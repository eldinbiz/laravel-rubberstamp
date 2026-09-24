<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Console\Commands;

use Eldinbiz\RubberStamp\Console\Concerns\InteractsWithRubberStampOptions;
use Eldinbiz\RubberStamp\Support\AuditMetadataResolver;
use Eldinbiz\RubberStamp\Support\BrowserEnvironmentDoctor;
use Eldinbiz\RubberStamp\Support\BrowserProcessSanitizer;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

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
            return self::SUCCESS;
        }

        $this->renderProcessWarningTable($processes, $appName);

        $forceKill = (bool) $this->option('force-kill-orphans');
        $isInteractive = $this->input->isInteractive();

        if ($forceKill || (! $isInteractive)) {
            $pids = array_column($processes, 'pid');
            $result = $sanitizer->killProcesses($pids);
            $sanitizer->cleanStaleTempFiles();

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

            $exitCode = $process->run(function (string $type, string $buffer) use ($testLogHandle): void {
                $this->output->write($buffer);

                if (is_resource($testLogHandle)) {
                    fwrite($testLogHandle, $buffer);
                }
            });

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
        }
    }

    /**
     * Clean up temporary files before test execution.
     */
    private function cleanupPreTestArtifacts(): void
    {
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
