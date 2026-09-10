<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class TestFeaturesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'doctest:features
        {target? : Optional test file or directory to execute (defaults to running all test suites via phpunit.xml)}
        {--pest-path= : Custom path to Pest binary}
        {--skip-clear : Skip config:clear and view:clear before running tests}';

    /**
     * Alternative aliases for the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['doctest:feature', 'test:features', 'test:feature'];

    /**
     * The console command description.
     */
    protected $description = 'Run Pest tests with cache clearing, memory optimization, and timestamped log documentation.';

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
        $timestamp = now()->format('Ymd_His');
        $runName = "test_{$timestamp}";

        $pestLogDir = (string) config('unit-tester-documenter.pest_log_dir', '.pest');
        $pestLog = $pestLogDir.DIRECTORY_SEPARATOR.$runName.'.log';

        $this->ensureDirectoryExists(base_path($pestLogDir));

        if (! $this->option('skip-clear')) {
            $this->callSilently('config:clear');
            $this->callSilently('view:clear');
        }

        $pestBinary = $this->resolvePestBinary();

        if ($pestBinary === null || ! file_exists($pestBinary)) {
            $this->error('Pest executable could not be found. Please ensure vendor/bin/pest exists.');

            return self::FAILURE;
        }

        $memoryLimit = (string) config('unit-tester-documenter.memory_limit', '1024M');
        $forwardedArgs = $this->resolveForwardedArguments();

        $rawTarget = $this->argument('target');
        $target = is_string($rawTarget) && $rawTarget !== '' ? $rawTarget : null;

        $command = [
            PHP_BINARY,
            '-d', "memory_limit={$memoryLimit}",
            '-d', 'output_buffering=0',
            $pestBinary,
        ];

        if ($target !== null) {
            $command[] = $target;
        }

        $command[] = '--colors=always';

        foreach ($forwardedArgs as $arg) {
            $command[] = $arg;
        }

        $pestLogHandle = fopen(base_path($pestLog), 'wb');

        $process = new Process(
            command: $command,
            cwd: base_path(),
            env: $_ENV,
            timeout: null,
        );

        $this->info("Starting Pest test run: {$runName}");

        if ($target !== null) {
            $this->line("<fg=gray>Target:</> {$target}");
        }

        $this->newLine();

        $exitCode = $process->run(function (string $type, string $buffer) use ($pestLogHandle): void {
            $this->output->write($buffer);

            if (is_resource($pestLogHandle)) {
                fwrite($pestLogHandle, $buffer);
            }
        });

        if (is_resource($pestLogHandle)) {
            fclose($pestLogHandle);
        }

        if ($exitCode !== 0) {
            $this->newLine();
            $this->line('<fg=red;options=bold>=================================================================</>');
            $this->line("<fg=red;options=bold>Test failure detected (exit code: {$exitCode}).</>");
            $this->line("<fg=white>Log preserved:</> {$pestLog}");
            $this->line('<fg=red;options=bold>=================================================================</>');

            return $exitCode;
        }

        $this->newLine();
        $this->line('<fg=green;options=bold>=================================================================</>');
        $this->line('<fg=green;options=bold>All tests passed successfully!</>');
        $this->line("<fg=white>Log saved in:</> {$pestLog}");
        $this->line('<fg=green;options=bold>=================================================================</>');

        return self::SUCCESS;
    }

    /**
     * Resolve the Pest binary path.
     */
    private function resolvePestBinary(): ?string
    {
        $customPath = $this->option('pest-path')
            ?: config('unit-tester-documenter.pest_binary');

        if ($customPath !== null && is_string($customPath)) {
            return file_exists($customPath) ? $customPath : null;
        }

        $candidates = [
            base_path('vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest'),
            base_path('vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest.bat'),
            base_path('vendor'.DIRECTORY_SEPARATOR.'pestphp'.DIRECTORY_SEPARATOR.'pest'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest'),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Parse unknown options and arguments from argv to forward directly to Pest.
     *
     * @return array<int, string>
     */
    private function resolveForwardedArguments(): array
    {
        $argv = $_SERVER['argv'] ?? [];
        $cmdIndex = false;

        foreach ($argv as $index => $token) {
            if (
                $token === 'doctest:features' || str_ends_with($token, 'doctest:features') ||
                $token === 'doctest:feature' || str_ends_with($token, 'doctest:feature') ||
                $token === 'test:features' || str_ends_with($token, 'test:features') ||
                $token === 'test:feature' || str_ends_with($token, 'test:feature')
            ) {
                $cmdIndex = $index;

                break;
            }
        }

        if ($cmdIndex === false) {
            return [];
        }

        $rawTokens = array_slice($argv, $cmdIndex + 1);
        $forwarded = [];
        $internalFlags = ['--skip-clear'];

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
}
