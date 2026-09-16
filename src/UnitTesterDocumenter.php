<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter;

use Eldinbiz\RubberStamp\RubberStamp;

if (! class_exists(RubberStamp::class, false)) {
    require_once __DIR__.'/RubberStamp.php';
}

/**
 * @deprecated Use \Eldinbiz\RubberStamp\RubberStamp instead.
 */
class UnitTesterDocumenter extends RubberStamp
{
}
