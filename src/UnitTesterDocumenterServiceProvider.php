<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter;

use Eldinbiz\RubberStamp\RubberStampServiceProvider;

if (! class_exists(RubberStampServiceProvider::class, false)) {
    spl_autoload_register(function (string $class): void {
        $prefix = 'Eldinbiz\\RubberStamp\\';
        if (str_starts_with($class, $prefix)) {
            $relativeClass = substr($class, strlen($prefix));
            $file = __DIR__.'/'.str_replace('\\', '/', $relativeClass).'.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    });

    require_once __DIR__.'/RubberStampServiceProvider.php';
}

/**
 * @deprecated Use \Eldinbiz\RubberStamp\RubberStampServiceProvider instead.
 */
class UnitTesterDocumenterServiceProvider extends RubberStampServiceProvider
{
}
