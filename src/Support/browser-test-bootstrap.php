<?php

declare(strict_types=1);

use Eldinbiz\RubberStamp\Support\BrowserSnapshotManager;
use Pest\Plugin;

require_once __DIR__.'/BrowserSnapshotManager.php';

// 1. Pre-define Pest's global visit() before vendor/autoload.php loads Functions.php
if (! function_exists('visit')) {
    /**
     * Browse to the given URL while tracking active browser page for snapshot capture.
     *
     * @param  array<int, string>|string  $url
     * @param  array<string, mixed>  $options
     */
    function visit(array|string $url, array $options = []): mixed
    {
        /** @var mixed $test */
        $test = test();
        /** @var mixed $page */
        $page = $test->visit($url, $options);

        if (is_object($page) && is_a($page, 'Pest\Browser\Api\PendingAwaitablePage')) {
            BrowserSnapshotManager::$activeBrowserPage = $page;
        }

        return $page;
    }
}

// 2. Ensure Collision printer environment is set before autoloader runs
$_SERVER['COLLISION_PRINTER'] = 'DefaultPrinter';

// 3. Ensure testing environment defaults are populated
foreach ([
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
] as $key => $value) {
    if (getenv($key) === false || getenv($key) === '') {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// 4. Load Composer autoloader
$autoloadPath = getcwd().DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// 5. Enable HTTP method parameter override & eager configuration bootstrap for Pest discovery
if (class_exists(\Illuminate\Http\Request::class)) {
    \Illuminate\Http\Request::enableHttpMethodParameterOverride();
}

$appBootstrap = getcwd().DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';

if (file_exists($appBootstrap)) {
    try {
        if (! function_exists('app') || ! app()->bound('config')) {
            /** @var \Illuminate\Foundation\Application $app */
            $app = require $appBootstrap;
            $app->bootstrapWith([
                \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
                \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
            ]);
            $app->instance('request', \Illuminate\Http\Request::create('/'));
        }
    } catch (\Throwable) {
        // Graceful fallback if application bootstrap is deferred
    }
}

// 6. Queue the afterEach snapshot hook into Pest's Plugin::$callables
if (class_exists(Plugin::class)) {
    Plugin::$callables[] = static function (): void {
        BrowserSnapshotManager::registerPestHooks();
    };
}

