<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Facades;

use Eldinbiz\RubberStamp\Facades\RubberStamp;

if (! class_exists(RubberStamp::class, false)) {
    require_once __DIR__.'/RubberStamp.php';
}

/**
 * @deprecated Use \Eldinbiz\RubberStamp\Facades\RubberStamp instead.
 * @see \Eldinbiz\RubberStamp\RubberStamp
 */
class UnitTesterDocumenter extends RubberStamp
{
}
