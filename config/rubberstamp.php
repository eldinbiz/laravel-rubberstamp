<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Results and Logging Directories
    |--------------------------------------------------------------------------
    |
    | Defines where Pest raw logs and test snapshot images will be stored.
    | Non-browser test execution logs (from rubberstamp:features) are stored in the
    | test-log directory. Browser test execution logs and Playwright visual
    | snapshots (from rubberstamp:browser) are stored in the browser-test-log directory.
    |
    */
    'results_dir' => env('RUBBERSTAMP_RESULTS_DIR', 'doctest-reports/browser-test-log'),
    'test_log_dir' => env('RUBBERSTAMP_LOG_DIR', 'doctest-reports/test-log'),
    'pest_log_dir' => env('RUBBERSTAMP_LOG_DIR', 'doctest-reports/test-log'),

    /*
    |--------------------------------------------------------------------------
    | PHP Memory Limit
    |--------------------------------------------------------------------------
    |
    | The memory limit passed to the PHP process when invoking Pest.
    |
    */
    'memory_limit' => env('RUBBERSTAMP_MEMORY_LIMIT', '1024M'),

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
    'browser_timeout' => (int) env('RUBBERSTAMP_BROWSER_TIMEOUT', 10_000),

    /*
    |--------------------------------------------------------------------------
    | Post-Run Snapshot Management
    |--------------------------------------------------------------------------
    |
    | When set to true, failed test runs will clean up their snapshot
    | directory while preserving both the .pest and results logs.
    |
    */
    'cleanup_snapshots_on_failure' => (bool) env('RUBBERSTAMP_CLEANUP_ON_FAILURE', true),

    /*
    |--------------------------------------------------------------------------
    | Pest Binary Path
    |--------------------------------------------------------------------------
    |
    | Path to the Pest executable. When null, the command will look for
    | vendor/bin/pest or vendor/pestphp/pest/bin/pest automatically.
    |
    */
    'pest_binary' => env('RUBBERSTAMP_PEST_BINARY'),

    /*
    |--------------------------------------------------------------------------
    | Corporate Documentation & Evidence Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for automated print-ready HTML test reports
    | generated for corporate SOP, QA compliance, and audit sign-offs.
    |
    */
    'reports_dir' => env('RUBBERSTAMP_REPORTS_DIR', 'doctest-reports'),
    'report_view' => env('RUBBERSTAMP_REPORT_VIEW', 'rubberstamp::report'),
    'auto_document' => (bool) env('RUBBERSTAMP_AUTO_DOCUMENT', true),
    'document_id_prefix' => env('RUBBERSTAMP_DOCUMENT_ID_PREFIX', 'DOC-TEST-'),
    'company_name' => env('RUBBERSTAMP_COMPANY_NAME'),
    'classification' => env('RUBBERSTAMP_CLASSIFICATION', 'INTERNAL USE ONLY'),
    'author_name' => env('RUBBERSTAMP_AUTHOR_NAME'),
    'logo_path' => env('RUBBERSTAMP_LOGO_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Default Sign-Off Approval Configuration
    |--------------------------------------------------------------------------
    |
    | Default reviewer/approver/acknowledger roles. When left empty, sections
    | are omitted from the report unless specified via CLI options.
    |
    */
    'signoff' => [
        'reviewed_by' => [],
        'approved_by' => [],
        'acknowledged_by' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Artifact Pruning & Retention
    |--------------------------------------------------------------------------
    |
    | Default number of days to retain test logs, generated reports, and
    | Playwright visual snapshot directories when running rubberstamp:prune.
    |
    */
    'prune_retention_days' => (int) env('RUBBERSTAMP_PRUNE_RETENTION_DAYS', 7),

];
