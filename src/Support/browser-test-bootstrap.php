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

// 3. Load Composer autoloader
$autoloadPath = getcwd().DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// 3. Queue the afterEach snapshot hook into Pest's Plugin::$callables
if (class_exists(Plugin::class)) {
    Plugin::$callables[] = static function (): void {
        BrowserSnapshotManager::registerPestHooks();
    };
}
