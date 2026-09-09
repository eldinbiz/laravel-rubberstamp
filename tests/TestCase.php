<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use UnitTesterDocumenter\UnitTesterDocumenter\UnitTesterDocumenterServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            UnitTesterDocumenterServiceProvider::class,
        ];
    }
}
