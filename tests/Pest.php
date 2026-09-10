<?php

declare(strict_types=1);

use UnitTesterDocumenter\UnitTesterDocumenter\Tests\TestCase;

$token = $_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? (string) getmypid();

putenv("APP_SERVICES_CACHE=bootstrap/cache/services_{$token}.php");
putenv("APP_PACKAGES_CACHE=bootstrap/cache/packages_{$token}.php");
$_ENV['APP_SERVICES_CACHE'] = "bootstrap/cache/services_{$token}.php";
$_ENV['APP_PACKAGES_CACHE'] = "bootstrap/cache/packages_{$token}.php";
$_SERVER['APP_SERVICES_CACHE'] = "bootstrap/cache/services_{$token}.php";
$_SERVER['APP_PACKAGES_CACHE'] = "bootstrap/cache/packages_{$token}.php";

uses(TestCase::class)->in(__DIR__);
