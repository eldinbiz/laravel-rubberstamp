<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Console\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class PruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'rubberstamp:prune
        {--hours= : Prune artifacts older than the specified number of hours}
        {--days= : Prune artifacts older than the specified number of days}
        {--keep= : Keep the latest N test runs and prune older ones}
        {--type=all : Limit pruning to a specific artifact type (all, reports, logs, snapshots)}
        {--a|all : Prune all test artifacts regardless of age}
        {--dry-run : Simulate pruning and display matching files without deleting}
        {--force : Force the operation without confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Prune old test execution logs, corporate reports, and browser test snapshots.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rawType = $this->option('type');
        $type = is_string($rawType) && trim($rawType) !== '' ? strtolower(trim($rawType)) : 'all';
        $validTypes = ['all', 'reports', 'logs', 'snapshots'];

        if (! in_array($type, $validTypes, true)) {
            $this->error("Invalid --type [{$type}]. Allowed values are: ".implode(', ', $validTypes).'.');

            return self::FAILURE;
        }

        $reportsDir = (string) config('rubberstamp.reports_dir', 'doctest-reports');
        $testLogDir = (string) (config('rubberstamp.test_log_dir')
            ?: config('rubberstamp.pest_log_dir', 'doctest-reports/test-log'));
        $resultsDir = (string) config('rubberstamp.results_dir', 'doctest-reports/browser-test-log');

        $reportsPath = $this->resolvePath($reportsDir);
        $testLogPath = $this->resolvePath($testLogDir);
        $resultsPath = $this->resolvePath($resultsDir);

        $all = (bool) $this->option('all');
        $hours = $this->option('hours') !== null ? (int) $this->option('hours') : null;
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;
        $keep = $this->option('keep') !== null ? (int) $this->option('keep') : null;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $all && $hours === null && $days === null && $keep === null) {
            $days = (int) config('rubberstamp.prune_retention_days', 7);
        }

        $criteriaMsg = $this->buildCriteriaDescription($all, $hours, $days, $keep);
        $this->line("<fg=gray>{$criteriaMsg}</>");

        if ($type !== 'all') {
            $this->line("<fg=gray>Artifact type filter: [{$type}]</>");
        }

        $artifacts = $this->scanArtifacts($type, $reportsPath, $testLogPath, $resultsPath);

        if (empty($artifacts)) {
            $this->info('No test artifacts found matching the pruning criteria.');

            return self::SUCCESS;
        }

        $toPrune = $this->filterArtifactsForPruning($artifacts, $keep, $hours, $days, $all);

        if (empty($toPrune)) {
            $this->info('No test artifacts found matching the pruning criteria.');

            return self::SUCCESS;
        }

        $totalBytes = array_sum(array_column($toPrune, 'size'));
        $formattedTotal = $this->formatBytes($totalBytes);
        $count = count($toPrune);

        if ($dryRun) {
            $this->table(
                ['Type', 'Artifact', 'Timestamp', 'Size'],
                array_map(fn (array $item): array => [
                    $item['type'],
                    $item['name'],
                    date('Y-m-d H:i:s', $item['timestamp']),
                    $this->formatBytes($item['size']),
                ], $toPrune),
            );

            $this->warn("Dry run: [{$count}] test artifacts ({$formattedTotal}) would be pruned.");
            $this->line('No files were deleted.');

            return self::SUCCESS;
        }

        if (! $force) {
            if ($all) {
                if (! $this->confirm("Are you sure you want to prune ALL [{$count}] test artifacts ({$formattedTotal})?", false)) {
                    $this->info('Pruning cancelled.');

                    return self::SUCCESS;
                }

                if (! $this->confirm('This will permanently delete all test reports, logs, and browser snapshots. Do you wish to continue?', false)) {
                    $this->info('Pruning cancelled.');

                    return self::SUCCESS;
                }
            } else {
                if (! $this->confirm("Are you sure you want to prune [{$count}] test artifacts ({$formattedTotal})?", false)) {
                    $this->info('Pruning cancelled.');

                    return self::SUCCESS;
                }
            }
        }

        $deletedCount = 0;
        $reclaimedBytes = 0;

        foreach ($toPrune as $item) {
            $deleted = false;

            if ($item['is_dir']) {
                if (File::deleteDirectory($item['path'])) {
                    $deleted = true;
                }
            } else {
                if (File::delete($item['path'])) {
                    $deleted = true;
                }
            }

            if ($deleted) {
                $deletedCount++;
                $reclaimedBytes += $item['size'];
            }
        }

        $reclaimedFormatted = $this->formatBytes($reclaimedBytes);
        $this->info("Successfully pruned [{$deletedCount}] test artifacts, reclaiming {$reclaimedFormatted}.");

        return self::SUCCESS;
    }

    /**
     * Build human-readable criteria description.
     */
    private function buildCriteriaDescription(bool $all, ?int $hours, ?int $days, ?int $keep): string
    {
        if ($all) {
            return 'Retention criteria: all test artifacts regardless of age.';
        }

        $parts = [];

        if ($keep !== null) {
            $parts[] = "keeping latest {$keep} test run(s)";
        }

        if ($hours !== null) {
            $cutoff = date('Y-m-d H:i:s', time() - ($hours * 3600));
            $parts[] = "older than {$hours} hour(s) (cutoff: {$cutoff})";
        } elseif ($days !== null) {
            $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
            $parts[] = "older than {$days} day(s) (cutoff: {$cutoff})";
        }

        if (empty($parts)) {
            return 'Retention criteria: none.';
        }

        return 'Retention criteria: '.implode(' and ', $parts).'.';
    }

    /**
     * Scan candidate artifacts across reports, logs, and browser snapshots.
     *
     * @return array<int, array{
     *     type: string,
     *     name: string,
     *     path: string,
     *     is_dir: bool,
     *     timestamp: int,
     *     size: int,
     *     run_name: string
     * }>
     */
    private function scanArtifacts(string $type, string $reportsPath, string $testLogPath, string $resultsPath): array
    {
        $artifacts = [];

        if (($type === 'all' || $type === 'reports') && is_dir($reportsPath)) {
            $htmlFiles = glob(rtrim($reportsPath, '/\\').DIRECTORY_SEPARATOR.'*.html') ?: [];
            $mdFiles = glob(rtrim($reportsPath, '/\\').DIRECTORY_SEPARATOR.'*.md') ?: [];
            $reportFiles = array_merge($htmlFiles, $mdFiles);

            foreach ($reportFiles as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                $mtime = filemtime($file);
                $timestamp = $this->extractTimestamp($filename, $mtime !== false ? $mtime : time());
                $size = (int) filesize($file);

                $artifacts[] = [
                    'type' => 'reports',
                    'name' => basename($file),
                    'path' => $file,
                    'is_dir' => false,
                    'timestamp' => $timestamp,
                    'size' => $size,
                    'run_name' => $filename,
                ];
            }
        }

        if (($type === 'all' || $type === 'logs') && is_dir($testLogPath)) {
            $logFiles = glob(rtrim($testLogPath, '/\\').DIRECTORY_SEPARATOR.'*.log') ?: [];
            foreach ($logFiles as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                $mtime = filemtime($file);
                $timestamp = $this->extractTimestamp($filename, $mtime !== false ? $mtime : time());
                $size = (int) filesize($file);

                $artifacts[] = [
                    'type' => 'logs',
                    'name' => basename($file),
                    'path' => $file,
                    'is_dir' => false,
                    'timestamp' => $timestamp,
                    'size' => $size,
                    'run_name' => $filename,
                ];
            }
        }

        if (($type === 'all' || $type === 'logs') && is_dir($resultsPath)) {
            $browserLogFiles = glob(rtrim($resultsPath, '/\\').DIRECTORY_SEPARATOR.'*.log') ?: [];
            foreach ($browserLogFiles as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                $mtime = filemtime($file);
                $timestamp = $this->extractTimestamp($filename, $mtime !== false ? $mtime : time());
                $size = (int) filesize($file);

                $artifacts[] = [
                    'type' => 'logs',
                    'name' => basename($file),
                    'path' => $file,
                    'is_dir' => false,
                    'timestamp' => $timestamp,
                    'size' => $size,
                    'run_name' => $filename,
                ];
            }
        }

        if (($type === 'all' || $type === 'snapshots') && is_dir($resultsPath)) {
            $directories = glob(rtrim($resultsPath, '/\\').DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];
            foreach ($directories as $dir) {
                $dirname = basename($dir);
                $mtime = filemtime($dir);
                $timestamp = $this->extractTimestamp($dirname, $mtime !== false ? $mtime : time());
                $size = $this->getDirectorySize($dir);

                $artifacts[] = [
                    'type' => 'snapshots',
                    'name' => $dirname,
                    'path' => $dir,
                    'is_dir' => true,
                    'timestamp' => $timestamp,
                    'size' => $size,
                    'run_name' => $dirname,
                ];
            }
        }

        return $artifacts;
    }

    /**
     * Filter scanned artifacts based on retention criteria.
     *
     * @param array<int, array{
     *     type: string,
     *     name: string,
     *     path: string,
     *     is_dir: bool,
     *     timestamp: int,
     *     size: int,
     *     run_name: string
     * }> $artifacts
     * @return array<int, array{
     *     type: string,
     *     name: string,
     *     path: string,
     *     is_dir: bool,
     *     timestamp: int,
     *     size: int,
     *     run_name: string
     * }>
     */
    private function filterArtifactsForPruning(array $artifacts, ?int $keep, ?int $hours, ?int $days, bool $all = false): array
    {
        if ($all) {
            return $artifacts;
        }

        $protectedRuns = [];

        if ($keep !== null) {
            $runTimestamps = [];
            foreach ($artifacts as $item) {
                $runName = $item['run_name'];
                $runTimestamps[$runName] = max($runTimestamps[$runName] ?? 0, $item['timestamp']);
            }
            arsort($runTimestamps);
            $protectedRuns = array_slice(array_keys($runTimestamps), 0, max(0, $keep));
        }

        $cutoff = null;

        if ($hours !== null) {
            $cutoff = time() - ($hours * 3600);
        } elseif ($days !== null) {
            $cutoff = time() - ($days * 86400);
        }

        $toPrune = [];

        foreach ($artifacts as $item) {
            if ($keep !== null && in_array($item['run_name'], $protectedRuns, true)) {
                continue;
            }

            if ($cutoff !== null) {
                if ($item['timestamp'] < $cutoff) {
                    $toPrune[] = $item;
                }
            } else {
                $toPrune[] = $item;
            }
        }

        return $toPrune;
    }

    /**
     * Extract timestamp from artifact name or fallback.
     */
    private function extractTimestamp(string $name, int $fallback): int
    {
        if (preg_match('/(\d{8}_\d{6})/', $name, $matches)) {
            $date = DateTimeImmutable::createFromFormat('Ymd_His', $matches[1]);

            if ($date !== false) {
                return $date->getTimestamp();
            }
        }

        return $fallback;
    }

    /**
     * Compute total size in bytes of all files in a directory.
     */
    private function getDirectorySize(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }

        $size = 0;
        $files = File::allFiles($dir);

        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    /**
     * Format bytes to human-readable string.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    /**
     * Resolve path whether relative or absolute.
     */
    private function resolvePath(string $path): string
    {
        if (preg_match('#^([a-zA-Z]:[\\\\/]|/)#', $path)) {
            return $path;
        }

        return base_path($path);
    }
}
