<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp;

use Eldinbiz\RubberStamp\Console\Commands\DocumentCommand;
use Eldinbiz\RubberStamp\Console\Commands\PruneCommand;
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
        $this->mergeConfigFrom(__DIR__.'/../config/rubberstamp.php', 'rubberstamp');

        $this->app->singleton(RubberStamp::class);
        $this->app->alias(RubberStamp::class, 'rubberstamp');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/rubberstamp.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'rubberstamp');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'rubberstamp');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/rubberstamp.php' => config_path('rubberstamp.php'),
        ], ['rubberstamp', 'rubberstamp-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/rubberstamp'),
        ], ['rubberstamp', 'rubberstamp-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/rubberstamp'),
        ], ['rubberstamp', 'rubberstamp-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/rubberstamp'),
        ], ['rubberstamp', 'rubberstamp-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['rubberstamp', 'rubberstamp-migrations']);

        $this->commands([
            RubberStampCommand::class,
            TestBrowserCommand::class,
            TestFeaturesCommand::class,
            DocumentCommand::class,
            PruneCommand::class,
        ]);
    }
}
