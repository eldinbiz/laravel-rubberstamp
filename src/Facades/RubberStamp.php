<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Facades;

use Eldinbiz\RubberStamp\Support\BrowserSnapshotManager;
use Illuminate\Support\Facades\Facade;

/**
 * @see \Eldinbiz\RubberStamp\RubberStamp
 */
class RubberStamp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Eldinbiz\RubberStamp\RubberStamp::class;
    }

    /**
     * Register Pest browser hooks without requiring a booted container.
     */
    public static function registerPestBrowserHooks(): void
    {
        BrowserSnapshotManager::registerPestHooks();
    }
}
