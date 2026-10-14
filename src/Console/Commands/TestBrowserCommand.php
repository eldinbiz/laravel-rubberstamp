<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Console\Commands;

use Eldinbiz\RubberStamp\Console\Concerns\InteractsWithRubberStampOptions;
use Eldinbiz\RubberStamp\Support\AuditMetadataResolver;
use Eldinbiz\RubberStamp\Support\BrowserEnvironmentDoctor;
use Eldinbiz\RubberStamp\Support\BrowserProcessSanitizer;
use Illuminate\Console\Command;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Process\Process;
use Throwable;

final class TestBrowserCommand extends Command
{
    use InteractsWithRubberStampOptions;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'rubberstamp:browser
        {target? : The test file or directory to execute (defaults to tests/Browser)}
        {--selected-test-suite : Interactively select one or more test suites or classes to run}
        {--doctor : Run environment and Playwright health checks only}
        {--check : Alias for --doctor}
        {--skip-health-check : Skip pre-flight environment health check}
        {--force-kill-orphans : Automatically terminate lingering Playwright processes without prompting}
        {--skip-orphan-check : Skip checking for lingering Playwright processes}
        {--idle-timeout= : Maximum seconds to wait without output before timing out (defaults to 90s)}
        {--pest-path= : Custom path to Pest binary}
        {--author= : Author/tester name (defaults to git config user.name)}
        {--sop= : SOP policy or RFC ticket code (defaults to N/A)}
        {--document-id= : Custom Document ID prefix (defaults to DOC-TEST-)}
        {--reviewed-by= : Pipe-separated reviewers/roles (e.g. "Mr Smith,QA Engineer|QA Head")}
        {--approved-by= : Pipe-separated approvers/roles (e.g. "Jane,QA Lead|Technical Lead")}
        {--acknowledged-by= : Pipe-separated acknowledgers/roles (e.g. "Bob,Project Manager|Product Owner")}
        {--i|interactive : Interactively configure audit metadata and sign-off approval sheet}
        {--no-doc : Skip corporate report generation}';

    /**
     * The console command description.
     */
    protected $description = 'Run Pest browser tests with Playwright self-health diagnostics, Vite protection, and snapshot documentation.';

    /**
     * Configure command to ignore validation errors for Pest argument passthrough.
     */
    protected function configure(): void
    {
        $this->ignoreValidationErrors();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $doctor = new BrowserEnvironmentDoctor(
            base_path(),
            (string) config('rubberstamp.chromium_binary') ?: null,
        );

        if ($this->option('doctor') || $this->option('check')) {
            return $this->runDoctor($doctor);
        }

        if (! $this->option('skip-health-check')) {
            $doctorExitCode = $this->runDoctor($doctor);

            if ($doctorExitCode !== self::SUCCESS) {
                return $doctorExitCode;
            }

            $this->newLine();
        }

        if (! $this->option('skip-orphan-check')) {
            $sanitizerExitCode = $this->runProcessSanitizer();

            if ($sanitizerExitCode !== self::SUCCESS) {
                return $sanitizerExitCode;
            }
        }

        return $this->executeBrowserTests($doctor);
    }

    /**
     * Run doctor diagnostics and render formatted report.
     */
    private function runDoctor(BrowserEnvironmentDoctor $doctor): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->line('<fg=cyan;options=bold>       Browser Testing & Playwright Environment Doctor          </>');
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->newLine();

        $checks = $doctor->checkAll();

        foreach ($checks as $check) {
            $badge = match ($check['status']) {
                BrowserEnvironmentDoctor::STATUS_OK => '<fg=black;bg=green;options=bold>  OK  </>',
                BrowserEnvironmentDoctor::STATUS_WARNING => '<fg=black;bg=yellow;options=bold> WARN </>',
                default => '<fg=white;bg=red;options=bold> FAIL </>',
            };

            $this->line(" {$badge} <options=bold>{$check['name']}</>");
            $this->line("        <fg=gray>{$check['message']}</>");

            if ($check['suggestion'] !== null) {
                $this->line("        <fg=yellow>Remedy:</> {$check['suggestion']}");
            }

            $this->newLine();
        }

        $this->line('<fg=cyan;options=bold>=================================================================</>');

        if ($doctor->hasFailures($checks)) {
            $this->error('Diagnostic check failed. Please resolve the issues above before running tests.');

            return self::FAILURE;
        }

        $this->info('All core browser testing dependencies are installed and operational!');

        return self::SUCCESS;
    }

    /**
     * Detect and sanitize lingering Playwright processes.
     */
    private function runProcessSanitizer(): int
    {
        $appName = (string) config('app.name', 'laravel');
        $sanitizer = new BrowserProcessSanitizer(base_path(), $appName);

        $processes = $sanitizer->detectLingeringProcesses();

        if (empty($processes)) {
            $sanitizer->cleanStaleTempFiles();

            return self::SUCCESS;
        }

        $this->renderProcessWarningTable($processes, $appName);

        $forceKill = (bool) $this->option('force-kill-orphans');
        $isInteractive = $this->input->isInteractive();

        if ($forceKill || (! $isInteractive)) {
            $pids = array_column($processes, 'pid');
            $result = $sanitizer->killProcesses($pids);
            $sanitizer->cleanStaleTempFiles();

            usleep(500_000);

            $this->info("Automatically cleaned up {$result['killed']} lingering process(es).");
            $this->newLine();

            return self::SUCCESS;
        }

        $confirmed = $this->confirm('Terminate lingering process(es) and continue testing?', true);

        if (! $confirmed) {
            $this->newLine();
            $this->warn('Browser testing aborted by user. Please resolve lingering processes before running rubberstamp:browser.');

            return self::FAILURE;
        }

        $pids = array_column($processes, 'pid');
        $result = $sanitizer->killProcesses($pids);
        $sanitizer->cleanStaleTempFiles();

        usleep(500_000);

        $this->info("Cleaned up {$result['killed']} lingering process(es). Proceeding with browser tests...");
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Render ASCII table for detected lingering processes.
     *
     * @param  array<int, array{pid: int, name: string, port: ?int, scope: string, command: string}>  $processes
     */
    private function renderProcessWarningTable(array $processes, string $appName): void
    {
        $this->newLine();
        $this->line("<fg=yellow;options=bold>! [WARNING] Lingering Playwright browser test processes detected for [{$appName}].</>");
        $this->line('<fg=gray>These processes hold port/socket locks from a previous run and will cause Pest to hang indefinitely.</>');
        $this->newLine();

        $headers = ['PID', 'Process Name', 'Port', 'Repository / Scope', 'Command Line'];
        $rows = [];

        foreach ($processes as $proc) {
            $rows[] = [
                $proc['pid'],
                $proc['name'],
                $proc['port'] !== null ? (string) $proc['port'] : '-',
                $proc['scope'],
                $proc['command'],
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
    }

    /**
     * Execute Pest browser tests with logging, hot backup, and snapshot capture.
     */
    private function executeBrowserTests(BrowserEnvironmentDoctor $doctor): int
    {
        $timestamp = now()->format('Ymd_His');
        $runName = "browser_test_{$timestamp}";

        $resolver = new AuditMetadataResolver(base_path());
        $docOptions = $this->resolveDocOptions($resolver);

        $resultsDir = (string) config('rubberstamp.results_dir', 'doctest-reports/browser-test-log');

        $runSnapshotDir = $resultsDir.DIRECTORY_SEPARATOR.$runName;
        $testLog = $resultsDir.DIRECTORY_SEPARATOR.$runName.'.log';

        $this->ensureDirectoryExists(base_path($resultsDir));
        $this->ensureDirectoryExists(base_path($runSnapshotDir));

        putenv('BROWSER_SNAPSHOT_DIR='.base_path($runSnapshotDir));
        $_ENV['BROWSER_SNAPSHOT_DIR'] = base_path($runSnapshotDir);
        $_SERVER['BROWSER_SNAPSHOT_DIR'] = base_path($runSnapshotDir);

        $configuredChromium = (string) config('rubberstamp.chromium_binary') ?: null;
        $envChromium = getenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH') ?: null;

        $chromiumPath = null;

        if ($configuredChromium !== null && file_exists($configuredChromium)) {
            $chromiumPath = $configuredChromium;
        } elseif ($envChromium !== null && file_exists((string) $envChromium)) {
            $chromiumPath = (string) $envChromium;
        } elseif ($doctor->findPlaywrightCachedChromium() === null) {
            $chromiumPath = $doctor->detectChromiumBinary();
        }

        if ($chromiumPath !== null) {
            putenv("PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH={$chromiumPath}");
            putenv('PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1');
            $_ENV['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'] = $chromiumPath;
            $_ENV['PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD'] = '1';
            $doctor->fixLinuxContainerSymlinks($chromiumPath);
        }

        $hotFile = public_path('hot');
        $hotBackup = public_path('hot.test-backup');
        $hotRestored = false;

        if (file_exists($hotFile)) {
            @rename($hotFile, $hotBackup);
            $hotRestored = true;
        }

        $restoreHot = function () use ($hotFile, $hotBackup, &$hotRestored): void {
            if ($hotRestored && file_exists($hotBackup)) {
                @rename($hotBackup, $hotFile);
                $hotRestored = false;
            }
        };

        register_shutdown_function($restoreHot);

        try {
            $this->cleanupPreTestArtifacts();

            $sanitizer = new BrowserProcessSanitizer(base_path(), (string) config('app.name', 'laravel'));
            $sanitizer->persistActiveSession((int) getmypid());

            $rawTarget = $this->argument('target');
            $targetArg = is_string($rawTarget) && $rawTarget !== '' ? $rawTarget : null;
            $target = $targetArg ?? 'tests/Browser';

            $selectedTargets = [];

            if ($this->option('selected-test-suite')) {
                $selectedTargets = $this->promptForTestSuites('browser', $targetArg);

                if (empty($selectedTargets)) {
                    $this->warn('No test suites selected. Aborting.');

                    return self::SUCCESS;
                }
            }

            $pestBinary = $this->option('pest-path')
                ?: config('rubberstamp.pest_binary')
                ?: $doctor->detectPestBinary();

            if ($pestBinary === null || ! file_exists((string) $pestBinary)) {
                $this->error('Pest executable could not be found. Please ensure vendor/bin/pest exists.');

                return self::FAILURE;
            }

            $this->callSilently('config:clear');
            $this->callSilently('view:clear');

            $memoryLimit = (string) config('rubberstamp.memory_limit', '1024M');
            $forwardedArgs = $this->resolveForwardedArguments();

            $bootstrapPath = str_replace('\\', '/', realpath(__DIR__.'/../../Support/browser-test-bootstrap.php') ?: (__DIR__.'/../../Support/browser-test-bootstrap.php'));
            $pestScript = (str_ends_with(strtolower((string) $pestBinary), '.bat') && file_exists(substr((string) $pestBinary, 0, -4)))
                ? substr((string) $pestBinary, 0, -4)
                : (string) $pestBinary;
            $pestScript = str_replace('\\', '/', realpath($pestScript) ?: $pestScript);

            $targets = ! empty($selectedTargets) ? $selectedTargets : [$target];
            $appName = (string) config('app.name', 'laravel');

            $command = array_merge(
                [
                    PHP_BINARY,
                    '-d', "memory_limit={$memoryLimit}",
                    '-d', 'output_buffering=0',
                    '-d', 'display_errors=1',
                    '-d', 'display_startup_errors=1',
                    '-d', "rubberstamp.tag=[{$appName}][rubberstamp]-browser-test",
                    '-d', "auto_prepend_file={$bootstrapPath}",
                    $pestScript,
                ],
                $targets,
                [
                    '--colors=always',
                ],
                $forwardedArgs,
            );

            $testLogHandle = fopen(base_path($testLog), 'wb');

            $this->clearEnv();

            putenv('BROWSER_SNAPSHOT_DIR='.base_path($runSnapshotDir));
            $_ENV['BROWSER_SNAPSHOT_DIR'] = base_path($runSnapshotDir);
            $_SERVER['BROWSER_SNAPSHOT_DIR'] = base_path($runSnapshotDir);

            if ($chromiumPath !== null) {
                putenv("PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH={$chromiumPath}");
                putenv('PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1');
                $_ENV['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'] = $chromiumPath;
                $_ENV['PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD'] = '1';
            }

            $process = new Process(
                command: $command,
                cwd: base_path(),
                env: null,
                timeout: null,
            );

            $this->info("Starting Pest Browser run: {$runName}");

            if (! empty($selectedTargets)) {
                $this->line('<fg=gray>Selected Test Suites ('.count($selectedTargets).'):</>');
                foreach ($selectedTargets as $st) {
                    $this->line("  <fg=gray>-</> {$st}");
                }
            } else {
                $this->line("<fg=gray>Target:</> {$target}");
            }
            $this->newLine();

            $idleTimeoutOption = $this->option('idle-timeout');
            $idleTimeout = $idleTimeoutOption !== null
                ? (int) $idleTimeoutOption
                : (int) config('rubberstamp.idle_timeout', 90);

            $process->start();

            $lastOutputTime = microtime(true);
            $lastCountdownRender = 0.0;
            $timedOut = false;

            $activeTest = null;
            $streamState = new class
            {
                public bool $summaryStarted = false;

                public bool $suiteConcluded = false;

                public int $liveLinesCount = 0;

                public bool $transientActive = false;
            };
            $errBuffer = '';
            $outBuffer = '';

            $handleErrLine = function (string $line) use (
                &$activeTest,
                &$lastOutputTime,
                $streamState,
                $testLogHandle
            ): void {
                $trimmed = trim($line);

                if (preg_match('/^__RUBBERSTAMP_EVENT__:([A-Z]+):([A-Za-z0-9+\/=]+)$/', $trimmed, $matches)) {
                    if ($streamState->suiteConcluded) {
                        return;
                    }

                    $event = $matches[1];
                    $json = base64_decode($matches[2], true);
                    $payload = is_string($json) ? json_decode($json, true) : null;

                    if (is_array($payload)) {
                        $lastOutputTime = microtime(true);
                        $testName = (string) ($payload['name'] ?? 'test scenario');
                        $testFile = (string) ($payload['file'] ?? '');
                        $testLine = isset($payload['line']) ? (int) $payload['line'] : null;

                        if ($event === 'START') {
                            $activeTest = [
                                'name' => $testName,
                                'file' => $testFile,
                                'line' => $testLine,
                                'started_at' => microtime(true),
                            ];

                            $streamState->summaryStarted = false;

                            if ($this->output->isDecorated()) {
                                $terminalWidth = max(30, (new Terminal)->getWidth());
                                $maxNameWidth = max(10, $terminalWidth - 15);
                                $truncatedName = mb_strlen($testName) > $maxNameWidth
                                    ? mb_substr($testName, 0, $maxNameWidth - 3).'...'
                                    : $testName;

                                $this->output->write("\r\x1b[2K  <fg=yellow>⏳</> <fg=white>{$truncatedName}</> <fg=gray>...</>\r");
                                $streamState->transientActive = true;
                            }
                        } elseif ($event === 'END') {
                            if ($streamState->transientActive) {
                                $this->output->write("\r\x1b[2K");
                                $streamState->transientActive = false;
                            }

                            if (! $streamState->summaryStarted && $this->output->isDecorated()) {
                                $duration = isset($payload['duration'])
                                    ? (float) $payload['duration']
                                    : max(0.001, microtime(true) - ($activeTest['started_at'] ?? microtime(true)));
                                $durationFormatted = number_format($duration, 2).'s';

                                $terminalWidth = max(30, (new Terminal)->getWidth());
                                $durationLen = mb_strlen($durationFormatted);
                                $maxNameWidth = max(10, $terminalWidth - $durationLen - 8);
                                $displayName = mb_strlen($testName) > $maxNameWidth
                                    ? mb_substr($testName, 0, $maxNameWidth - 3).'...'
                                    : $testName;

                                $padding = max(2, $terminalWidth - mb_strlen($displayName) - $durationLen - 6);
                                $this->output->writeln("  <fg=green>✓</> <fg=default>{$displayName}</>".str_repeat(' ', $padding)."<fg=gray>{$durationFormatted}</>");
                                $streamState->liveLinesCount++;
                            }

                            $activeTest = null;
                        }
                    }

                    return;
                }

                if ($line !== '') {
                    $this->output->writeln($line);

                    if (is_resource($testLogHandle)) {
                        fwrite($testLogHandle, $line."\n");
                    }
                }
            };

            $handleOutLine = function (string $line) use (
                $streamState,
                $testLogHandle
            ): void {
                if (is_resource($testLogHandle)) {
                    fwrite($testLogHandle, $line."\n");
                }

                $cleanLine = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $line) ?? '';
                $strippedLine = trim(strip_tags($cleanLine));

                if (preg_match('/^Tests:\s+/i', $strippedLine)) {
                    $streamState->suiteConcluded = true;
                }

                // If Pest outputs an empty line (gap between test classes or before summary)
                if ($strippedLine === '' && $streamState->liveLinesCount > 0) {
                    if ($streamState->transientActive) {
                        $this->output->write("\r\x1b[2K");
                        $streamState->transientActive = false;
                    }

                    if ($this->output->isDecorated()) {
                        $this->output->write(str_repeat("\x1b[1A\x1b[2K", $streamState->liveLinesCount));
                        $streamState->liveLinesCount = 0;
                    }

                    $this->output->writeln($line);

                    return;
                }

                // Detect Pest class status header (PASS, FAIL, WARN, RISKY, SKIPPED)
                if (preg_match('/^(PASS|FAIL|WARN|RISKY|SKIPPED)\s+/i', $strippedLine)) {
                    $streamState->summaryStarted = true;

                    if ($streamState->transientActive) {
                        $this->output->write("\r\x1b[2K");
                        $streamState->transientActive = false;
                    }

                    if ($streamState->liveLinesCount > 0 && $this->output->isDecorated()) {
                        $this->output->write(str_repeat("\x1b[1A\x1b[2K", $streamState->liveLinesCount));
                        $streamState->liveLinesCount = 0;
                    }
                }

                if ($streamState->transientActive) {
                    $this->output->write("\r\x1b[2K");
                    $streamState->transientActive = false;
                }

                $this->output->writeln($line);
            };

            while ($process->isRunning()) {
                $out = $process->getIncrementalOutput();
                $err = $process->getIncrementalErrorOutput();

                if ($out !== '') {
                    $lastOutputTime = microtime(true);
                    $outBuffer .= $out;

                    if (str_contains($outBuffer, "\n")) {
                        $lines = explode("\n", $outBuffer);
                        $outBuffer = array_pop($lines);
                        foreach ($lines as $l) {
                            $handleOutLine(rtrim($l, "\r"));
                        }
                    }
                }

                if ($err !== '') {
                    $lastOutputTime = microtime(true);
                    $errBuffer .= $err;

                    if (str_contains($errBuffer, "\n")) {
                        $lines = explode("\n", $errBuffer);
                        $errBuffer = array_pop($lines);
                        foreach ($lines as $l) {
                            $handleErrLine(rtrim($l, "\r"));
                        }
                    }
                }

                if ($out === '' && $err === '' && $idleTimeout > 0) {
                    $elapsed = microtime(true) - $lastOutputTime;

                    if ($elapsed > $idleTimeout) {
                        $timedOut = true;
                        $process->stop(5);

                        break;
                    }

                    if ($elapsed >= 5.0 && $this->output->isDecorated()) {
                        $now = microtime(true);

                        if ($now - $lastCountdownRender >= 1.0) {
                            $lastCountdownRender = $now;
                            $remaining = max(0, (int) ceil($idleTimeout - $elapsed));

                            if ($activeTest !== null) {
                                $testElapsed = (int) floor(microtime(true) - $activeTest['started_at']);
                                $terminalWidth = max(30, (new Terminal)->getWidth());
                                $maxCountdownName = max(10, $terminalWidth - 55);
                                $countdownName = mb_strlen($activeTest['name']) > $maxCountdownName
                                    ? mb_substr($activeTest['name'], 0, $maxCountdownName - 3).'...'
                                    : $activeTest['name'];
                                $this->output->write("\r\x1b[2K  <fg=yellow>⏳</> <fg=white>{$countdownName}</> <fg=gray>(running for {$testElapsed}s, {$remaining}s until idle timeout)</>\r");
                            } else {
                                $this->output->write("\r\x1b[2K  <fg=gray>Waiting for test output... ({$remaining}s until idle timeout)</>\r");
                            }
                            $streamState->transientActive = true;
                        }
                    }
                }

                usleep(50_000);
            }

            if ($streamState->transientActive) {
                $this->output->write("\r\x1b[2K");
                $streamState->transientActive = false;
            }

            $remainingOut = $process->getIncrementalOutput();

            if ($remainingOut !== '') {
                $outBuffer .= $remainingOut;
            }

            if ($outBuffer !== '') {
                $lines = explode("\n", $outBuffer);
                foreach ($lines as $l) {
                    if ($l !== '') {
                        $handleOutLine(rtrim($l, "\r"));
                    }
                }
                $outBuffer = '';
            }

            $remainingErr = $process->getIncrementalErrorOutput();

            if ($remainingErr !== '') {
                $errBuffer .= $remainingErr;
            }

            if ($errBuffer !== '') {
                $lines = explode("\n", $errBuffer);
                foreach ($lines as $l) {
                    if ($l !== '') {
                        $handleErrLine(rtrim($l, "\r"));
                    }
                }
                $errBuffer = '';
            }

            if ($timedOut) {
                if ($activeTest !== null) {
                    $stalledName = (string) $activeTest['name'];
                    $stalledFile = (string) ($activeTest['file'] !== '' ? $activeTest['file'] : $target);
                    $stalledLine = $activeTest['line'] ?? null;
                    $stalledElapsed = number_format(microtime(true) - $activeTest['started_at'], 1).'s';

                    $padding = max(2, 95 - mb_strlen($stalledName));
                    $this->output->writeln("  <fg=red>✗</> <fg=default>{$stalledName}</>".str_repeat(' ', $padding)."<fg=red>[TIMED OUT after {$stalledElapsed}]</>");

                    $this->newLine();
                    $this->line('<fg=red;options=bold>=================================================================</>');
                    $this->line("<fg=red;options=bold>Browser test scenario deadlock detected (no output for {$idleTimeout}s).</>");
                    $this->line("<fg=yellow;options=bold>Stalled Scenario:</> <fg=white;options=bold>{$stalledName}</>");
                    $this->line("<fg=yellow;options=bold>File Location:</>    <fg=white>{$stalledFile}".($stalledLine ? ":{$stalledLine}" : '').'</>');
                    $this->line("<fg=yellow;options=bold>Elapsed on Test:</>  <fg=white>{$stalledElapsed}</>");
                    $this->newLine();
                    $this->line('<fg=yellow>The test stalled during execution (e.g. Playwright actionability deadlock, unresolvable element, or infinite wait).</>');
                    $this->line('<fg=yellow>Lingering test processes and socket locks will be terminated in cleanup.</>');
                    $this->newLine();
                    $this->line('<fg=cyan>Hint:</> To isolate this specific scenario for debugging, run:');
                    $this->line("      <fg=white;options=bold>./vendor/bin/pest --filter=\"{$stalledName}\" {$stalledFile}</>");
                    $this->line('<fg=red;options=bold>=================================================================</>');
                } else {
                    $this->output->writeln('  <fg=red>✗</> <fg=default>Pre-Test Initialization</>'.str_repeat(' ', 65)."<fg=red>[TIMED OUT after {$idleTimeout}s]</>");

                    $this->newLine();
                    $this->line('<fg=red;options=bold>=================================================================</>');
                    $this->line("<fg=red;options=bold>Playwright server launch deadlock detected (no output for {$idleTimeout}s).</>");
                    $this->line('<fg=yellow;options=bold>Stalled Phase:</>    <fg=white;options=bold>Pre-test bootstrap / Playwright server launch</>');
                    $this->line('<fg=yellow;options=bold>Details:</>          <fg=white>Pest deadlocked before Test 1 began executing (0 bytes emitted).</>');
                    $this->newLine();
                    $this->line('<fg=yellow>Probable Cause:</>');
                    $this->line('  - Playwright WebSocket server failed to bind or emit "Listening on".');
                    $this->line('  - Windows TCP socket TIME_WAIT state collision from an immediate rapid re-run.');
                    $this->line('  - Ephemeral port exhaustion or orphaned background worker.');
                    $this->newLine();
                    $this->line('<fg=cyan>Remediation:</>');
                    $this->line('  - Wait ~15-30 seconds for the Windows socket TIME_WAIT cooldown to clear.');
                    $this->line('  - Ensure lingering test processes are purged:');
                    $this->line("      <fg=white;options=bold>php artisan rubberstamp:browser {$target} --force-kill-orphans</>");
                    $this->line('<fg=red;options=bold>=================================================================</>');
                }

                $exitCode = 124;
            } else {
                $exitCode = $process->getExitCode() ?? 1;
            }

            if (is_resource($testLogHandle)) {
                fclose($testLogHandle);
            }

            $screenshotsSource = base_path('tests'.DIRECTORY_SEPARATOR.'Browser'.DIRECTORY_SEPARATOR.'Screenshots');
            $this->copyDirectoryContents($screenshotsSource, base_path($runSnapshotDir));

            $this->generateAndRenderReport(
                $runName,
                base_path($testLog),
                base_path($runSnapshotDir),
                $docOptions,
                $timestamp,
            );

            if ($exitCode !== 0) {
                if ((bool) config('rubberstamp.cleanup_snapshots_on_failure', true)) {
                    $this->deleteDirectory(base_path($runSnapshotDir));
                }

                $this->newLine();
                $this->line('<fg=red;options=bold>=================================================================</>');
                $this->line("<fg=red;options=bold>Browser test failure detected (exit code: {$exitCode}).</>");

                if ((bool) config('rubberstamp.cleanup_snapshots_on_failure', true)) {
                    $this->line("<fg=yellow>Cleaned up snapshot directory:</> {$runSnapshotDir}");
                }
                $this->line("<fg=white>Log preserved:</> {$testLog}");
                $this->line('<fg=red;options=bold>=================================================================</>');

                return $exitCode;
            }

            $this->newLine();
            $this->line('<fg=green;options=bold>=================================================================</>');
            $this->line('<fg=green;options=bold>All browser tests passed successfully!</>');
            $this->line("<fg=white>Snapshots preserved in:</> {$runSnapshotDir}");
            $this->line("<fg=white>Log saved in:</> {$testLog}");
            $this->line('<fg=green;options=bold>=================================================================</>');

            return self::SUCCESS;
        } finally {
            $restoreHot();

            try {
                $sanitizer = new BrowserProcessSanitizer(base_path(), (string) config('app.name', 'laravel'));
                $sanitizer->cleanActiveSession();
                $lingering = $sanitizer->detectLingeringProcesses();

                if (! empty($lingering)) {
                    $sanitizer->killProcesses(array_column($lingering, 'pid'));
                }
                $sanitizer->cleanStaleTempFiles();
            } catch (Throwable) {
                // Suppress cleanup errors to avoid masking test exit status or exceptions
            }
        }
    }

    /**
     * Clean up temporary files before test execution.
     */
    private function cleanupPreTestArtifacts(): void
    {
        $sanitizer = new BrowserProcessSanitizer(base_path(), (string) config('app.name', 'laravel'));
        $sanitizer->cleanStaleTempFiles();

        $tempDir = base_path('vendor'.DIRECTORY_SEPARATOR.'pestphp'.DIRECTORY_SEPARATOR.'pest-plugin-browser'.DIRECTORY_SEPARATOR.'.temp');

        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }

        $screenshotsDir = base_path('tests'.DIRECTORY_SEPARATOR.'Browser'.DIRECTORY_SEPARATOR.'Screenshots');

        if (is_dir($screenshotsDir)) {
            $this->deleteDirectoryContents($screenshotsDir);
        }
    }

    /**
     * Parse unknown options from argv to forward directly to Pest.
     *
     * @return array<int, string>
     */
    private function resolveForwardedArguments(): array
    {
        $argv = $_SERVER['argv'] ?? [];
        $cmdIndex = false;

        foreach ($argv as $index => $token) {
            if ($token === 'rubberstamp:browser' || str_ends_with($token, 'rubberstamp:browser')) {
                $cmdIndex = $index;

                break;
            }
        }

        if ($cmdIndex === false) {
            return [];
        }

        $rawTokens = array_slice($argv, $cmdIndex + 1);
        $forwarded = [];
        $internalFlags = ['--doctor', '--check', '--skip-health-check', '--interactive', '-i', '--no-doc', '--selected-test-suite', '--force-kill-orphans', '--skip-orphan-check'];
        $internalPrefixes = [
            '--pest-path=',
            '--author=',
            '--sop=',
            '--document-id=',
            '--reviewed-by=',
            '--approved-by=',
            '--acknowledged-by=',
            '--selected-test-suite=',
            '--idle-timeout=',
        ];

        $target = $this->argument('target');

        foreach ($rawTokens as $token) {
            if (in_array($token, $internalFlags, true)) {
                continue;
            }

            $matchesPrefix = false;
            foreach ($internalPrefixes as $prefix) {
                if (str_starts_with($token, $prefix)) {
                    $matchesPrefix = true;

                    break;
                }
            }

            if ($matchesPrefix) {
                continue;
            }

            if ($target !== null && $token === $target) {
                continue;
            }

            $forwarded[] = $token;
        }

        return $forwarded;
    }

    /**
     * Ensure a directory exists.
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }

    /**
     * Recursively delete a directory and its contents.
     */
    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $this->deleteDirectoryContents($dir);
        @rmdir($dir);
    }

    /**
     * Recursively delete the contents of a directory without removing the directory itself.
     */
    private function deleteDirectoryContents(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
    }

    /**
     * Copy contents of source directory into target directory.
     */
    private function copyDirectoryContents(string $source, string $target): void
    {
        if (! is_dir($source)) {
            return;
        }

        $this->ensureDirectoryExists($target);

        $items = scandir($source);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = $source.DIRECTORY_SEPARATOR.$item;
            $dstPath = $target.DIRECTORY_SEPARATOR.$item;

            if (is_dir($srcPath)) {
                $this->copyDirectoryContents($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
    }
}
