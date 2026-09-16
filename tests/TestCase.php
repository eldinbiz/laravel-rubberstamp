<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Tests;

use Eldinbiz\RubberStamp\RubberStampServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            RubberStampServiceProvider::class,
        ];
    }
}

// Backward compatibility alias
if (! class_exists(\UnitTesterDocumenter\UnitTesterDocumenter\Tests\TestCase::class, false)) {
    class_alias(TestCase::class, \UnitTesterDocumenter\UnitTesterDocumenter\Tests\TestCase::class);
}
