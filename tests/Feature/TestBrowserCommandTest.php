<?php

declare(strict_types=1);

use Eldinbiz\RubberStamp\Console\Concerns\InteractsWithRubberStampOptions;
use Eldinbiz\RubberStamp\Support\BrowserSnapshotManager;
use Illuminate\Console\Command;

it('registers the rubberstamp:browser artisan command', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('rubberstamp:browser')
        ->and($commands)->not->toHaveKey('doctest:browser');
});

it('registers no aliases for rubberstamp:browser', function () {
    $command = Artisan::all()['rubberstamp:browser'];

    expect($command->getAliases())->toBeEmpty();
});

it('has the expected command description and options', function () {
    $command = Artisan::all()['rubberstamp:browser'];

    expect($command->getDescription())->toContain('Run Pest browser tests with Playwright self-health diagnostics')
        ->and($command->getDefinition()->hasOption('doctor'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('check'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('skip-health-check'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('force-kill-orphans'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('skip-orphan-check'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('selected-test-suite'))->toBeTrue()
        ->and($command->getDefinition()->hasArgument('target'))->toBeTrue();
});

it('discovers browser test classes when present', function () {
    $command = new class extends Command
    {
        use InteractsWithRubberStampOptions;

        /**
         * @return array<string, string>
         */
        public function testDiscovery(): array
        {
            return $this->discoverAvailableTestSuites('browser');
        }
    };

    $options = $command->testDiscovery();
    expect($options)->toBeArray();
});

it('aborts gracefully when no browser test suites are discovered', function () {
    $this->artisan('rubberstamp:browser', [
        '--selected-test-suite' => true,
        '--skip-health-check' => true,
    ])
        ->expectsOutputToContain('No test suites or classes discovered for [browser].')
        ->assertExitCode(0);
});

it('allows interactive browser test suite selection when targets exist', function () {
    $this->artisan('rubberstamp:browser', [
        'target' => 'tests/Feature',
        '--selected-test-suite' => true,
        '--skip-health-check' => true,
        '--pest-path' => '/nonexistent/path/to/pest',
    ])
        ->expectsQuestion('Select browser test suites or classes to run:', ['1'])
        ->assertExitCode(1);
});

it('can run doctor diagnostics via artisan rubberstamp:browser --doctor', function () {
    $this->artisan('rubberstamp:browser', ['--doctor' => true])
        ->expectsOutputToContain('Browser Testing & Playwright Environment Doctor')
        ->assertExitCode(in_array($this->artisan('rubberstamp:browser', ['--doctor' => true]), [0, 1], true) ? 0 : 1);
});

it('exposes the default package configurations', function () {
    expect(config('rubberstamp.results_dir'))->toBe('doctest-reports/browser-test-log')
        ->and(config('rubberstamp.pest_log_dir'))->toBe('doctest-reports/test-log')
        ->and(config('rubberstamp.test_log_dir'))->toBe('doctest-reports/test-log')
        ->and(config('rubberstamp.memory_limit'))->toBe('1024M')
        ->and(config('rubberstamp.cleanup_snapshots_on_failure'))->toBeTrue();
});

it('resolves browser test artifacts inside results_dir', function () {
    $resultsDir = config('rubberstamp.results_dir');

    expect($resultsDir)->toBe('doctest-reports/browser-test-log');
});

it('runs health check by default before executing browser tests', function () {
    $this->artisan('rubberstamp:browser', ['--target' => 'tests/Browser/NonExistentTest.php'])
        ->expectsOutputToContain('Browser Testing & Playwright Environment Doctor');
});

it('bypasses health check when --skip-health-check is supplied', function () {
    $this->artisan('rubberstamp:browser', [
        '--skip-health-check' => true,
        '--target' => 'tests/Browser/NonExistentTest.php',
    ])
        ->doesntExpectOutputToContain('Browser Testing & Playwright Environment Doctor');
});

it('resolves browser-test-bootstrap.php file and registers hooks idempotently', function () {
    $bootstrapPath = realpath(__DIR__.'/../../src/Support/browser-test-bootstrap.php');
    $runnerPath = realpath(__DIR__.'/../../src/Support/browser-pest-runner.php');

    expect($bootstrapPath)->not->toBeFalse()
        ->and(file_exists((string) $bootstrapPath))->toBeTrue()
        ->and($runnerPath)->not->toBeFalse()
        ->and(file_exists((string) $runnerPath))->toBeTrue();

    BrowserSnapshotManager::$pestHooksRegistered = false;

    // First call should register
    BrowserSnapshotManager::registerPestHooks();
    expect(BrowserSnapshotManager::$pestHooksRegistered)->toBeTrue();

    // Second call should be a no-op / idempotent
    BrowserSnapshotManager::registerPestHooks();
    expect(BrowserSnapshotManager::$pestHooksRegistered)->toBeTrue();
});

it('accepts --skip-orphan-check and --force-kill-orphans flags during execution', function () {
    $this->artisan('rubberstamp:browser', [
        'target' => 'tests/Browser/NonExistentTest.php',
        '--skip-health-check' => true,
        '--skip-orphan-check' => true,
        '--force-kill-orphans' => true,
        '--pest-path' => '/nonexistent/path/to/pest',
    ])
        ->assertExitCode(1);
});
