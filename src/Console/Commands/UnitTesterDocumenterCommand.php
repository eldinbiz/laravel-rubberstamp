<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Console\Commands;

use Eldinbiz\RubberStamp\Console\Commands\RubberStampCommand;

if (! class_exists(RubberStampCommand::class, false)) {
    require_once __DIR__.'/RubberStampCommand.php';
}

/**
 * @deprecated Use \Eldinbiz\RubberStamp\Console\Commands\RubberStampCommand instead.
 */
class UnitTesterDocumenterCommand extends RubberStampCommand
{
}
