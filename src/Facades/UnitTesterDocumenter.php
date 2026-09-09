<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \UnitTesterDocumenter\UnitTesterDocumenter\UnitTesterDocumenter
 */
class UnitTesterDocumenter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \UnitTesterDocumenter\UnitTesterDocumenter\UnitTesterDocumenter::class;
    }
}
