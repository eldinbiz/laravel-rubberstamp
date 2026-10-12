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

it('identifies unattached orphaned chromium and node test processes', function () {
    $sanitizer = new BrowserProcessSanitizer('C:\\projects\\scaffolding-app-laravel', 'scaffolding-app-laravel');

    $mockProcesses = [
        [
            'pid' => 6001,
            'ppid' => 1,
            'name' => 'chromium.exe',
            'command' => 'chromium.exe --headless --remote-debugging-pipe',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['pid'])->toBe(6001)
        ->and($filtered[0]['name'])->toBe('chromium.exe')
        ->and($filtered[0]['scope'])->toBe('Orphaned Test Browser');
});

it('identifies playwright servers listening on Windows ephemeral port range (49152-49999)', function () {
    $sanitizer = new BrowserProcessSanitizer('C:\\projects\\scaffolding-app-laravel', 'scaffolding-app-laravel');

    $mockProcesses = [
        [
            'pid' => 7001,
            'ppid' => 10,
            'name' => 'node.exe',
            'command' => 'node node_modules/playwright-core/cli.js run-server --port 49500',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses, 49500, [7001]);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['pid'])->toBe(7001)
        ->and($filtered[0]['port'])->toBe(49500);
});

it('ignores developer runner and watcher processes such as concurrently, npm, and vite', function () {
    $sanitizer = new BrowserProcessSanitizer('C:\\projects\\scaffolding-app-laravel', 'scaffolding-app-laravel');

    $mockProcesses = [
        [
            'pid' => 8001,
            'ppid' => 100,
            'name' => 'node.exe',
            'command' => 'node.exe "C:\\Users\\dev\\AppData\\Roaming\\npm\\node_modules\\concurrently\\dist\\bin\\concurrently.js" -c "#93c5fd,#c4b5fd" "php artisan serve" "npm run dev"',
        ],
        [
            'pid' => 8002,
            'ppid' => 8001,
            'name' => 'node.exe',
            'command' => 'node.exe "C:\\Program Files\\nodejs\\node_modules\\npm\\bin\\npm-cli.js" run dev',
        ],
        [
            'pid' => 8003,
            'ppid' => 8002,
            'name' => 'node.exe',
            'command' => 'node.exe "C:\\projects\\scaffolding-app-laravel\\node_modules\\vite\\bin\\vite.js"',
        ],
        [
            'pid' => 8004,
            'ppid' => 100,
            'name' => 'node.exe',
            'command' => 'node.exe C:\\projects\\scaffolding-app-laravel\\node_modules\\eslint\\bin\\eslint.js --watch',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses);

    expect($filtered)->toBeEmpty();
});

it('identifies playwright browser worker processes located inside ms-playwright directory', function () {
    $sanitizer = new BrowserProcessSanitizer('C:\\projects\\scaffolding-app-laravel', 'scaffolding-app-laravel');

    $mockProcesses = [
        // 1. Playwright main browser
        [
            'pid' => 9001,
            'ppid' => 10,
            'name' => 'chrome.exe',
            'command' => '"C:\\Users\\dev\\AppData\\Local\\ms-playwright\\chromium-1155\\chrome-win\\chrome.exe" --headless --remote-debugging-pipe',
        ],
        // 2. Playwright GPU worker process
        [
            'pid' => 9002,
            'ppid' => 9001,
            'name' => 'chrome.exe',
            'command' => '"C:\\Users\\dev\\AppData\\Local\\ms-playwright\\chromium-1155\\chrome-win\\chrome.exe" --type=gpu-process',
        ],
        // 3. Playwright renderer worker process (even if parent died or was orphaned)
        [
            'pid' => 9003,
            'ppid' => 1,
            'name' => 'chrome.exe',
            'command' => '"C:\\Users\\dev\\AppData\\Local\\ms-playwright\\chromium-1155\\chrome-win\\chrome.exe" --type=renderer',
        ],
        // 4. Regular user Google Chrome (should NOT be matched)
        [
            'pid' => 9004,
            'ppid' => 500,
            'name' => 'chrome.exe',
            'command' => '"C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe" --type=gpu-process',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses);

    expect($filtered)->toHaveCount(3)
        ->and($filtered[0]['pid'])->toBe(9001)
        ->and($filtered[0]['scope'])->toBe('Playwright Browser Worker')
        ->and($filtered[1]['pid'])->toBe(9002)
        ->and($filtered[1]['scope'])->toBe('Playwright Browser Worker')
        ->and($filtered[2]['pid'])->toBe(9003)
        ->and($filtered[2]['scope'])->toBe('Playwright Browser Worker');
});

it('identifies tagged pest browser test runners via rubberstamp.tag', function () {
    $sanitizer = new BrowserProcessSanitizer('C:\\projects\\scaffolding-app-laravel', 'scaffolding-app-laravel');

    $mockProcesses = [
        // 1. Tagged Pest process belonging to this app
        [
            'pid' => 9101,
            'ppid' => 10,
            'name' => 'php.exe',
            'command' => 'php.exe -d memory_limit=1024M -d rubberstamp.tag=[scaffolding-app-laravel][rubberstamp]-browser-test vendor/bin/pest tests/Browser',
        ],
        // 2. Tagged Pest process belonging to a DIFFERENT app
        [
            'pid' => 9102,
            'ppid' => 20,
            'name' => 'php.exe',
            'command' => 'php.exe -d memory_limit=1024M -d rubberstamp.tag=[other-app][rubberstamp]-browser-test C:\\other-app\\vendor\\bin\\pest tests/Browser',
        ],
        // 3. Normal php artisan serve command (should NOT be matched)
        [
            'pid' => 9103,
            'ppid' => 30,
            'name' => 'php.exe',
            'command' => 'php artisan serve --port 8000',
        ],
        // 4. Normal php artisan queue:listen command (should NOT be matched)
        [
            'pid' => 9104,
            'ppid' => 30,
            'name' => 'php.exe',
            'command' => 'php artisan queue:listen --tries=1 --timeout=0',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['pid'])->toBe(9101)
        ->and($filtered[0]['name'])->toBe('php.exe')
        ->and($filtered[0]['scope'])->toBe('scaffolding-app-laravel (Pest Browser Runner)');
});

it('identifies sibling rubberstamp:browser cli session for the same repository', function () {
    $sanitizer = new BrowserProcessSanitizer('C:\\projects\\scaffolding-app-laravel', 'scaffolding-app-laravel');

    $mockProcesses = [
        // 1. Sibling session running for this repository
        [
            'pid' => 9201,
            'ppid' => 10,
            'name' => 'php.exe',
            'command' => 'php.exe "C:\\projects\\scaffolding-app-laravel\\artisan" rubberstamp:browser --no-doc',
        ],
        // 2. Sibling session running for a DIFFERENT repository
        [
            'pid' => 9202,
            'ppid' => 20,
            'name' => 'php.exe',
            'command' => 'php.exe "C:\\other-project\\artisan" rubberstamp:browser --no-doc',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['pid'])->toBe(9201)
        ->and($filtered[0]['name'])->toBe('php.exe')
        ->and($filtered[0]['scope'])->toBe('scaffolding-app-laravel (Active Browser Session)');
});

it('never matches current process pid as lingering process', function () {
    $sanitizer = new BrowserProcessSanitizer('C:\\projects\\scaffolding-app-laravel', 'scaffolding-app-laravel');
    $currentPid = (int) getmypid();

    $mockProcesses = [
        [
            'pid' => $currentPid,
            'ppid' => 1,
            'name' => 'php.exe',
            'command' => 'php.exe "C:\\projects\\scaffolding-app-laravel\\artisan" rubberstamp:browser',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses);

    expect($filtered)->toBeEmpty();
});

it('identifies sibling rubberstamp:browser cli session even when invoked with relative artisan path', function () {
    $sanitizer = new BrowserProcessSanitizer('C:\\projects\\scaffolding-app-laravel', 'scaffolding-app-laravel');

    $mockProcesses = [
        // Sibling session running via relative artisan command line
        [
            'pid' => 9301,
            'ppid' => 10,
            'name' => 'php.exe',
            'command' => 'php artisan rubberstamp:browser --no-doc',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['pid'])->toBe(9301)
        ->and($filtered[0]['name'])->toBe('php.exe')
        ->and($filtered[0]['scope'])->toBe('scaffolding-app-laravel (Active Browser Session)');
});

it('persists, reads, and cleans active session PID in rubberstamp-session.json', function () {
    $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rubberstamp_sess_'.uniqid();
    $sanitizer = new BrowserProcessSanitizer($tempDir, 'test-app');

    expect($sanitizer->getRecordedSessionPid())->toBeNull();

    $sanitizer->persistActiveSession(12345);
    expect($sanitizer->getRecordedSessionPid())->toBe(12345);

    $sanitizer->cleanActiveSession();
    expect($sanitizer->getRecordedSessionPid())->toBeNull();

    $sanitizer->persistActiveSession(67890);
    $sanitizer->cleanStaleTempFiles();
    expect($sanitizer->getRecordedSessionPid())->toBeNull();

    @rmdir($tempDir);
});

it('identifies active session process matching recorded session PID', function () {
    $sanitizer = new BrowserProcessSanitizer('C:\\projects\\scaffolding-app-laravel', 'scaffolding-app-laravel');

    $mockProcesses = [
        [
            'pid' => 9401,
            'ppid' => 10,
            'name' => 'php.exe',
            'command' => 'php.exe custom-runner.php',
        ],
    ];

    $filtered = $sanitizer->filterLingeringProcesses($mockProcesses, null, [], 9401);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['pid'])->toBe(9401)
        ->and($filtered[0]['scope'])->toBe('scaffolding-app-laravel (Active Browser Session)');
});
