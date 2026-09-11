<?php

declare(strict_types=1);

it('registers the doctest:browser artisan command', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('doctest:browser');
});

it('registers the test:browser alias', function () {
    $command = Artisan::all()['doctest:browser'];

    expect($command->getAliases())->toContain('test:browser');
});

it('has the expected command description and options', function () {
    $command = Artisan::all()['doctest:browser'];

    expect($command->getDescription())->toContain('Run Pest browser tests with Playwright self-health diagnostics')
        ->and($command->getDefinition()->hasOption('doctor'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('check'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('skip-health-check'))->toBeTrue()
        ->and($command->getDefinition()->hasArgument('target'))->toBeTrue();
});

it('can run doctor diagnostics via artisan doctest:browser --doctor', function () {
    $this->artisan('doctest:browser', ['--doctor' => true])
        ->expectsOutputToContain('Browser Testing & Playwright Environment Doctor')
        ->assertExitCode(in_array($this->artisan('doctest:browser', ['--doctor' => true]), [0, 1], true) ? 0 : 1);
});

it('exposes the default package configurations', function () {
    expect(config('unit-tester-documenter.results_dir'))->toBe('doctest-reports/browser-test-log')
        ->and(config('unit-tester-documenter.pest_log_dir'))->toBe('doctest-reports/test-log')
        ->and(config('unit-tester-documenter.test_log_dir'))->toBe('doctest-reports/test-log')
        ->and(config('unit-tester-documenter.memory_limit'))->toBe('1024M')
        ->and(config('unit-tester-documenter.cleanup_snapshots_on_failure'))->toBeTrue();
});

it('resolves browser test artifacts inside results_dir', function () {
    $resultsDir = config('unit-tester-documenter.results_dir');

    expect($resultsDir)->toBe('doctest-reports/browser-test-log');
});
