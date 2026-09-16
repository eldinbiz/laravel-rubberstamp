<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter;

use UnitTesterDocumenter\UnitTesterDocumenter\Support\BrowserSnapshotManager;

class UnitTesterDocumenter
{
    /**
     * Register Pest browser hooks for snapshot capturing.
     */
    public static function registerPestBrowserHooks(): void
    {
        BrowserSnapshotManager::registerPestHooks();
    }
}
