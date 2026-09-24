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
        $listeningPids = $recordedPort !== null ? $this->fetchPidsListeningOnPort($recordedPort) : [];

        return $this->filterLingeringProcesses($rawProcesses, $recordedPort, $listeningPids);
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
    public function filterLingeringProcesses(array $processes, ?int $recordedPort = null, array $listeningPids = []): array
    {
        $normalizedBase = strtolower(str_replace('\\', '/', $this->basePath));
        $normalizedBaseBackslash = strtolower(str_replace('/', '\\', $this->basePath));

        $detectedNodeProcesses = [];
        $detectedChromiumProcesses = [];

        // 1. Identify Node.js / Playwright server processes belonging to this project
        foreach ($processes as $proc) {
            $cmd = $proc['command'];
            $cmdLower = strtolower($cmd);
            $nameLower = strtolower($proc['name']);

            $isNode = str_contains($nameLower, 'node') || str_contains($nameLower, 'pw-server');
            $isPlaywrightServer = str_contains($cmdLower, 'playwright') && (str_contains($cmdLower, 'run-server') || str_contains($cmdLower, 'launchserver'));

            if (! ($isNode && $isPlaywrightServer)) {
                continue;
            }

            $matchesPath = str_contains($cmdLower, $normalizedBase) || str_contains($cmdLower, $normalizedBaseBackslash);
            $hasRelativeNodeModules = str_contains($cmdLower, 'node_modules') && ! str_contains($cmdLower, ':\\') && ! str_starts_with($cmdLower, '/');
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

            $isChromium = str_contains($nameLower, 'chromium') || str_contains($nameLower, 'chrome');

            if (! $isChromium) {
                continue;
            }

            $isChildOfNode = in_array($proc['ppid'], $nodePids, true);
            $isHeadlessTestBrowser = str_contains($cmdLower, '--remote-debugging-pipe') || str_contains($cmdLower, '--headless');

            if ($isChildOfNode && $isHeadlessTestBrowser) {
                $detectedChromiumProcesses[$proc['pid']] = [
                    'pid' => $proc['pid'],
                    'name' => $proc['name'],
                    'port' => null,
                    'scope' => "Child of Node PID {$proc['ppid']}",
                    'command' => $this->truncateCommand($cmd),
                ];
            }
        }

        return array_merge($detectedNodeProcesses, $detectedChromiumProcesses);
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
     * Delete the stale playwright-server.json file and temp artifacts.
     */
    public function cleanStaleTempFiles(): void
    {
        $tempFile = $this->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pestphp'.DIRECTORY_SEPARATOR.'pest-plugin-browser'.DIRECTORY_SEPARATOR.'.temp'.DIRECTORY_SEPARATOR.'playwright-server.json';

        if (file_exists($tempFile)) {
            @unlink($tempFile);
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
        $sockets = $this->fetchWindowsListeningSockets();
        $runningProcesses = $this->fetchWindowsProcesses();

        $procMap = [];
        foreach ($runningProcesses as $proc) {
            $procMap[$proc['pid']] = $proc['name'];
        }

        $ignoredPorts = [5173, 8000, 3000];
        $hotFile = $this->basePath.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'hot';

        if (file_exists($hotFile)) {
            $hotUrl = @file_get_contents($hotFile);

            if ($hotUrl !== false && preg_match('/:(\d+)/', $hotUrl, $m)) {
                $ignoredPorts[] = (int) $m[1];
            }
        }

        $detected = [];
        $seenPids = [];

        foreach ($sockets as $sock) {
            $pid = $sock['pid'];
            $port = $sock['port'];

            if (in_array($pid, $seenPids, true)) {
                continue;
            }

            if (! isset($procMap[$pid])) {
                continue;
            }

            $name = $procMap[$pid];
            $nameLower = strtolower($name);

            $isTargetProcess = str_contains($nameLower, 'node') || str_contains($nameLower, 'chromium') || str_contains($nameLower, 'chrome');

            if (! $isTargetProcess) {
                continue;
            }

            $isRecordedPort = $recordedPort !== null && $port === $recordedPort;
            $isDynamicTestPort = $port >= 50000 && ! in_array($port, $ignoredPorts, true);

            if ($isRecordedPort || $isDynamicTestPort) {
                $seenPids[] = $pid;
                $scope = $isRecordedPort
                    ? ($this->appName ?: basename($this->basePath))
                    : ($this->appName ?: basename($this->basePath)).' (port '.$port.')';

                $detected[] = [
                    'pid' => $pid,
                    'name' => $name,
                    'port' => $port,
                    'scope' => $scope,
                    'command' => "{$name} listening on port {$port}",
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
}
