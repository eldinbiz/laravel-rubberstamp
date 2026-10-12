<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Support;

use Symfony\Component\Process\Process;
use Throwable;

final class BrowserProcessSanitizer
{
    /**
     * @param  string  $basePath  Base path of the host Laravel application.
     * @param  string|null  $appName  Optional application name for isolation.
     */
    public function __construct(
        private readonly string $basePath,
        private readonly ?string $appName = null,
    ) {}

    /**
     * Detect lingering Playwright/Node/Chromium processes associated with this project.
     *
     * @return array<int, array{
     *     pid: int,
     *     name: string,
     *     port: ?int,
     *     scope: string,
     *     command: string
     * }>
     */
    public function detectLingeringProcesses(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->detectWindowsLingeringProcesses();
        }

        $rawProcesses = $this->fetchSystemProcesses();
        $recordedPort = $this->getRecordedPort();
        $recordedSessionPid = $this->getRecordedSessionPid();
        $listeningPids = $recordedPort !== null ? $this->fetchPidsListeningOnPort($recordedPort) : [];

        return $this->filterLingeringProcesses($rawProcesses, $recordedPort, $listeningPids, $recordedSessionPid);
    }

    /**
     * Filter system processes to match this project's Playwright server and Chromium children.
     *
     * @param  array<int, array{pid: int, ppid: int, name: string, command: string}>  $processes
     * @param  array<int, int>  $listeningPids
     * @return array<int, array{
     *     pid: int,
     *     name: string,
     *     port: ?int,
     *     scope: string,
     *     command: string
     * }>
     */
    public function filterLingeringProcesses(array $processes, ?int $recordedPort = null, array $listeningPids = [], ?int $recordedSessionPid = null): array
    {
        $normalizedBase = strtolower(str_replace('\\', '/', $this->basePath));
        $normalizedBaseBackslash = strtolower(str_replace('/', '\\', $this->basePath));

        $detectedNodeProcesses = [];
        $detectedChromiumProcesses = [];
        $detectedPhpProcesses = [];
        $currentPid = getmypid();

        // 1. Identify Node.js, Playwright, and active/lingering PHP test runner processes
        foreach ($processes as $proc) {
            $cmd = $proc['command'];
            $cmdLower = strtolower($cmd);
            $nameLower = strtolower($proc['name']);

            // Never match our own process
            if ($currentPid !== false && $proc['pid'] === $currentPid) {
                continue;
            }

            // Explicitly ignore active developer runners and CLI server/queue watchers
            $isDevRunner = str_contains($cmdLower, 'concurrently')
                || str_contains($cmdLower, 'vite')
                || str_contains($cmdLower, 'npm-cli')
                || str_contains($cmdLower, 'artisan serve')
                || str_contains($cmdLower, 'artisan queue:listen')
                || str_contains($cmdLower, 'artisan queue:work')
                || str_contains($cmdLower, 'artisan schedule:work')
                || str_contains($cmdLower, 'artisan pulse:check');

            if ($isDevRunner) {
                continue;
            }

            $matchesPath = str_contains($cmdLower, $normalizedBase) || str_contains($cmdLower, $normalizedBaseBackslash);
            $hasRelativeNodeModules = str_contains($cmdLower, 'node_modules') && ! str_contains($cmdLower, ':\\') && ! str_starts_with($cmdLower, '/');
            $hasAbsoluteArtisanPath = (bool) preg_match('/(?:[a-zA-Z]:[\\\\\/]|\/)[^\s"]*artisan/i', $cmd);
            $hasRelativeArtisan = str_contains($cmdLower, 'artisan') && ! $hasAbsoluteArtisanPath;
            $holdsRecordedSession = $recordedSessionPid !== null && $proc['pid'] === $recordedSessionPid;

            // 1a. Check for tagged Pest browser test runner (rubberstamp.tag)
            $isPhp = str_contains($nameLower, 'php');
            $appNameLower = strtolower($this->appName);

            if ($isPhp && str_contains($cmdLower, 'rubberstamp.tag=')) {
                $isTaggedPest = str_contains($cmdLower, "[{$appNameLower}][rubberstamp]-browser-test")
                    || ($matchesPath && str_contains($cmdLower, '[rubberstamp]-browser-test'));

                if ($isTaggedPest) {
                    $detectedPhpProcesses[$proc['pid']] = [
                        'pid' => $proc['pid'],
                        'name' => $proc['name'],
                        'port' => null,
                        'scope' => ($this->appName ?: basename($this->basePath)).' (Pest Browser Runner)',
                        'command' => $this->truncateCommand($cmd),
                    ];

                    continue;
                }
            }

            // 1b. Check for sibling rubberstamp:browser CLI session or recorded session PID for this app
            if ($holdsRecordedSession || ($isPhp && str_contains($cmdLower, 'rubberstamp:browser') && ($matchesPath || $hasRelativeArtisan))) {
                $detectedPhpProcesses[$proc['pid']] = [
                    'pid' => $proc['pid'],
                    'name' => $proc['name'],
                    'port' => null,
                    'scope' => ($this->appName ?: basename($this->basePath)).' (Active Browser Session)',
                    'command' => $this->truncateCommand($cmd),
                ];

                continue;
            }

            // 1c. Check for Playwright Node server processes belonging to this project
            $isNode = str_contains($nameLower, 'node') || str_contains($nameLower, 'pw-server');
            $isPlaywrightServer = str_contains($cmdLower, 'playwright') && (str_contains($cmdLower, 'run-server') || str_contains($cmdLower, 'launchserver'));

            if (! ($isNode && $isPlaywrightServer)) {
                continue;
            }

            $holdsRecordedPort = $recordedPort !== null && in_array($proc['pid'], $listeningPids, true);

            $cliPort = null;

            if (preg_match('/--port\s+(\d+)/i', $cmd, $portMatches)) {
                $cliPort = (int) $portMatches[1];
            }

            $effectivePort = $holdsRecordedPort ? $recordedPort : $cliPort;

            if ($matchesPath || $holdsRecordedPort || $hasRelativeNodeModules) {
                $scope = $matchesPath
                    ? ($this->appName ?: basename($this->basePath))
                    : ($this->appName ?: basename($this->basePath)).' (local)';

                $detectedNodeProcesses[$proc['pid']] = [
                    'pid' => $proc['pid'],
                    'name' => $proc['name'],
                    'port' => $effectivePort,
                    'scope' => $scope,
                    'command' => $this->truncateCommand($cmd),
                ];
            }
        }

        $nodePids = array_keys($detectedNodeProcesses);

        // 2. Identify Chromium instances spawned by the detected Playwright Node processes
        foreach ($processes as $proc) {
            $nameLower = strtolower($proc['name']);
            $cmd = $proc['command'];
            $cmdLower = strtolower($cmd);

            $isPlaywrightBinary = str_contains($cmdLower, 'ms-playwright')
                || str_contains($cmdLower, 'chromium_headless_shell');

            $isChromium = str_contains($nameLower, 'chromium')
                || str_contains($nameLower, 'chrome')
                || $isPlaywrightBinary;

            if (! $isChromium) {
                continue;
            }

            $isChildOfNode = in_array($proc['ppid'], $nodePids, true);
            $isHeadlessTestBrowser = str_contains($cmdLower, '--remote-debugging-pipe') || str_contains($cmdLower, '--headless');

            if ($isPlaywrightBinary) {
                $detectedChromiumProcesses[$proc['pid']] = [
                    'pid' => $proc['pid'],
                    'name' => $proc['name'],
                    'port' => null,
                    'scope' => 'Playwright Browser Worker',
                    'command' => $this->truncateCommand($cmd),
                ];
            } elseif ($isChildOfNode && $isHeadlessTestBrowser) {
                $detectedChromiumProcesses[$proc['pid']] = [
                    'pid' => $proc['pid'],
                    'name' => $proc['name'],
                    'port' => null,
                    'scope' => "Child of Node PID {$proc['ppid']}",
                    'command' => $this->truncateCommand($cmd),
                ];
            } elseif ($isHeadlessTestBrowser && str_contains($cmdLower, '--remote-debugging-pipe')) {
                $detectedChromiumProcesses[$proc['pid']] = [
                    'pid' => $proc['pid'],
                    'name' => $proc['name'],
                    'port' => null,
                    'scope' => 'Orphaned Test Browser',
                    'command' => $this->truncateCommand($cmd),
                ];
            }
        }

        return array_merge($detectedPhpProcesses, $detectedNodeProcesses, $detectedChromiumProcesses);
    }

    /**
     * Terminate the given list of process IDs.
     *
     * @param  array<int, int>  $pids
     * @return array{killed: int, failed: int}
     */
    public function killProcesses(array $pids): array
    {
        $killed = 0;
        $failed = 0;

        foreach ($pids as $pid) {
            if ($pid <= 0) {
                continue;
            }

            if (PHP_OS_FAMILY === 'Windows') {
                $process = new Process(['taskkill', '/F', '/T', '/PID', (string) $pid]);
            } else {
                $process = new Process(['kill', '-9', (string) $pid]);
            }

            try {
                $process->run();

                if ($process->isSuccessful()) {
                    $killed++;
                } else {
                    $failed++;
                }
            } catch (Throwable) {
                $failed++;
            }
        }

        if (PHP_OS_FAMILY === 'Windows' && $killed > 0) {
            // Give Windows OS kernel time to release socket TIME_WAIT and named pipe handles
            usleep(1_500_000);
        }

        return [
            'killed' => $killed,
            'failed' => $failed,
        ];
    }

    /**
     * Read recorded port from pest-plugin-browser state file.
     */
    public function getRecordedPort(): ?int
    {
        $tempFile = $this->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pestphp'.DIRECTORY_SEPARATOR.'pest-plugin-browser'.DIRECTORY_SEPARATOR.'.temp'.DIRECTORY_SEPARATOR.'playwright-server.json';

        if (! file_exists($tempFile)) {
            return null;
        }

        $content = @file_get_contents($tempFile);

        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);

        if (is_array($data) && isset($data['port']) && is_numeric($data['port'])) {
            return (int) $data['port'];
        }

        return null;
    }

    /**
     * Read recorded active session PID from rubberstamp-session.json state file.
     */
    public function getRecordedSessionPid(): ?int
    {
        $tempFile = $this->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pestphp'.DIRECTORY_SEPARATOR.'pest-plugin-browser'.DIRECTORY_SEPARATOR.'.temp'.DIRECTORY_SEPARATOR.'rubberstamp-session.json';

        if (! file_exists($tempFile)) {
            return null;
        }

        $content = @file_get_contents($tempFile);

        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);

        if (is_array($data) && isset($data['pid']) && is_numeric($data['pid'])) {
            return (int) $data['pid'];
        }

        return null;
    }

    /**
     * Persist active browser session PID into rubberstamp-session.json.
     */
    public function persistActiveSession(int $pid): void
    {
        $tempDir = $this->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pestphp'.DIRECTORY_SEPARATOR.'pest-plugin-browser'.DIRECTORY_SEPARATOR.'.temp';

        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $tempFile = $tempDir.DIRECTORY_SEPARATOR.'rubberstamp-session.json';
        $payload = json_encode([
            'pid' => $pid,
            'time' => microtime(true),
        ]);

        if ($payload !== false) {
            @file_put_contents($tempFile, $payload);
        }
    }

    /**
     * Remove the active session lock file.
     */
    public function cleanActiveSession(): void
    {
        $tempFile = $this->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pestphp'.DIRECTORY_SEPARATOR.'pest-plugin-browser'.DIRECTORY_SEPARATOR.'.temp'.DIRECTORY_SEPARATOR.'rubberstamp-session.json';

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    /**
     * Delete the stale playwright-server.json file and temp artifacts.
     */
    public function cleanStaleTempFiles(): void
    {
        $tempDir = $this->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pestphp'.DIRECTORY_SEPARATOR.'pest-plugin-browser'.DIRECTORY_SEPARATOR.'.temp';
        $tempFile = $tempDir.DIRECTORY_SEPARATOR.'playwright-server.json';

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }

        $sessionFile = $tempDir.DIRECTORY_SEPARATOR.'rubberstamp-session.json';

        if (file_exists($sessionFile)) {
            @unlink($sessionFile);
        }

        if (is_dir($tempDir)) {
            $files = @glob($tempDir.DIRECTORY_SEPARATOR.'*');

            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
    }

    /**
     * Query system processes across Windows and Unix platforms.
     *
     * @return array<int, array{pid: int, ppid: int, name: string, command: string}>
     */
    public function fetchSystemProcesses(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->fetchWindowsProcesses();
        }

        return $this->fetchUnixProcesses();
    }

    /**
     * Native Windows lingering process detection using tasklist and netstat (zero PowerShell).
     *
     * @return array<int, array{
     *     pid: int,
     *     name: string,
     *     port: ?int,
     *     scope: string,
     *     command: string
     * }>
     */
    public function detectWindowsLingeringProcesses(): array
    {
        $recordedPort = $this->getRecordedPort();
        $recordedSessionPid = $this->getRecordedSessionPid();
        $sockets = $this->fetchWindowsListeningSockets();

        $listeningPids = [];

        if ($recordedPort !== null) {
            foreach ($sockets as $s) {
                if ($s['port'] === $recordedPort) {
                    $listeningPids[] = $s['pid'];
                }
            }
        }

        // 1. High-fidelity CIM/PowerShell process discovery with CommandLine & ParentProcessId
        $cimProcesses = $this->fetchWindowsProcessesViaCim();

        if (! empty($cimProcesses)) {
            return $this->filterLingeringProcesses($cimProcesses, $recordedPort, $listeningPids, $recordedSessionPid);
        }

        // 2. Fallback detection via netstat & tasklist (zero PowerShell / AppLocker mode)
        $runningProcesses = $this->fetchWindowsProcesses();
        $procMap = [];
        foreach ($runningProcesses as $proc) {
            $procMap[$proc['pid']] = $proc['name'];
        }

        $detected = [];

        // 2a. Recorded active session PID check in fallback mode
        if ($recordedSessionPid !== null && isset($procMap[$recordedSessionPid])) {
            $name = $procMap[$recordedSessionPid];
            $detected[] = [
                'pid' => $recordedSessionPid,
                'name' => $name,
                'port' => null,
                'scope' => ($this->appName ?: basename($this->basePath)).' (Active Browser Session)',
                'command' => "{$name} (recorded active session PID {$recordedSessionPid})",
            ];
        }

        // 2b. Sockets strictly on the recorded port from playwright-server.json
        if ($recordedPort !== null) {
            foreach ($sockets as $sock) {
                $pid = $sock['pid'];
                $port = $sock['port'];

                if ($port !== $recordedPort || ! isset($procMap[$pid])) {
                    continue;
                }

                $name = $procMap[$pid];
                $nameLower = strtolower($name);

                if (str_contains($nameLower, 'node') || str_contains($nameLower, 'pw-server')) {
                    $detected[] = [
                        'pid' => $pid,
                        'name' => $name,
                        'port' => $recordedPort,
                        'scope' => $this->appName ?: basename($this->basePath),
                        'command' => "{$name} listening on recorded port {$recordedPort}",
                    ];
                }
            }
        }

        // 2b. Supplementary Chromium check in fallback mode (only standalone chromium.exe)
        foreach ($runningProcesses as $proc) {
            $pid = $proc['pid'];
            $name = $proc['name'];
            $nameLower = strtolower($name);

            if ($nameLower === 'chromium.exe') {
                $detected[] = [
                    'pid' => $pid,
                    'name' => $name,
                    'port' => null,
                    'scope' => 'Orphaned Test Browser',
                    'command' => "{$name} (unattached test browser)",
                ];
            }
        }

        return $detected;
    }

    /**
     * Query Windows processes via native tasklist (zero PowerShell).
     *
     * @return array<int, array{pid: int, ppid: int, name: string, command: string}>
     */
    private function fetchWindowsProcesses(): array
    {
        $process = new Process(['tasklist', '/FO', 'CSV', '/NH']);

        try {
            $process->run();

            if (! $process->isSuccessful()) {
                return [];
            }

            $lines = explode("\n", trim($process->getOutput()));
            $processes = [];

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $fields = str_getcsv($line);

                if (count($fields) >= 2) {
                    $name = (string) $fields[0];
                    $pid = (int) $fields[1];

                    if ($pid > 0) {
                        $processes[] = [
                            'pid' => $pid,
                            'ppid' => 0,
                            'name' => $name,
                            'command' => $name,
                        ];
                    }
                }
            }

            return $processes;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Query Unix processes via ps.
     *
     * @return array<int, array{pid: int, ppid: int, name: string, command: string}>
     */
    private function fetchUnixProcesses(): array
    {
        $process = new Process(['ps', '-A', '-o', 'pid=,ppid=,comm=,args=']);

        try {
            $process->run();

            if (! $process->isSuccessful()) {
                return [];
            }

            $lines = explode("\n", trim($process->getOutput()));
            $processes = [];

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $parts = preg_split('/\s+/', $line, 4);

                if (is_array($parts) && count($parts) >= 3) {
                    $processes[] = [
                        'pid' => (int) $parts[0],
                        'ppid' => (int) $parts[1],
                        'name' => $parts[2],
                        'command' => $parts[3] ?? $parts[2],
                    ];
                }
            }

            return $processes;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Find PIDs listening on the specified TCP port.
     *
     * @return array<int, int>
     */
    public function fetchPidsListeningOnPort(int $port): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->fetchWindowsPidsOnPort($port);
        }

        return $this->fetchUnixPidsOnPort($port);
    }

    /**
     * Query TCP listening sockets on Windows via netstat (zero PowerShell).
     *
     * @return array<int, array{port: int, pid: int}>
     */
    public function fetchWindowsListeningSockets(): array
    {
        $process = new Process(['netstat', '-ano']);

        try {
            $process->run();

            if (! $process->isSuccessful()) {
                return [];
            }

            $sockets = [];
            foreach (explode("\n", $process->getOutput()) as $line) {
                $line = trim($line);

                if (preg_match('/TCP\s+[\d\.:\[\]]+:(\d+)\s+.*LISTENING\s+(\d+)/i', $line, $matches)) {
                    $port = (int) $matches[1];
                    $pid = (int) $matches[2];

                    if ($port > 0 && $pid > 0) {
                        $sockets[] = [
                            'port' => $port,
                            'pid' => $pid,
                        ];
                    }
                }
            }

            return $sockets;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Find PIDs listening on port in Windows using netstat.
     *
     * @return array<int, int>
     */
    private function fetchWindowsPidsOnPort(int $port): array
    {
        $sockets = $this->fetchWindowsListeningSockets();
        $pids = [];

        foreach ($sockets as $socket) {
            if ($socket['port'] === $port) {
                $pids[] = $socket['pid'];
            }
        }

        return array_values(array_unique($pids));
    }

    /**
     * Find PIDs listening on port in Unix using lsof.
     *
     * @return array<int, int>
     */
    private function fetchUnixPidsOnPort(int $port): array
    {
        $process = new Process(['lsof', '-ti', ":{$port}"]);

        try {
            $process->run();

            if (! $process->isSuccessful()) {
                return [];
            }

            $pids = [];
            foreach (explode("\n", trim($process->getOutput())) as $line) {
                $pid = (int) trim($line);

                if ($pid > 0) {
                    $pids[] = $pid;
                }
            }

            return array_values(array_unique($pids));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Truncate long command lines for clean terminal presentation.
     */
    private function truncateCommand(string $command, int $maxLength = 70): string
    {
        $cleaned = trim(preg_replace('/\s+/', ' ', $command) ?? $command);

        if (strlen($cleaned) <= $maxLength) {
            return $cleaned;
        }

        return substr($cleaned, 0, $maxLength - 3).'...';
    }

    /**
     * Query Windows processes with full CommandLine and ParentProcessId via CIM/PowerShell.
     *
     * @return array<int, array{pid: int, ppid: int, name: string, command: string}>
     */
    private function fetchWindowsProcessesViaCim(): array
    {
        $script = 'Get-CimInstance Win32_Process | Where-Object { $_.Name -in @(\'node.exe\',\'chromium.exe\',\'chrome.exe\',\'php.exe\',\'php\') } | Select-Object ProcessId,ParentProcessId,Name,CommandLine | ConvertTo-Json -Compress';
        $encodedCommand = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));

        $process = new Process([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy', 'Bypass',
            '-EncodedCommand',
            $encodedCommand,
        ]);
        $process->setTimeout(10);

        try {
            $process->run();

            if (! $process->isSuccessful()) {
                return [];
            }

            $output = trim($process->getOutput());

            if ($output === '' || $output === 'null') {
                return [];
            }

            $data = json_decode($output, true);

            if (! is_array($data)) {
                return [];
            }

            if (isset($data['ProcessId'])) {
                $data = [$data];
            }

            $results = [];

            foreach ($data as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $pid = (int) ($item['ProcessId'] ?? 0);
                $ppid = (int) ($item['ParentProcessId'] ?? 0);
                $name = (string) ($item['Name'] ?? '');
                $command = (string) ($item['CommandLine'] ?? $name);

                if ($pid > 0) {
                    $results[] = [
                        'pid' => $pid,
                        'ppid' => $ppid,
                        'name' => $name,
                        'command' => $command,
                    ];
                }
            }

            return $results;
        } catch (Throwable) {
            return [];
        }
    }
}
