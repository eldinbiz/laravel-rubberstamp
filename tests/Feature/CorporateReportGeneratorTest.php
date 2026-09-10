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

it('generates corporate html and markdown reports with strict omission of unpassed sign-off categories', function () {
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
        ->and(file_exists($report['markdown_path']))->toBeTrue();

    $html = (string) file_get_contents($report['html_path']);
    $markdown = (string) file_get_contents($report['markdown_path']);

    // Check HTML contents
    expect($html)->toContain('Formal Test Execution Record')
        ->and($html)->toContain('DOC-TEST-20260910-102030')
        ->and($html)->toContain('SOP-QA-999')
        ->and($html)->toContain('Eldin Akbar')
        ->and($html)->toContain('Mr Smith')
        ->and($html)->toContain('QA Head')
        ->and($html)->toContain('Prepared By:')
        ->and($html)->toContain('Reviewed By:')
        // Assert omission of unpassed categories:
        ->and($html)->not->toContain('Approved By:')
        ->and($html)->not->toContain('Acknowledged By:');

    // Check Markdown contents
    expect($markdown)->toContain('# Formal Test Execution Record')
        ->and($markdown)->toContain('DOC-TEST-20260910-102030')
        ->and($markdown)->toContain('SOP-QA-999')
        ->and($markdown)->toContain('### Prepared By')
        ->and($markdown)->toContain('### Reviewed By')
        ->and($markdown)->toContain('Mr Smith')
        ->and($markdown)->toContain('QA Head')
        // Assert omission of unpassed categories:
        ->and($markdown)->not->toContain('### Approved By')
        ->and($markdown)->not->toContain('### Acknowledged By');

    // Cleanup temp
    @unlink($report['html_path']);
    @unlink($report['markdown_path']);
    @rmdir(dirname($report['html_path']));
    @rmdir($tmpDir);
});
