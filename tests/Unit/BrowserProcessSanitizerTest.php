<?php

declare(strict_types=1);

use Eldinbiz\RubberStamp\Support\BrowserProcessSanitizer;

it('parses recorded port from temp state file when present', function () {
    $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rubberstamp_test_'.uniqid();
    $stateDir = $tempDir.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pestphp'.DIRECTORY_SEPARATOR.'pest-plugin-browser'.DIRECTORY_SEPARATOR.'.temp';
    mkdir($stateDir, 0755, true);

    $stateFile = $stateDir.DIRECTORY_SEPARATOR.'playwright-server.json';
    file_put_contents($stateFile, json_encode(['host' => '127.0.0.1', 'port' => 54932]));

    $sanitizer = new BrowserProcessSanitizer($tempDir, 'test-app');

    expect($sanitizer->getRecordedPort())->toBe(54932);

    $sanitizer->cleanStaleTempFiles();
    expect(file_exists($stateFile))->toBeFalse();
    expect($sanitizer->getRecordedPort())->toBeNull();

    @rmdir($stateDir);
});

it('returns null when state file does not exist or is invalid', function () {
    $sanitizer = new BrowserProcessSanitizer('/nonexistent/base/path', 'test-app');

    expect($sanitizer->getRecordedPort())->toBeNull();
});

it('filters playwright node server belonging to the current base path', function () {
    $basePath = 'C:\\projects\\my-laravel-app';
    $sanitizer = new BrowserProcessSanitizer($basePath, 'my-laravel-app');

    $mockProcesses = [
        // 1. Playwright server belonging to this app
        [
            'pid' => 1001,
            'ppid' => 500,
            'name' => 'node.exe',
            'command' => 'node.exe "C:\\projects\\my-laravel-app\\node_modules\\playwright-core\\cli.js" run-server --host 127.0.0.1 --port 54932',
        ],
        // 2. Playwright server belonging to ANOTHER app (should NOT be matched)
        [
            'pid' => 1002,
            'ppid' => 600,
            'name' => 'node.exe',
            'command' => 'node.exe "C:\\other-projects\\different-app\\node_modules\\playwright-core\\cli.js" run-server --host 127.0.0.1 --port 55000',
        ],
        // 3. Vite dev server for this app (should NOT be matched)
        [
            'pid' => 1003,
            'ppid' => 700,
            'name' => 'node.exe',
            'command' => 'node.exe "C:\\projects\\my-laravel-app\\node_modules\\vite\\bin\\vite.js"',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses, 54932, [1001]);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['pid'])->toBe(1001)
        ->and($filtered[0]['port'])->toBe(54932)
        ->and($filtered[0]['scope'])->toBe('my-laravel-app')
        ->and($filtered[0]['name'])->toBe('node.exe');
});

it('identifies chromium child processes belonging to detected node playwright server', function () {
    $basePath = 'C:\\projects\\my-laravel-app';
    $sanitizer = new BrowserProcessSanitizer($basePath, 'my-laravel-app');

    $mockProcesses = [
        // Node Playwright Server
        [
            'pid' => 2001,
            'ppid' => 100,
            'name' => 'node.exe',
            'command' => 'node.exe "C:\\projects\\my-laravel-app\\node_modules\\playwright-core\\cli.js" run-server',
        ],
        // Chromium spawned by this Node instance
        [
            'pid' => 2002,
            'ppid' => 2001,
            'name' => 'chromium.exe',
            'command' => 'chromium.exe --headless --remote-debugging-pipe --user-data-dir=C:\\temp\\pw',
        ],
        // Personal Google Chrome tab (unrelated parent PID, should NOT be matched)
        [
            'pid' => 3001,
            'ppid' => 450, // e.g. explorer.exe
            'name' => 'chrome.exe',
            'command' => 'chrome.exe https://google.com',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses);

    expect($filtered)->toHaveCount(2)
        ->and($filtered[0]['pid'])->toBe(2001)
        ->and($filtered[0]['name'])->toBe('node.exe')
        ->and($filtered[1]['pid'])->toBe(2002)
        ->and($filtered[1]['name'])->toBe('chromium.exe')
        ->and($filtered[1]['scope'])->toBe('Child of Node PID 2001');
});

it('normalizes mixed directory slashes in base path', function () {
    // Linux-style base path in sanitizer, Windows-style backslashes in command
    $sanitizer = new BrowserProcessSanitizer('c:/projects/my-laravel-app', 'my-laravel-app');

    $mockProcesses = [
        [
            'pid' => 4001,
            'ppid' => 10,
            'name' => 'node.exe',
            'command' => 'node.exe C:\\projects\\my-laravel-app\\node_modules\\playwright\\cli.js launchServer',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['pid'])->toBe(4001);
});

it('detects relative node_modules command line and extracts CLI port', function () {
    $sanitizer = new BrowserProcessSanitizer('C:\\projects\\scaffolding-app-laravel', 'scaffolding-app-laravel');

    $mockProcesses = [
        [
            'pid' => 5001,
            'ppid' => 10,
            'name' => 'node.exe',
            'command' => 'node node_modules/playwright-core/cli.js run-server --port 54932',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['pid'])->toBe(5001)
        ->and($filtered[0]['port'])->toBe(54932)
        ->and($filtered[0]['scope'])->toBe('scaffolding-app-laravel (local)');
});
