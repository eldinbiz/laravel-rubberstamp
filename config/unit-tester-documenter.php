<?php

declare(strict_types=1);

return [

    'placeholder' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Results and Logging Directories
    |--------------------------------------------------------------------------
    |
    | Defines where Pest raw logs and test snapshot images will be stored.
    | Each test run creates a timestamped subdirectory inside the results
    | directory and mirror logs in the pest log directory.
    |
    */
    'results_dir' => env('BROWSER_TEST_RESULTS_DIR', 'browser-test-results'),
    'pest_log_dir' => env('BROWSER_TEST_PEST_LOG_DIR', '.pest'),

    /*
    |--------------------------------------------------------------------------
    | PHP Memory Limit
    |--------------------------------------------------------------------------
    |
    | The memory limit passed to the PHP process when invoking Pest.
    |
    */
    'memory_limit' => env('BROWSER_TEST_MEMORY_LIMIT', '1024M'),

    /*
    |--------------------------------------------------------------------------
    | Playwright & Chromium Configuration
    |--------------------------------------------------------------------------
    |
    | Optional explicit path to the Chromium executable and whether to
    | skip automatic browser downloads by Playwright.
    |
    */
    'chromium_binary' => env('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'),
    'skip_browser_download' => (bool) env('PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD', true),

    /*
    |--------------------------------------------------------------------------
    | Post-Run Snapshot Management
    |--------------------------------------------------------------------------
    |
    | When set to true, failed test runs will clean up their snapshot
    | directory while preserving both the .pest and results logs.
    |
    */
    'cleanup_snapshots_on_failure' => (bool) env('BROWSER_TEST_CLEANUP_ON_FAILURE', true),

    /*
    |--------------------------------------------------------------------------
    | Pest Binary Path
    |--------------------------------------------------------------------------
    |
    | Path to the Pest executable. When null, the command will look for
    | vendor/bin/pest or vendor/pestphp/pest/bin/pest automatically.
    |
    */
    'pest_binary' => env('BROWSER_TEST_PEST_BINARY'),

];
