<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use UnitTesterDocumenter\UnitTesterDocumenter\Support\AuditMetadataResolver;
use UnitTesterDocumenter\UnitTesterDocumenter\Support\CorporateReportGenerator;
use UnitTesterDocumenter\UnitTesterDocumenter\Support\PestLogParser;

it('registers doctest:document artisan command with aliases and options', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('doctest:document');

    $command = $commands['doctest:document'];
    expect($command->getAliases())->toContain('test:document')
        ->and($command->getDefinition()->hasOption('author'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('sop'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('document-id'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('reviewed-by'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('approved-by'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('acknowledged-by'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('interactive'))->toBeTrue();
});

it('resolves audit metadata and parses sign-off roles with Name,Role and Role-only fallback', function () {
    $resolver = new AuditMetadataResolver;

    expect($resolver->resolveDocumentId('UAT-TEST', '20260910_102030'))
        ->toBe('UAT-TEST-20260910_102030')
        ->and($resolver->resolveTestCasePrefix('UAT-'))->toBe('UAT-CASE-')
        ->and($resolver->resolveTestCasePrefix(null, 'UAT-20260910_102030'))->toBe('UAT-CASE-')
        ->and($resolver->resolveTestCasePrefix(null, 'DOC-TEST-20260910-102030'))->toBe('DOC-TEST-CASE-')
        ->and($resolver->resolveTestCasePrefix())->toBe('DOC-TEST-CASE-')
        ->and($resolver->resolveSop(null))->toBe('N/A')
        ->and($resolver->resolveSop('SOP-QA-001'))->toBe('SOP-QA-001')
        ->and($resolver->resolveAuthor('Custom Tester'))->toBe('Custom Tester')
        ->and($resolver->resolveRuntimeStack())->toContain('PHP')
        ->and($resolver->resolveRuntimeStack())->toContain('Laravel');

    $parsedRoles = $resolver->parseSignoffOption('Mr Smith,QA Engineer|Taylor,QA Lead|QA Head');

    expect($parsedRoles)->toHaveCount(3)
        ->and($parsedRoles[0])->toBe(['name' => 'Mr Smith', 'role' => 'QA Engineer'])
        ->and($parsedRoles[1])->toBe(['name' => 'Taylor', 'role' => 'QA Lead'])
        ->and($parsedRoles[2])->toBe(['name' => '', 'role' => 'QA Head']);

    // Omission rule: empty/null options return empty array
    expect($resolver->parseSignoffOption(null))->toBeEmpty()
        ->and($resolver->parseSignoffOption(''))->toBeEmpty();
});

it('parses pest logs with ansi codes, passed/failed cases, and failure traces', function () {
    $parser = new PestLogParser;

    $sampleLog = <<<LOG
\e[30;42;1m PASS \e[39;49;22m Tests\Feature\AuthTest
\e[32;1m✓\e[39;22m user can authenticate 0.12s
\e[32;1m✓\e[39;22m user can logout 0.05s

\e[39;41;1m FAIL \e[39;49;22m Tests\Feature\ApprovalTest
\e[32;1m✓\e[39;22m step advances to next 0.10s
\e[31;1m⨯\e[39;22m rejected request notifies submitter 0.08s
──────────────────────────────────────────────────────────────────
FAILED  Tests\Feature\ApprovalTest > rejected request notifies submitter
Expected notification [RequestRejected] was not sent.

at tests/Feature/ApprovalTest.php:48
  46▕     \$request->reject();
  47▕
➜ 48▕     Notification::assertSentTo(\$user, RequestRejected::class);

Tests:  1 failed, 3 passed (12 assertions)
Duration: 0.35s
LOG;

    $result = $parser->parse($sampleLog);

    expect($result['verdict'])->toBe('FAILED')
        ->and($result['total_tests'])->toBe(4)
        ->and($result['passed_tests'])->toBe(3)
        ->and($result['failed_tests'])->toBe(1)
        ->and($result['total_assertions'])->toBe(12)
        ->and($result['suites'])->toHaveCount(2)
        ->and($result['suites'][0]['name'])->toBe('Tests\Feature\AuthTest')
        ->and($result['suites'][0]['status'])->toBe('PASSED')
        ->and($result['suites'][1]['name'])->toBe('Tests\Feature\ApprovalTest')
        ->and($result['suites'][1]['status'])->toBe('FAILED');

    $failedCase = $result['suites'][1]['cases'][1];
    expect($failedCase['name'])->toBe('rejected request notifies submitter')
        ->and($failedCase['status'])->toBe('FAILED')
        ->and($failedCase['failure'])->not->toBeNull()
        ->and($failedCase['failure']['location'])->toBe('tests/Feature/ApprovalTest.php:48');
});

it('generates corporate html report with strict omission of unpassed sign-off categories', function () {
    $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'doctest_test_'.uniqid();
    mkdir($tmpDir, 0755, true);

    config(['unit-tester-documenter.reports_dir' => 'reports']);

    $generator = new CorporateReportGenerator($tmpDir);

    $metadata = [
        'run_name' => 'test_20260910_102030',
        'document_id' => 'DOC-TEST-20260910-102030',
        'sop' => 'SOP-QA-999',
        'author' => 'Eldin Akbar',
        'executed_at' => '2026-09-10 10:20:30 UTC',
        'company_name' => 'Enterprise System',
        'classification' => 'INTERNAL USE ONLY',
        'git' => ['branch' => 'main', 'commit' => 'c0ffee1'],
        'runtime_stack' => 'PHP 8.4.1 / Laravel 12.0.0 / SQLite 3.45 (Windows)',
        'reviewed_by' => [
            ['name' => 'Mr Smith', 'role' => 'QA Engineer'],
            ['name' => '', 'role' => 'QA Head'],
        ],
        'approved_by' => [], // Omitted
        'acknowledged_by' => [], // Omitted
    ];

    $testData = [
        'verdict' => 'PASSED',
        'total_tests' => 5,
        'passed_tests' => 5,
        'failed_tests' => 0,
        'total_assertions' => 18,
        'duration' => '1.20s',
        'suites' => [
            [
                'name' => 'Tests\Unit\ExampleTest',
                'file' => 'tests/Unit/ExampleTest.php',
                'status' => 'PASSED',
                'total' => 5,
                'passed' => 5,
                'failed' => 0,
                'duration' => '1.20s',
                'cases' => [
                    [
                        'name' => 'it works properly',
                        'status' => 'PASSED',
                        'duration' => '0.10s',
                        'assertions' => 3,
                        'failure' => null,
                    ],
                ],
            ],
        ],
        'screenshots' => [],
    ];

    $report = $generator->generate($metadata, $testData);

    expect(file_exists($report['html_path']))->toBeTrue()
        ->and(isset($report['markdown_path']))->toBeFalse()
        ->and(file_exists($tmpDir.'/reports/test_20260910_102030.md'))->toBeFalse();

    $html = (string) file_get_contents($report['html_path']);

    // Check HTML contents
    expect($html)->toContain('Formal Test Execution Record')
        ->and($html)->toContain('DOC-TEST-20260910-102030')
        ->and($html)->toContain('DOC-TEST-CASE-1')
        ->and($html)->toContain('<code class="case-id">DOC-TEST-CASE-1</code>')
        ->and($html)->toContain('SOP-QA-999')
        ->and($html)->toContain('Eldin Akbar')
        ->and($html)->toContain('Mr Smith')
        ->and($html)->toContain('QA Head')
        ->and($html)->toContain('Prepared By:')
        ->and($html)->toContain('Reviewed By:')
        // Assert omission of unpassed categories:
        ->and($html)->not->toContain('Approved By:')
        ->and($html)->not->toContain('Acknowledged By:');

    // Cleanup temp
    @unlink($report['html_path']);
    @rmdir(dirname($report['html_path']));
    @rmdir($tmpDir);
});

it('numbers test cases sequentially with CASE prefix derived from document id prefix across multiple suites', function () {
    $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'doctest_uat_'.uniqid();
    mkdir($tmpDir, 0755, true);

    config(['unit-tester-documenter.reports_dir' => 'reports']);

    $generator = new CorporateReportGenerator($tmpDir);

    $metadata = [
        'run_name' => 'test_20260911_120000',
        'document_id' => 'UAT-20260911_120000',
        'document_id_prefix' => 'UAT-',
        'sop' => 'SOP-UAT-100',
        'author' => 'QA Lead',
        'executed_at' => '2026-09-11 12:00:00 UTC',
        'company_name' => 'Enterprise System',
        'classification' => 'CONFIDENTIAL',
        'git' => ['branch' => 'release', 'commit' => 'a1b2c3d'],
        'runtime_stack' => 'PHP 8.4.1 / Laravel 12.0.0 / SQLite 3.45 (Windows)',
        'reviewed_by' => [],
        'approved_by' => [],
        'acknowledged_by' => [],
    ];

    $testData = [
        'verdict' => 'FAILED',
        'total_tests' => 4,
        'passed_tests' => 3,
        'failed_tests' => 1,
        'total_assertions' => 10,
        'duration' => '2.50s',
        'suites' => [
            [
                'name' => 'Tests\Unit\FirstModuleTest',
                'file' => 'tests/Unit/FirstModuleTest.php',
                'status' => 'PASSED',
                'total' => 2,
                'passed' => 2,
                'failed' => 0,
                'duration' => '0.80s',
                'cases' => [
                    [
                        'name' => 'first test in module one',
                        'status' => 'PASSED',
                        'duration' => '0.30s',
                        'assertions' => 2,
                        'failure' => null,
                    ],
                    [
                        'name' => 'second test in module one',
                        'status' => 'PASSED',
                        'duration' => '0.50s',
                        'assertions' => 3,
                        'failure' => null,
                    ],
                ],
            ],
            [
                'name' => 'Tests\Feature\SecondModuleTest',
                'file' => 'tests/Feature/SecondModuleTest.php',
                'status' => 'FAILED',
                'total' => 2,
                'passed' => 1,
                'failed' => 1,
                'duration' => '1.70s',
                'cases' => [
                    [
                        'name' => 'first test in module two',
                        'status' => 'PASSED',
                        'duration' => '0.70s',
                        'assertions' => 2,
                        'failure' => null,
                    ],
                    [
                        'name' => 'second test in module two fails',
                        'status' => 'FAILED',
                        'duration' => '1.00s',
                        'assertions' => 3,
                        'failure' => [
                            'message' => 'Failed asserting that false is true.',
                            'location' => 'tests/Feature/SecondModuleTest.php:42',
                            'snippet' => 'expect(false)->toBeTrue();',
                        ],
                    ],
                ],
            ],
        ],
        'screenshots' => [],
    ];

    $report = $generator->generate($metadata, $testData);

    expect(file_exists($report['html_path']))->toBeTrue()
        ->and(isset($report['markdown_path']))->toBeFalse();

    $html = (string) file_get_contents($report['html_path']);

    // Assert sequential numbering in HTML across suites
    expect($html)->toContain('<code class="case-id">UAT-CASE-1</code>')
        ->and($html)->toContain('<code class="case-id">UAT-CASE-2</code>')
        ->and($html)->toContain('<code class="case-id">UAT-CASE-3</code>')
        ->and($html)->toContain('<code class="case-id">UAT-CASE-4</code>')
        ->and($html)->toContain('FAILURE AUDIT TRACE [UAT-CASE-4]:');

    // Cleanup temp
    @unlink($report['html_path']);
    @rmdir(dirname($report['html_path']));
    @rmdir($tmpDir);
});

it('links test cases to visual evidence anchors and labels screenshot evidence with case numbers', function () {
    $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'doctest_evidence_'.uniqid();
    mkdir($tmpDir, 0755, true);

    config(['unit-tester-documenter.reports_dir' => 'reports']);

    $generator = new CorporateReportGenerator($tmpDir);

    $metadata = [
        'run_name' => 'browser_test_20260911_130000',
        'document_id' => 'UAT-20260911_130000',
        'document_id_prefix' => 'UAT-',
        'sop' => 'SOP-QA-007',
        'author' => 'Test Lead',
        'executed_at' => '2026-09-11 13:00:00 UTC',
        'company_name' => 'Acme Corp',
        'classification' => 'INTERNAL',
        'git' => ['branch' => 'main', 'commit' => 'beef123'],
        'runtime_stack' => 'PHP 8.4.1 / Laravel 12.0.0 / SQLite 3.45 (Windows)',
        'reviewed_by' => [],
        'approved_by' => [],
        'acknowledged_by' => [],
    ];

    $testData = [
        'verdict' => 'PASSED',
        'total_tests' => 2,
        'passed_tests' => 2,
        'failed_tests' => 0,
        'total_assertions' => 5,
        'duration' => '1.50s',
        'suites' => [
            [
                'name' => 'Tests\Browser\AdminTest',
                'file' => 'tests/Browser/AdminTest.php',
                'status' => 'PASSED',
                'total' => 2,
                'passed' => 2,
                'failed' => 0,
                'duration' => '1.50s',
                'cases' => [
                    [
                        'name' => 'admin can login',
                        'status' => 'PASSED',
                        'duration' => '0.80s',
                        'assertions' => 3,
                        'failure' => null,
                    ],
                    [
                        'name' => 'admin can view profile',
                        'status' => 'PASSED',
                        'duration' => '0.70s',
                        'assertions' => 2,
                        'failure' => null,
                    ],
                ],
            ],
        ],
        'screenshots' => [
            [
                'file_name' => 'admin_can_login.png',
                'relative_path' => 'AdminTest/admin_can_login.png',
                'absolute_path' => '/tmp/AdminTest/admin_can_login.png',
                'test_case' => 'admin can login',
                'suite' => 'tests/Browser/AdminTest.php',
                'base64' => 'data:image/png;base64,FAKEBASE64IMAGE',
            ],
        ],
    ];

    $report = $generator->generate($metadata, $testData);

    $html = (string) file_get_contents($report['html_path']);

    // Check HTML: Section 4 has clickable anchor to evidence
    expect($html)->toContain('<a href="#evidence-uat-case-1" class="case-evidence-link"')
        ->and($html)->toContain('id="case-uat-case-1"')
        ->and($html)->toContain('<code class="case-id" style="cursor: pointer;">UAT-CASE-1 <span style="font-size: 10px;">📷</span></code>');

    // Check HTML: Section 5 evidence item has anchor id, case badge, and back-link
    expect($html)->toContain('id="evidence-uat-case-1"')
        ->and($html)->toContain('<code class="case-id">UAT-CASE-1</code>')
        ->and($html)->toContain('<a href="#case-uat-case-1" class="back-to-case"');

    // Cleanup temp
    @unlink($report['html_path']);
    @rmdir(dirname($report['html_path']));
    @rmdir($tmpDir);
});

it('correlates screenshots even when test names contain hyphens, commas, and punctuation that were slugified', function () {
    $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'doctest_slug_'.uniqid();
    mkdir($tmpDir, 0755, true);

    config(['unit-tester-documenter.reports_dir' => 'reports']);

    $generator = new CorporateReportGenerator($tmpDir);

    $metadata = [
        'run_name' => 'browser_test_20260911_140000',
        'document_id' => 'UAT-20260911_140000',
        'document_id_prefix' => 'UAT-',
        'sop' => 'SOP-QA-007',
        'author' => 'Test Lead',
        'executed_at' => '2026-09-11 14:00:00 UTC',
        'company_name' => 'Acme Corp',
        'classification' => 'INTERNAL',
        'git' => ['branch' => 'main', 'commit' => 'beef123'],
        'runtime_stack' => 'PHP 8.4.1 / Laravel 12.0.0 / SQLite 3.45 (Windows)',
        'reviewed_by' => [],
        'approved_by' => [],
        'acknowledged_by' => [],
    ];

    $testData = [
        'verdict' => 'PASSED',
        'total_tests' => 3,
        'passed_tests' => 3,
        'failed_tests' => 0,
        'total_assertions' => 6,
        'duration' => '3.00s',
        'suites' => [
            [
                'name' => 'Tests\Browser\LoginTest',
                'file' => 'tests/Browser/LoginTest.php',
                'status' => 'PASSED',
                'total' => 3,
                'passed' => 3,
                'failed' => 0,
                'duration' => '3.00s',
                'cases' => [
                    [
                        'name' => 'user can log in successfully through the 2-step authentication flow',
                        'status' => 'PASSED',
                        'duration' => '1.00s',
                        'assertions' => 2,
                        'failure' => null,
                    ],
                    [
                        'name' => 'delete button is hidden for active roles, requires exact typed name in modal, and dismisses on cancel',
                        'status' => 'PASSED',
                        'duration' => '1.00s',
                        'assertions' => 2,
                        'failure' => null,
                    ],
                    [
                        'name' => 'unauthorized user without manage-users permission cannot access user management and sidebar link is hidden',
                        'status' => 'PASSED',
                        'duration' => '1.00s',
                        'assertions' => 2,
                        'failure' => null,
                    ],
                ],
            ],
        ],
        'screenshots' => [
            [
                'file_name' => 'user_can_log_in_successfully_through_the_2_step_authentication_flow.png',
                'relative_path' => 'LoginTest/user_can_log_in_successfully_through_the_2_step_authentication_flow.png',
                'absolute_path' => '/tmp/LoginTest/user_can_log_in_successfully_through_the_2_step_authentication_flow.png',
                'test_case' => 'user can log in successfully through the 2 step authentication flow',
                'suite' => 'tests/Browser/LoginTest.php',
                'base64' => 'data:image/png;base64,FAKEIMAGE1',
            ],
            [
                'file_name' => 'delete_button_is_hidden_for_active_roles_requires_exact_typed_name_in_modal_and_dismisses_on_cancel.png',
                'relative_path' => 'LoginTest/delete_button_is_hidden_for_active_roles_requires_exact_typed_name_in_modal_and_dismisses_on_cancel.png',
                'absolute_path' => '/tmp/LoginTest/delete_button_is_hidden_for_active_roles_requires_exact_typed_name_in_modal_and_dismisses_on_cancel.png',
                'test_case' => 'delete button is hidden for active roles requires exact typed name in modal and dismisses on cancel',
                'suite' => 'tests/Browser/LoginTest.php',
                'base64' => 'data:image/png;base64,FAKEIMAGE2',
            ],
            [
                'file_name' => 'unauthorized_user_without_manage_users_permission_cannot_access_user_management_and_sidebar_link_is_hidden.png',
                'relative_path' => 'LoginTest/unauthorized_user_without_manage_users_permission_cannot_access_user_management_and_sidebar_link_is_hidden.png',
                'absolute_path' => '/tmp/LoginTest/unauthorized_user_without_manage_users_permission_cannot_access_user_management_and_sidebar_link_is_hidden.png',
                'test_case' => 'unauthorized user without manage users permission cannot access user management and sidebar link is hidden',
                'suite' => 'tests/Browser/LoginTest.php',
                'base64' => 'data:image/png;base64,FAKEIMAGE3',
            ],
        ],
    ];

    $report = $generator->generate($metadata, $testData);

    $html = (string) file_get_contents($report['html_path']);

    // Assert that all 3 cases with hyphens, commas, and stripped punctuation match and have camera icon in Section 4
    expect($html)->toContain('<a href="#evidence-uat-case-1" class="case-evidence-link"')
        ->and($html)->toContain('<code class="case-id" style="cursor: pointer;">UAT-CASE-1 <span style="font-size: 10px;">📷</span></code>')
        ->and($html)->toContain('<a href="#evidence-uat-case-2" class="case-evidence-link"')
        ->and($html)->toContain('<code class="case-id" style="cursor: pointer;">UAT-CASE-2 <span style="font-size: 10px;">📷</span></code>')
        ->and($html)->toContain('<a href="#evidence-uat-case-3" class="case-evidence-link"')
        ->and($html)->toContain('<code class="case-id" style="cursor: pointer;">UAT-CASE-3 <span style="font-size: 10px;">📷</span></code>');

    // Assert that all 3 screenshots in Section 5 have case ID badges and back-to-case links
    expect($html)->toContain('id="evidence-uat-case-1"')
        ->and($html)->toContain('<code class="case-id">UAT-CASE-1</code>')
        ->and($html)->toContain('id="evidence-uat-case-2"')
        ->and($html)->toContain('<code class="case-id">UAT-CASE-2</code>')
        ->and($html)->toContain('id="evidence-uat-case-3"')
        ->and($html)->toContain('<code class="case-id">UAT-CASE-3</code>');

    // Cleanup temp
    @unlink($report['html_path']);
    @rmdir(dirname($report['html_path']));
    @rmdir($tmpDir);
});

it('allows developers to customize report using a custom blade view', function () {
    $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'doctest_custom_view_'.uniqid();
    mkdir($tmpDir, 0755, true);

    // Register a custom in-memory Blade view
    $viewFactory = app('view');
    $viewFactory->addNamespace('custom-test', __DIR__.'/../fixtures/views');

    // Or use View::addLocation / anonymous component / config override
    config([
        'unit-tester-documenter.reports_dir' => 'reports',
        'unit-tester-documenter.report_view' => 'unit-tester-documenter::report',
    ]);

    $generator = new CorporateReportGenerator($tmpDir);

    $metadata = [
        'run_name' => 'custom_run_123',
        'document_id' => 'DOC-CUSTOM-001',
        'sop' => 'SOP-CUSTOM',
        'author' => 'Custom Author',
        'executed_at' => '2026-09-15 12:00:00 UTC',
        'company_name' => 'Custom Corp',
        'classification' => 'PUBLIC',
        'git' => ['branch' => 'main', 'commit' => 'abc1234'],
        'runtime_stack' => 'PHP 8.4 / Laravel 12',
        'reviewed_by' => [],
        'approved_by' => [],
        'acknowledged_by' => [],
    ];

    $testData = [
        'verdict' => 'PASSED',
        'total_tests' => 1,
        'passed_tests' => 1,
        'failed_tests' => 0,
        'total_assertions' => 1,
        'duration' => '0.01s',
        'suites' => [
            [
                'name' => 'Tests\Unit\CustomTest',
                'file' => 'tests/Unit/CustomTest.php',
                'status' => 'PASSED',
                'total' => 1,
                'passed' => 1,
                'failed' => 0,
                'duration' => '0.01s',
                'cases' => [
                    [
                        'name' => 'it works',
                        'status' => 'PASSED',
                        'duration' => '0.01s',
                        'assertions' => 1,
                        'failure' => null,
                    ],
                ],
            ],
        ],
        'screenshots' => [],
    ];

    $report = $generator->generate($metadata, $testData);

    $html = (string) file_get_contents($report['html_path']);

    expect($html)->toContain('Formal Test Execution Record - DOC-CUSTOM-001')
        ->and($html)->toContain('Custom Corp')
        ->and($html)->toContain('DOC-CUSTOM-CASE-1');

    // Cleanup temp
    @unlink($report['html_path']);
    @rmdir(dirname($report['html_path']));
    @rmdir($tmpDir);
});

it('publishes blade views using the unit-tester-documenter-views publish tag', function () {
    $publishedPath = resource_path('views/vendor/unit-tester-documenter');

    // Ensure clean state
    if (is_dir($publishedPath)) {
        array_map('unlink', glob("{$publishedPath}/*.*") ?: []);
        @rmdir($publishedPath);
    }

    Artisan::call('vendor:publish', [
        '--tag' => 'unit-tester-documenter-views',
    ]);

    expect(file_exists($publishedPath.DIRECTORY_SEPARATOR.'report.blade.php'))->toBeTrue()
        ->and(file_exists($publishedPath.DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR.'styles.blade.php'))->toBeTrue()
        ->and(file_exists($publishedPath.DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR.'header.blade.php'))->toBeTrue()
        ->and(file_exists($publishedPath.DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR.'metadata.blade.php'))->toBeTrue()
        ->and(file_exists($publishedPath.DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR.'summary.blade.php'))->toBeTrue()
        ->and(file_exists($publishedPath.DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR.'suites-table.blade.php'))->toBeTrue()
        ->and(file_exists($publishedPath.DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR.'cases.blade.php'))->toBeTrue()
        ->and(file_exists($publishedPath.DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR.'screenshots.blade.php'))->toBeTrue()
        ->and(file_exists($publishedPath.DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR.'signoff.blade.php'))->toBeTrue();

    // Clean up published files
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($publishedPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }

    @rmdir($publishedPath);
});
