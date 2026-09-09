<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use UnitTesterDocumenter\UnitTesterDocumenter\Support\BrowserEnvironmentDoctor;

final class TestBrowserCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:browser
        {target? : The test file or directory to execute (defaults to tests/Browser)}
        {--doctor : Run environment and Playwright health checks only}
        {--check : Alias for --doctor}
        {--skip-health-check : Skip pre-flight environment health check}
        {--pest-path= : Custom path to Pest binary}';

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
            (string) config('unit-tester-documenter.chromium_binary') ?: null,
        );

        if ($this->option('doctor') || $this->option('check')) {
            return $this->runDoctor($doctor);
        }

        if (! $this->option('skip-health-check')) {
            $preflightPassed = $this->runPreflightCheck($doctor);

            if (! $preflightPassed) {
                return self::FAILURE;
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
     * Run a quick preflight check before starting tests.
     */
    private function runPreflightCheck(BrowserEnvironmentDoctor $doctor): bool
    {
        $checks = $doctor->checkAll();

        if ($doctor->hasFailures($checks)) {
            $this->newLine();
            $this->error('Pre-flight health check detected missing browser testing dependencies:');
            $this->newLine();

            foreach ($checks as $check) {
                if ($check['status'] === BrowserEnvironmentDoctor::STATUS_FAILED) {
                    $this->line("  <fg=red>[FAIL]</> <options=bold>{$check['name']}</>: {$check['message']}");

                    if ($check['suggestion'] !== null) {
                        $this->line("         <fg=yellow>Remedy:</> {$check['suggestion']}");
                    }
                }
            }

            $this->newLine();
            $this->line('Run <comment>php artisan test:browser --doctor</comment> for detailed diagnostics,');
            $this->line('or use <comment>--skip-health-check</comment> to bypass this verification.');
            $this->newLine();

            return false;
        }

        return true;
    }

    /**
     * Execute Pest browser tests with logging, hot backup, and snapshot capture.
     */
    private function executeBrowserTests(BrowserEnvironmentDoctor $doctor): int
    {
        $timestamp = now()->format('Ymd_His');
        $runName = "browser_test_{$timestamp}";

        $resultsDir = (string) config('unit-tester-documenter.results_dir', 'browser-test-results');
        $pestLogDir = (string) config('unit-tester-documenter.pest_log_dir', '.pest');

        $runSnapshotDir = $resultsDir.DIRECTORY_SEPARATOR.$runName;
        $pestLog = $pestLogDir.DIRECTORY_SEPARATOR.$runName.'.log';
        $runResultsLog = $resultsDir.DIRECTORY_SEPARATOR.$runName.'.log';

        $this->ensureDirectoryExists(base_path($pestLogDir));
        $this->ensureDirectoryExists(base_path($resultsDir));
        $this->ensureDirectoryExists(base_path($runSnapshotDir));

        putenv('BROWSER_SNAPSHOT_DIR='.base_path($runSnapshotDir));
        $_ENV['BROWSER_SNAPSHOT_DIR'] = base_path($runSnapshotDir);
        $_SERVER['BROWSER_SNAPSHOT_DIR'] = base_path($runSnapshotDir);

        $chromiumPath = $doctor->detectChromiumBinary();

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

            $this->callSilently('config:clear');
            $this->callSilently('view:clear');

            $rawTarget = $this->argument('target');
            $target = is_string($rawTarget) && $rawTarget !== '' ? $rawTarget : 'tests/Browser';
            $pestBinary = $this->option('pest-path')
                ?: config('unit-tester-documenter.pest_binary')
                ?: $doctor->detectPestBinary();

            if ($pestBinary === null || ! file_exists((string) $pestBinary)) {
                $this->error('Pest executable could not be found. Please ensure vendor/bin/pest exists.');

                return self::FAILURE;
            }

            $memoryLimit = (string) config('unit-tester-documenter.memory_limit', '1024M');
            $forwardedArgs = $this->resolveForwardedArguments();

            $command = array_merge(
                [
                    PHP_BINARY,
                    '-d', "memory_limit={$memoryLimit}",
                    '-d', 'output_buffering=0',
                    (string) $pestBinary,
                    $target,
                    '--colors=always',
                ],
                $forwardedArgs,
            );

            $pestLogHandle = fopen(base_path($pestLog), 'wb');
            $runResultsLogHandle = fopen(base_path($runResultsLog), 'wb');

            $process = new Process(
                command: $command,
                cwd: base_path(),
                env: array_merge($_ENV, [
                    'BROWSER_SNAPSHOT_DIR' => base_path($runSnapshotDir),
                ]),
                timeout: null,
            );

            $this->info("Starting Pest Browser run: {$runName}");
            $this->line("<fg=gray>Target:</> {$target}");
            $this->newLine();

            $exitCode = $process->run(function (string $type, string $buffer) use ($pestLogHandle, $runResultsLogHandle): void {
                $this->output->write($buffer);

                if (is_resource($pestLogHandle)) {
                    fwrite($pestLogHandle, $buffer);
                }

                if (is_resource($runResultsLogHandle)) {
                    fwrite($runResultsLogHandle, $buffer);
                }
            });

            if (is_resource($pestLogHandle)) {
                fclose($pestLogHandle);
            }

            if (is_resource($runResultsLogHandle)) {
                fclose($runResultsLogHandle);
            }

            $screenshotsSource = base_path('tests'.DIRECTORY_SEPARATOR.'Browser'.DIRECTORY_SEPARATOR.'Screenshots');
            $this->copyDirectoryContents($screenshotsSource, base_path($runSnapshotDir));

            if ($exitCode !== 0) {
                if ((bool) config('unit-tester-documenter.cleanup_snapshots_on_failure', true)) {
                    $this->deleteDirectory(base_path($runSnapshotDir));
                }

                $this->newLine();
                $this->line('<fg=red;options=bold>=================================================================</>');
                $this->line("<fg=red;options=bold>Browser test failure detected (exit code: {$exitCode}).</>");

                if ((bool) config('unit-tester-documenter.cleanup_snapshots_on_failure', true)) {
                    $this->line("<fg=yellow>Cleaned up snapshot directory:</> {$runSnapshotDir}");
                }
                $this->line("<fg=white>Logs preserved:</> {$pestLog} and {$runResultsLog}");
                $this->line('<fg=red;options=bold>=================================================================</>');

                return $exitCode;
            }

            $this->newLine();
            $this->line('<fg=green;options=bold>=================================================================</>');
            $this->line('<fg=green;options=bold>All browser tests passed successfully!</>');
            $this->line("<fg=white>Snapshots preserved in:</> {$runSnapshotDir}");
            $this->line("<fg=white>Logs saved in:</> {$pestLog} and {$runResultsLog}");
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
            if ($token === 'test:browser' || str_ends_with($token, 'test:browser')) {
                $cmdIndex = $index;

                break;
            }
        }

        if ($cmdIndex === false) {
            return [];
        }

        $rawTokens = array_slice($argv, $cmdIndex + 1);
        $forwarded = [];
        $internalFlags = ['--doctor', '--check', '--skip-health-check'];

        $target = $this->argument('target');

        foreach ($rawTokens as $token) {
            if (in_array($token, $internalFlags, true)) {
                continue;
            }

            if (str_starts_with($token, '--pest-path=')) {
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
