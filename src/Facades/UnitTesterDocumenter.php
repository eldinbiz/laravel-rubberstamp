<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Facades;

use Illuminate\Support\Facades\Facade;
use UnitTesterDocumenter\UnitTesterDocumenter\Concerns\CapturesBrowserSnapshots;

/**
 * @see \UnitTesterDocumenter\UnitTesterDocumenter\UnitTesterDocumenter
 */
class UnitTesterDocumenter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \UnitTesterDocumenter\UnitTesterDocumenter\UnitTesterDocumenter::class;
    }

    /**
     * Register Pest browser hooks without requiring a booted container.
     */
    public static function registerPestBrowserHooks(): void
    {
        CapturesBrowserSnapshots::registerPestHooks();
    }
}
