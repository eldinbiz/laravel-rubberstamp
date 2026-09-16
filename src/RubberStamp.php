<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp;

use Eldinbiz\RubberStamp\Support\BrowserSnapshotManager;

class RubberStamp
{
    /**
     * Register Pest browser hooks for snapshot capturing.
     */
    public static function registerPestBrowserHooks(): void
    {
        BrowserSnapshotManager::registerPestHooks();
    }
}
