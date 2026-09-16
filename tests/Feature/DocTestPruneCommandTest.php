<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'doctest_prune_test_'.uniqid();
    $this->reportsDir = $this->tempDir.DIRECTORY_SEPARATOR.'reports';
    $this->testLogDir = $this->tempDir.DIRECTORY_SEPARATOR.'test-log';
    $this->resultsDir = $this->tempDir.DIRECTORY_SEPARATOR.'browser-test-log';

    @mkdir($this->reportsDir, 0777, true);
    @mkdir($this->testLogDir, 0777, true);
    @mkdir($this->resultsDir, 0777, true);

    config()->set('rubberstamp.reports_dir', $this->reportsDir);
    config()->set('rubberstamp.test_log_dir', $this->testLogDir);
    config()->set('rubberstamp.results_dir', $this->resultsDir);
    config()->set('unit-tester-documenter.reports_dir', $this->reportsDir);
    config()->set('unit-tester-documenter.test_log_dir', $this->testLogDir);
    config()->set('unit-tester-documenter.results_dir', $this->resultsDir);
});

afterEach(function () {
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

it('registers the rubberstamp:prune artisan command', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('rubberstamp:prune')
        ->and($commands)->toHaveKey('doctest:prune');
});

it('registers the doctest:prune and test:prune aliases', function () {
    $command = Artisan::all()['rubberstamp:prune'];

    expect($command->getAliases())->toContain('doctest:prune')
        ->and($command->getAliases())->toContain('test:prune');
});

it('has the expected command definition and options', function () {
    $command = Artisan::all()['rubberstamp:prune'];

    expect($command->getDescription())->toContain('Prune old test execution logs')
        ->and($command->getDefinition()->hasOption('hours'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('days'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('keep'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('type'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('all'))->toBeTrue()
        ->and($command->getDefinition()->getOption('all')->getShortcut())->toBe('a')
        ->and($command->getDefinition()->hasOption('dry-run'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('force'))->toBeTrue()
        ->and($command->getDefinition()->getArgumentCount())->toBe(0);
});

it('rejects invalid --type option value', function () {
    $this->artisan('rubberstamp:prune', ['--type' => 'invalid_type'])
        ->expectsOutputToContain('Invalid --type [invalid_type]')
        ->assertExitCode(1);
});

it('exits successfully when no artifacts are found to prune and displays criteria', function () {
    $this->artisan('rubberstamp:prune', ['--force' => true])
        ->expectsOutputToContain('Retention criteria: older than 7 day(s)')
        ->expectsOutputToContain('No test artifacts found matching the pruning criteria')
        ->assertExitCode(0);
});

it('previews files in --dry-run without deleting them', function () {
    $reportFile = $this->reportsDir.DIRECTORY_SEPARATOR.'test_20200101_120000.html';
    $logFile = $this->testLogDir.DIRECTORY_SEPARATOR.'test_20200101_120000.log';
    file_put_contents($reportFile, '<html>Report</html>');
    file_put_contents($logFile, 'Test log content');

    $this->artisan('rubberstamp:prune', [
        '--days' => 1,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Dry run: [2] test artifacts')
        ->expectsOutputToContain('No files were deleted')
        ->assertExitCode(0);

    expect(file_exists($reportFile))->toBeTrue()
        ->and(file_exists($logFile))->toBeTrue();
});

it('cancels pruning when confirmation is rejected without --force', function () {
    $reportFile = $this->reportsDir.DIRECTORY_SEPARATOR.'test_20200101_120000.html';
    file_put_contents($reportFile, '<html>Report</html>');

    $size = strlen('<html>Report</html>');

    $this->artisan('rubberstamp:prune', ['--days' => 1])
        ->expectsConfirmation("Are you sure you want to prune [1] test artifacts ({$size} B)?", 'no')
        ->expectsOutputToContain('Pruning cancelled')
        ->assertExitCode(0);

    expect(file_exists($reportFile))->toBeTrue();
});

it('prunes artifacts older than specified days when confirmed with --force', function () {
    // Old file (from 2020)
    $oldReport = $this->reportsDir.DIRECTORY_SEPARATOR.'test_20200101_120000.html';
    $oldLog = $this->testLogDir.DIRECTORY_SEPARATOR.'test_20200101_120000.log';
    file_put_contents($oldReport, '<html>Old Report</html>');
    file_put_contents($oldLog, 'Old Log');

    // Recent file (now timestamp in name)
    $recentTimestamp = now()->format('Ymd_His');
    $recentReport = $this->reportsDir.DIRECTORY_SEPARATOR."test_{$recentTimestamp}.html";
    file_put_contents($recentReport, '<html>Recent Report</html>');

    $this->artisan('rubberstamp:prune', [
        '--days' => 1,
        '--force' => true,
    ])
        ->expectsOutputToContain('Successfully pruned [2] test artifacts')
        ->assertExitCode(0);

    expect(file_exists($oldReport))->toBeFalse()
        ->and(file_exists($oldLog))->toBeFalse()
        ->and(file_exists($recentReport))->toBeTrue();
});

it('prunes artifacts older than specified hours with --hours', function () {
    $oldReport = $this->reportsDir.DIRECTORY_SEPARATOR.'test_20200101_120000.html';
    file_put_contents($oldReport, '<html>Old Report</html>');

    $this->artisan('rubberstamp:prune', [
        '--hours' => 2,
        '--force' => true,
    ])
        ->expectsOutputToContain('Successfully pruned [1] test artifacts')
        ->assertExitCode(0);

    expect(file_exists($oldReport))->toBeFalse();
});

it('keeps latest N runs when --keep is specified', function () {
    // Run 1: Oldest
    $run1Report = $this->reportsDir.DIRECTORY_SEPARATOR.'test_20240101_100000.html';
    $run1Log = $this->testLogDir.DIRECTORY_SEPARATOR.'test_20240101_100000.log';
    file_put_contents($run1Report, 'Report 1');
    file_put_contents($run1Log, 'Log 1');

    // Run 2: Middle
    $run2Report = $this->reportsDir.DIRECTORY_SEPARATOR.'test_20240201_100000.html';
    file_put_contents($run2Report, 'Report 2');

    // Run 3: Newest
    $run3Report = $this->reportsDir.DIRECTORY_SEPARATOR.'test_20240301_100000.html';
    file_put_contents($run3Report, 'Report 3');

    // Keep latest 2 runs (run 3 and run 2), prune run 1
    $this->artisan('rubberstamp:prune', [
        '--keep' => 2,
        '--force' => true,
    ])
        ->expectsOutputToContain('Successfully pruned [2] test artifacts')
        ->assertExitCode(0);

    expect(file_exists($run1Report))->toBeFalse()
        ->and(file_exists($run1Log))->toBeFalse()
        ->and(file_exists($run2Report))->toBeTrue()
        ->and(file_exists($run3Report))->toBeTrue();
});

it('filters pruning by artifact --type', function () {
    $report = $this->reportsDir.DIRECTORY_SEPARATOR.'test_20200101_100000.html';
    $log = $this->testLogDir.DIRECTORY_SEPARATOR.'test_20200101_100000.log';
    $snapshotDir = $this->resultsDir.DIRECTORY_SEPARATOR.'browser_test_20200101_100000';
    @mkdir($snapshotDir, 0777, true);
    file_put_contents($snapshotDir.DIRECTORY_SEPARATOR.'step1.png', 'fake image data');

    file_put_contents($report, 'Report');
    file_put_contents($log, 'Log');

    // Only prune reports
    $this->artisan('rubberstamp:prune', [
        '--type' => 'reports',
        '--days' => 1,
        '--force' => true,
    ])
        ->expectsOutputToContain('Successfully pruned [1] test artifacts')
        ->assertExitCode(0);

    expect(file_exists($report))->toBeFalse()
        ->and(file_exists($log))->toBeTrue()
        ->and(is_dir($snapshotDir))->toBeTrue();
});

it('prunes snapshot directories recursively when --type=snapshots is selected', function () {
    $snapshotDir = $this->resultsDir.DIRECTORY_SEPARATOR.'browser_test_20200101_100000';
    @mkdir($snapshotDir, 0777, true);
    file_put_contents($snapshotDir.DIRECTORY_SEPARATOR.'screenshot.png', 'fake image');

    $this->artisan('rubberstamp:prune', [
        '--type' => 'snapshots',
        '--days' => 1,
        '--force' => true,
    ])
        ->expectsOutputToContain('Successfully pruned [1] test artifacts')
        ->assertExitCode(0);

    expect(is_dir($snapshotDir))->toBeFalse();
});

it('prunes both html and markdown reports under reports directory', function () {
    $htmlReport = $this->reportsDir.DIRECTORY_SEPARATOR.'test_20200101_100000.html';
    $mdReport = $this->reportsDir.DIRECTORY_SEPARATOR.'test_20200101_100000.md';
    file_put_contents($htmlReport, '<html>Report</html>');
    file_put_contents($mdReport, '# Markdown Report');

    $this->artisan('rubberstamp:prune', [
        '--type' => 'reports',
        '--days' => 1,
        '--force' => true,
    ])
        ->expectsOutputToContain('Successfully pruned [2] test artifacts')
        ->assertExitCode(0);

    expect(file_exists($htmlReport))->toBeFalse()
        ->and(file_exists($mdReport))->toBeFalse();
});

it('prunes all artifacts with --all after double confirmation', function () {
    $report = $this->reportsDir.DIRECTORY_SEPARATOR.'test_recent.html';
    $log = $this->testLogDir.DIRECTORY_SEPARATOR.'test_recent.log';
    file_put_contents($report, 'Recent report');
    file_put_contents($log, 'Recent log');

    $this->artisan('rubberstamp:prune', ['--all' => true])
        ->expectsOutputToContain('Retention criteria: all test artifacts regardless of age')
        ->expectsConfirmation('Are you sure you want to prune ALL [2] test artifacts (23 B)?', 'yes')
        ->expectsConfirmation('This will permanently delete all test reports, logs, and browser snapshots. Do you wish to continue?', 'yes')
        ->expectsOutputToContain('Successfully pruned [2] test artifacts')
        ->assertExitCode(0);

    expect(file_exists($report))->toBeFalse()
        ->and(file_exists($log))->toBeFalse();
});

it('cancels --all pruning if first confirmation prompt is rejected', function () {
    $report = $this->reportsDir.DIRECTORY_SEPARATOR.'test_recent.html';
    file_put_contents($report, 'Recent report');

    $this->artisan('rubberstamp:prune', ['--all' => true])
        ->expectsConfirmation('Are you sure you want to prune ALL [1] test artifacts (13 B)?', 'no')
        ->expectsOutputToContain('Pruning cancelled')
        ->assertExitCode(0);

    expect(file_exists($report))->toBeTrue();
});

it('cancels --all pruning if second confirmation prompt is rejected', function () {
    $report = $this->reportsDir.DIRECTORY_SEPARATOR.'test_recent.html';
    file_put_contents($report, 'Recent report');

    $this->artisan('rubberstamp:prune', ['--all' => true])
        ->expectsConfirmation('Are you sure you want to prune ALL [1] test artifacts (13 B)?', 'yes')
        ->expectsConfirmation('This will permanently delete all test reports, logs, and browser snapshots. Do you wish to continue?', 'no')
        ->expectsOutputToContain('Pruning cancelled')
        ->assertExitCode(0);

    expect(file_exists($report))->toBeTrue();
});

it('prunes all artifacts immediately with --all and --force without confirmation prompts', function () {
    $report = $this->reportsDir.DIRECTORY_SEPARATOR.'test_recent.html';
    $log = $this->testLogDir.DIRECTORY_SEPARATOR.'test_recent.log';
    file_put_contents($report, 'Recent report');
    file_put_contents($log, 'Recent log');

    $this->artisan('rubberstamp:prune', [
        '--all' => true,
        '--force' => true,
    ])
        ->expectsOutputToContain('Retention criteria: all test artifacts regardless of age')
        ->expectsOutputToContain('Successfully pruned [2] test artifacts')
        ->assertExitCode(0);

    expect(file_exists($report))->toBeFalse()
        ->and(file_exists($log))->toBeFalse();
});
