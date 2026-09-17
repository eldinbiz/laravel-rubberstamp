<?php

declare(strict_types=1);

use Eldinbiz\RubberStamp\Console\Concerns\InteractsWithRubberStampOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;

it('registers the rubberstamp:features artisan command', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('rubberstamp:features')
        ->and($commands)->not->toHaveKey('doctest:features');
});

it('registers no aliases for rubberstamp:features', function () {
    $command = Artisan::all()['rubberstamp:features'];

    expect($command->getAliases())->toBeEmpty();
});

it('has the expected command definition and options', function () {
    $command = Artisan::all()['rubberstamp:features'];

    expect($command->getDescription())->toContain('Run Pest tests with cache clearing')
        ->and($command->getDefinition()->hasArgument('target'))->toBeTrue()
        ->and($command->getDefinition()->getArgument('target')->isRequired())->toBeFalse()
        ->and($command->getDefinition()->getArgument('target')->getDefault())->toBeNull()
        ->and($command->getDefinition()->hasOption('pest-path'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('skip-clear'))->toBeTrue();
});

it('handles missing pest executable gracefully', function () {
    $this->artisan('rubberstamp:features', [
        '--pest-path' => '/nonexistent/path/to/pest',
        '--skip-clear' => true,
    ])
        ->expectsOutputToContain('Pest executable could not be found')
        ->assertExitCode(1);
});

it('clears variables defined in environment file', function () {
    $tempEnv = (string) tempnam(sys_get_temp_dir(), 'env_test_');
    file_put_contents($tempEnv, "DOCTEST_CUSTOM_VAR=should_be_cleared\n");

    $_ENV['DOCTEST_CUSTOM_VAR'] = 'should_be_cleared';
    $_SERVER['DOCTEST_CUSTOM_VAR'] = 'should_be_cleared';
    putenv('DOCTEST_CUSTOM_VAR=should_be_cleared');

    $command = new class extends Command
    {
        use InteractsWithRubberStampOptions;

        public function testClear(string $path, string $file): void
        {
            $vars = $this->getEnvironmentVariables($path, $file);
            $repository = Env::getRepository();
            foreach ($vars as $name) {
                $repository->clear($name);
                unset($_ENV[$name], $_SERVER[$name]);
                putenv($name);
            }
        }
    };

    $command->testClear(dirname($tempEnv), basename($tempEnv));

    expect(isset($_ENV['DOCTEST_CUSTOM_VAR']))->toBeFalse()
        ->and(isset($_SERVER['DOCTEST_CUSTOM_VAR']))->toBeFalse()
        ->and(getenv('DOCTEST_CUSTOM_VAR'))->toBeFalse();

    @unlink($tempEnv);
});
