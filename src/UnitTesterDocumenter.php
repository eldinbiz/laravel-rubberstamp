<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter;

use UnitTesterDocumenter\UnitTesterDocumenter\Concerns\CapturesBrowserSnapshots;

class UnitTesterDocumenter
{
    /**
     * Register Pest browser hooks for snapshot capturing.
     */
    public static function registerPestBrowserHooks(): void
    {
        CapturesBrowserSnapshots::registerPestHooks();
    }
}
