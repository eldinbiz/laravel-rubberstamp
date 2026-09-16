<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp;

use Eldinbiz\RubberStamp\Console\Commands\DocTestDocumentCommand;
use Eldinbiz\RubberStamp\Console\Commands\DocTestPruneCommand;
use Eldinbiz\RubberStamp\Console\Commands\RubberStampCommand;
use Eldinbiz\RubberStamp\Console\Commands\TestBrowserCommand;
use Eldinbiz\RubberStamp\Console\Commands\TestFeaturesCommand;
use Illuminate\Support\ServiceProvider;

class RubberStampServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $configPath = file_exists(__DIR__.'/../config/rubberstamp.php')
            ? __DIR__.'/../config/rubberstamp.php'
            : __DIR__.'/../config/unit-tester-documenter.php';

        $this->mergeConfigFrom($configPath, 'rubberstamp');
        $this->mergeConfigFrom($configPath, 'unit-tester-documenter');

        $this->app->singleton(RubberStamp::class);
        $this->app->alias(RubberStamp::class, 'rubberstamp');
        $this->app->alias(RubberStamp::class, \UnitTesterDocumenter\UnitTesterDocumenter\UnitTesterDocumenter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $routesPath = file_exists(__DIR__.'/../routes/rubberstamp.php')
            ? __DIR__.'/../routes/rubberstamp.php'
            : __DIR__.'/../routes/unit-tester-documenter.php';

        $this->loadRoutesFrom($routesPath);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'rubberstamp');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'unit-tester-documenter');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'rubberstamp');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'unit-tester-documenter');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $configPath = file_exists(__DIR__.'/../config/rubberstamp.php')
            ? __DIR__.'/../config/rubberstamp.php'
            : __DIR__.'/../config/unit-tester-documenter.php';

        $this->publishes([
            $configPath => config_path('rubberstamp.php'),
        ], ['rubberstamp', 'rubberstamp-config', 'unit-tester-documenter', 'unit-tester-documenter-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/rubberstamp'),
        ], ['rubberstamp', 'rubberstamp-views', 'unit-tester-documenter', 'unit-tester-documenter-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/rubberstamp'),
        ], ['rubberstamp', 'rubberstamp-lang', 'unit-tester-documenter', 'unit-tester-documenter-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/rubberstamp'),
        ], ['rubberstamp', 'rubberstamp-assets', 'unit-tester-documenter', 'unit-tester-documenter-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['rubberstamp', 'rubberstamp-migrations', 'unit-tester-documenter', 'unit-tester-documenter-migrations']);

        $this->commands([
            RubberStampCommand::class,
            TestBrowserCommand::class,
            TestFeaturesCommand::class,
            DocTestDocumentCommand::class,
            DocTestPruneCommand::class,
        ]);
    }
}
