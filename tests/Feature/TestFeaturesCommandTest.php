<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('registers the test:features artisan command', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('test:features');
});

it('registers the test:feature alias', function () {
    $command = Artisan::all()['test:features'];

    expect($command->getAliases())->toContain('test:feature');
});

it('has the expected command definition and options', function () {
    $command = Artisan::all()['test:features'];

    expect($command->getDescription())->toContain('Run Pest tests with cache clearing')
        ->and($command->getDefinition()->hasArgument('target'))->toBeTrue()
        ->and($command->getDefinition()->getArgument('target')->isRequired())->toBeFalse()
        ->and($command->getDefinition()->getArgument('target')->getDefault())->toBeNull()
        ->and($command->getDefinition()->hasOption('pest-path'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('skip-clear'))->toBeTrue();
});

it('handles missing pest executable gracefully', function () {
    $this->artisan('test:features', [
        '--pest-path' => '/nonexistent/path/to/pest',
        '--skip-clear' => true,
    ])
        ->expectsOutputToContain('Pest executable could not be found')
        ->assertExitCode(1);
});
