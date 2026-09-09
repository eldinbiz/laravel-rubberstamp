<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter;

use Illuminate\Support\ServiceProvider;
use UnitTesterDocumenter\UnitTesterDocumenter\Console\Commands\TestBrowserCommand;
use UnitTesterDocumenter\UnitTesterDocumenter\Console\Commands\TestFeaturesCommand;
use UnitTesterDocumenter\UnitTesterDocumenter\Console\Commands\UnitTesterDocumenterCommand;

class UnitTesterDocumenterServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/unit-tester-documenter.php', 'unit-tester-documenter');

        $this->app->singleton(UnitTesterDocumenter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/unit-tester-documenter.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'unit-tester-documenter');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'unit-tester-documenter');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/unit-tester-documenter.php' => config_path('unit-tester-documenter.php'),
        ], ['unit-tester-documenter', 'unit-tester-documenter-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/unit-tester-documenter'),
        ], ['unit-tester-documenter', 'unit-tester-documenter-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/unit-tester-documenter'),
        ], ['unit-tester-documenter', 'unit-tester-documenter-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/unit-tester-documenter'),
        ], ['unit-tester-documenter', 'unit-tester-documenter-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['unit-tester-documenter', 'unit-tester-documenter-migrations']);

        $this->commands([
            UnitTesterDocumenterCommand::class,
            TestBrowserCommand::class,
            TestFeaturesCommand::class,
        ]);
    }
}
