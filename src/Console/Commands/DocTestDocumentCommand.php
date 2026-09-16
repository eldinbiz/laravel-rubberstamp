<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Console\Commands;

use Eldinbiz\RubberStamp\Console\Concerns\InteractsWithDocTestOptions;
use Eldinbiz\RubberStamp\Support\AuditMetadataResolver;
use Illuminate\Console\Command;

final class DocTestDocumentCommand extends Command
{
    use InteractsWithDocTestOptions;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'rubberstamp:document
        {run? : Specific run name or log file to document (defaults to latest test run)}
        {--author= : Author/tester name (defaults to git config user.name)}
        {--sop= : SOP policy or RFC ticket code (defaults to N/A)}
        {--document-id= : Custom Document ID prefix (defaults to DOC-TEST-)}
        {--reviewed-by= : Pipe-separated reviewers/roles (e.g. "Mr Smith,QA Engineer|QA Head")}
        {--approved-by= : Pipe-separated approvers/roles (e.g. "Jane,QA Lead|Technical Lead")}
        {--acknowledged-by= : Pipe-separated acknowledgers/roles (e.g. "Bob,Project Manager|Product Owner")}
        {--i|interactive : Interactively configure audit metadata and sign-off approval sheet}
        {--no-doc : Dry run without writing report files}';

    /**
     * Alternative aliases for the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['doctest:document', 'test:document'];

    /**
     * The console command description.
     */
    protected $description = 'Generate or regenerate corporate HTML test documentation from existing test logs.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $testLogDir = (string) (config('rubberstamp.test_log_dir')
            ?: config('rubberstamp.pest_log_dir')
            ?: config('unit-tester-documenter.test_log_dir')
            ?: config('unit-tester-documenter.pest_log_dir', 'doctest-reports/test-log'));
        $resultsDir = (string) (config('rubberstamp.results_dir')
            ?: config('unit-tester-documenter.results_dir', 'doctest-reports/browser-test-log'));

        $resolved = $this->resolveTargetRun($testLogDir, $resultsDir);

        if ($resolved === null) {
            $this->error('No test runs found to document. Please run tests first using [php artisan rubberstamp:features] or [php artisan rubberstamp:browser].');

            return self::FAILURE;
        }

        [$runName, $logPath, $timestamp] = $resolved;

        $snapshotDir = base_path($resultsDir.DIRECTORY_SEPARATOR.$runName);

        if (! is_dir($snapshotDir)) {
            $snapshotDir = null;
        }

        $resolver = new AuditMetadataResolver(base_path());
        $docOptions = $this->resolveDocOptions($resolver);

        $this->info("Compiling corporate documentation for test run: {$runName}");
        $this->line("<fg=gray>Source Log:</> {$logPath}");

        $report = $this->generateAndRenderReport(
            $runName,
            $logPath,
            $snapshotDir,
            $docOptions,
            $timestamp,
        );

        return $report !== null ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Resolve target run name, log file path, and timestamp.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function resolveTargetRun(string $testLogDir, string $resultsDir): ?array
    {
        $target = $this->argument('run');

        if (is_string($target) && trim($target) !== '') {
            $raw = trim($target);
            $cleanName = pathinfo($raw, PATHINFO_FILENAME);

            $directPath = base_path($raw);

            if (file_exists($directPath)) {
                return [$cleanName, $directPath, $this->extractTimestamp($cleanName)];
            }

            $inBrowserLog = base_path($resultsDir.DIRECTORY_SEPARATOR.$cleanName.'.log');

            if (file_exists($inBrowserLog)) {
                return [$cleanName, $inBrowserLog, $this->extractTimestamp($cleanName)];
            }

            $inTestLog = base_path($testLogDir.DIRECTORY_SEPARATOR.$cleanName.'.log');

            if (file_exists($inTestLog)) {
                return [$cleanName, $inTestLog, $this->extractTimestamp($cleanName)];
            }

            $this->error("Specified run log could not be found: [{$raw}]");

            return null;
        }

        $searchDirs = array_filter([base_path($testLogDir), base_path($resultsDir)], 'is_dir');

        if (empty($searchDirs)) {
            return null;
        }

        $files = [];

        foreach ($searchDirs as $dir) {
            $found = glob($dir.DIRECTORY_SEPARATOR.'*.log');

            if ($found !== false && ! empty($found)) {
                $files = array_merge($files, $found);
            }
        }

        if (empty($files)) {
            return null;
        }

        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        $latest = $files[0];
        $runName = pathinfo($latest, PATHINFO_FILENAME);

        return [$runName, $latest, $this->extractTimestamp($runName)];
    }

    /**
     * Extract timestamp from run name or fallback to now.
     */
    private function extractTimestamp(string $runName): string
    {
        if (preg_match('/(\d{8}_\d{6})/', $runName, $m)) {
            return $m[1];
        }

        return now()->format('Ymd_His');
    }
}
