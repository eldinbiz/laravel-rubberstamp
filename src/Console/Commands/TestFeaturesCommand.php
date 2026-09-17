<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Console\Commands;

use Eldinbiz\RubberStamp\Console\Concerns\InteractsWithRubberStampOptions;
use Eldinbiz\RubberStamp\Support\AuditMetadataResolver;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class TestFeaturesCommand extends Command
{
    use InteractsWithRubberStampOptions;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'rubberstamp:features
        {target? : Optional test file or directory to execute (defaults to running all test suites via phpunit.xml)}
        {--pest-path= : Custom path to Pest binary}
        {--skip-clear : Skip config:clear and view:clear before running tests}
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

        $resolver = new AuditMetadataResolver(base_path());
        $docOptions = $this->resolveDocOptions($resolver);

        $testLogDir = (string) (config('rubberstamp.test_log_dir')
            ?: config('rubberstamp.pest_log_dir', 'doctest-reports/test-log'));
        $testLog = $testLogDir.DIRECTORY_SEPARATOR.$runName.'.log';

        $this->ensureDirectoryExists(base_path($testLogDir));

        if (! $this->option('skip-clear')) {
            $this->callSilently('config:clear');
            $this->callSilently('view:clear');
        }

        $pestBinary = $this->resolvePestBinary();

        if ($pestBinary === null || ! file_exists($pestBinary)) {
            $this->error('Pest executable could not be found. Please ensure vendor/bin/pest exists.');

            return self::FAILURE;
        }

        $memoryLimit = (string) config('rubberstamp.memory_limit', '1024M');
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

        $testLogHandle = fopen(base_path($testLog), 'wb');

        $this->clearEnv();

        $process = new Process(
            command: $command,
            cwd: base_path(),
            env: null,
            timeout: null,
        );

        $this->info("Starting Pest test run: {$runName}");

        if ($target !== null) {
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

        $this->generateAndRenderReport(
            $runName,
            base_path($testLog),
            null,
            $docOptions,
            $timestamp,
        );

        if ($exitCode !== 0) {
            $this->newLine();
            $this->line('<fg=red;options=bold>=================================================================</>');
            $this->line("<fg=red;options=bold>Test failure detected (exit code: {$exitCode}).</>");
            $this->line("<fg=white>Log preserved:</> {$testLog}");
            $this->line('<fg=red;options=bold>=================================================================</>');

            return $exitCode;
        }

        $this->newLine();
        $this->line('<fg=green;options=bold>=================================================================</>');
        $this->line('<fg=green;options=bold>All tests passed successfully!</>');
        $this->line("<fg=white>Log saved in:</> {$testLog}");
        $this->line('<fg=green;options=bold>=================================================================</>');

        return self::SUCCESS;
    }

    /**
     * Resolve the Pest binary path.
     */
    private function resolvePestBinary(): ?string
    {
        $customPath = $this->option('pest-path')
            ?: config('rubberstamp.pest_binary');

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
            if ($token === 'rubberstamp:features' || str_ends_with($token, 'rubberstamp:features')) {
                $cmdIndex = $index;

                break;
            }
        }

        if ($cmdIndex === false) {
            return [];
        }

        $rawTokens = array_slice($argv, $cmdIndex + 1);
        $forwarded = [];
        $internalFlags = ['--skip-clear', '--interactive', '-i', '--no-doc'];
        $internalPrefixes = [
            '--pest-path=',
            '--author=',
            '--sop=',
            '--document-id=',
            '--reviewed-by=',
            '--approved-by=',
            '--acknowledged-by=',
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
}
