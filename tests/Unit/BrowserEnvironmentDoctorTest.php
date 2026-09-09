<?php

declare(strict_types=1);

use UnitTesterDocumenter\UnitTesterDocumenter\Support\BrowserEnvironmentDoctor;

it('evaluates check failures correctly', function () {
    $doctor = new BrowserEnvironmentDoctor(__DIR__);

    $checksWithFailure = [
        ['name' => 'Check 1', 'status' => BrowserEnvironmentDoctor::STATUS_OK, 'message' => 'ok', 'suggestion' => null],
        ['name' => 'Check 2', 'status' => BrowserEnvironmentDoctor::STATUS_FAILED, 'message' => 'failed', 'suggestion' => 'fix it'],
    ];

    $checksWithoutFailure = [
        ['name' => 'Check 1', 'status' => BrowserEnvironmentDoctor::STATUS_OK, 'message' => 'ok', 'suggestion' => null],
        ['name' => 'Check 2', 'status' => BrowserEnvironmentDoctor::STATUS_WARNING, 'message' => 'warning', 'suggestion' => 'optional fix'],
    ];

    expect($doctor->hasFailures($checksWithFailure))->toBeTrue();
    expect($doctor->hasFailures($checksWithoutFailure))->toBeFalse();
});

it('returns all 5 core checks in checkAll', function () {
    $doctor = new BrowserEnvironmentDoctor(__DIR__);
    $checks = $doctor->checkAll();

    expect($checks)->toBeArray()
        ->and(count($checks))->toBe(5);

    foreach ($checks as $check) {
        expect($check)->toHaveKeys(['name', 'status', 'message', 'suggestion'])
            ->and(in_array($check['status'], [
                BrowserEnvironmentDoctor::STATUS_OK,
                BrowserEnvironmentDoctor::STATUS_WARNING,
                BrowserEnvironmentDoctor::STATUS_FAILED,
            ], true))->toBeTrue();
    }
});

it('respects explicitly configured existing chromium binary', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'chrom_test_');
    $doctor = new BrowserEnvironmentDoctor(__DIR__, $tempFile);

    expect($doctor->detectChromiumBinary())->toBe($tempFile);

    if ($tempFile && file_exists($tempFile)) {
        @unlink($tempFile);
    }
});

it('identifies missing browser test directory gracefully as a warning', function () {
    $tempBase = sys_get_temp_dir().DIRECTORY_SEPARATOR.'empty_base_'.uniqid();
    @mkdir($tempBase, 0755, true);

    $doctor = new BrowserEnvironmentDoctor($tempBase);
    $result = $doctor->checkBrowserTestDirectory();

    expect($result['status'])->toBe(BrowserEnvironmentDoctor::STATUS_WARNING)
        ->and($result['suggestion'])->not->toBeNull();

    @rmdir($tempBase);
});
