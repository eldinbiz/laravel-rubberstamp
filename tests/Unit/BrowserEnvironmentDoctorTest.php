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

it('fails playwright check if not installed in node_modules', function () {
    $tempBase = sys_get_temp_dir().DIRECTORY_SEPARATOR.'empty_base_'.uniqid();
    @mkdir($tempBase, 0755, true);

    $doctor = new BrowserEnvironmentDoctor($tempBase);
    $result = $doctor->checkPlaywrightPackage();

    expect($result['status'])->toBe(BrowserEnvironmentDoctor::STATUS_FAILED)
        ->and($result['suggestion'])->toContain('npm install -D playwright');

    @rmdir($tempBase);
});

it('passes playwright check when installed in node_modules', function () {
    $tempBase = sys_get_temp_dir().DIRECTORY_SEPARATOR.'playwright_base_'.uniqid();
    @mkdir($tempBase.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright', 0755, true);

    $doctor = new BrowserEnvironmentDoctor($tempBase);
    $result = $doctor->checkPlaywrightPackage();

    expect($result['status'])->toBe(BrowserEnvironmentDoctor::STATUS_OK)
        ->and($result['message'])->toContain('installed in node_modules');

    @rmdir($tempBase.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright');
    @rmdir($tempBase.DIRECTORY_SEPARATOR.'node_modules');
    @rmdir($tempBase);
});

it('passes chromium check when explicitly configured binary exists', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'chrom_test_');
    $doctor = new BrowserEnvironmentDoctor(__DIR__, $tempFile);
    $result = $doctor->checkChromiumBrowser();

    expect($result['status'])->toBe(BrowserEnvironmentDoctor::STATUS_OK)
        ->and($result['message'])->toContain($tempFile);

    if ($tempFile && file_exists($tempFile)) {
        @unlink($tempFile);
    }
});

it('fails chromium check with playwright install suggestion when not found in isolated cache', function () {
    $previousLocal = getenv('LOCALAPPDATA');
    $previousHome = getenv('HOME');
    $tempEmptyDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'empty_cache_'.uniqid();
    @mkdir($tempEmptyDir, 0755, true);

    putenv("LOCALAPPDATA={$tempEmptyDir}");
    putenv("HOME={$tempEmptyDir}");
    putenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH');

    try {
        $doctor = new BrowserEnvironmentDoctor(__DIR__);
        $result = $doctor->checkChromiumBrowser();

        expect($result['status'])->toBe(BrowserEnvironmentDoctor::STATUS_FAILED)
            ->and($result['suggestion'])->toContain('npx playwright install chromium');
    } finally {
        if ($previousLocal !== false) {
            putenv("LOCALAPPDATA={$previousLocal}");
        } else {
            putenv('LOCALAPPDATA');
        }

        if ($previousHome !== false) {
            putenv("HOME={$previousHome}");
        } else {
            putenv('HOME');
        }

        @rmdir($tempEmptyDir);
    }
});

it('fails chromium check when cached browser revision is outdated compared to expected revision', function () {
    $previousLocal = getenv('LOCALAPPDATA');
    $previousHome = getenv('HOME');

    $tempProject = sys_get_temp_dir().DIRECTORY_SEPARATOR.'proj_'.uniqid();
    $tempCache = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cache_'.uniqid();

    @mkdir($tempProject.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright-core', 0755, true);
    file_put_contents(
        $tempProject.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright-core'.DIRECTORY_SEPARATOR.'browsers.json',
        json_encode(['browsers' => [['name' => 'chromium', 'revision' => '1243']]])
    );

    $msPlaywright = $tempCache.DIRECTORY_SEPARATOR.'ms-playwright';
    @mkdir($msPlaywright.DIRECTORY_SEPARATOR.'chromium-1200', 0755, true);

    putenv("LOCALAPPDATA={$tempCache}");
    putenv("HOME={$tempCache}");
    putenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH');

    try {
        $doctor = new BrowserEnvironmentDoctor($tempProject);
        $result = $doctor->checkChromiumBrowser();

        expect($result['status'])->toBe(BrowserEnvironmentDoctor::STATUS_FAILED)
            ->and($result['message'])->toContain('revision 1243')
            ->and($result['message'])->toContain('chromium-1200')
            ->and($result['suggestion'])->toContain('npx playwright install chromium');
    } finally {
        if ($previousLocal !== false) {
            putenv("LOCALAPPDATA={$previousLocal}");
        } else {
            putenv('LOCALAPPDATA');
        }

        if ($previousHome !== false) {
            putenv("HOME={$previousHome}");
        } else {
            putenv('HOME');
        }

        @rmdir($msPlaywright.DIRECTORY_SEPARATOR.'chromium-1200');
        @rmdir($msPlaywright);
        @rmdir($tempCache);
        @unlink($tempProject.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright-core'.DIRECTORY_SEPARATOR.'browsers.json');
        @rmdir($tempProject.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright-core');
        @rmdir($tempProject.DIRECTORY_SEPARATOR.'node_modules');
        @rmdir($tempProject);
    }
});

it('passes chromium check when cached browser revision matches expected revision', function () {
    $previousLocal = getenv('LOCALAPPDATA');
    $previousHome = getenv('HOME');

    $tempProject = sys_get_temp_dir().DIRECTORY_SEPARATOR.'proj_'.uniqid();
    $tempCache = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cache_'.uniqid();

    @mkdir($tempProject.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright-core', 0755, true);
    file_put_contents(
        $tempProject.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright-core'.DIRECTORY_SEPARATOR.'browsers.json',
        json_encode(['browsers' => [['name' => 'chromium', 'revision' => '1243']]])
    );

    $msPlaywright = $tempCache.DIRECTORY_SEPARATOR.'ms-playwright';
    @mkdir($msPlaywright.DIRECTORY_SEPARATOR.'chromium-1243', 0755, true);

    putenv("LOCALAPPDATA={$tempCache}");
    putenv("HOME={$tempCache}");
    putenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH');

    try {
        $doctor = new BrowserEnvironmentDoctor($tempProject);
        $result = $doctor->checkChromiumBrowser();

        expect($result['status'])->toBe(BrowserEnvironmentDoctor::STATUS_OK)
            ->and($result['message'])->toContain('Found Playwright Chromium (revision 1243)');
    } finally {
        if ($previousLocal !== false) {
            putenv("LOCALAPPDATA={$previousLocal}");
        } else {
            putenv('LOCALAPPDATA');
        }

        if ($previousHome !== false) {
            putenv("HOME={$previousHome}");
        } else {
            putenv('HOME');
        }

        @rmdir($msPlaywright.DIRECTORY_SEPARATOR.'chromium-1243');
        @rmdir($msPlaywright);
        @rmdir($tempCache);
        @unlink($tempProject.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright-core'.DIRECTORY_SEPARATOR.'browsers.json');
        @rmdir($tempProject.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright-core');
        @rmdir($tempProject.DIRECTORY_SEPARATOR.'node_modules');
        @rmdir($tempProject);
    }
});
