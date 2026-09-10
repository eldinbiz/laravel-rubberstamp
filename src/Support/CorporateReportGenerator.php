<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Support;

final class CorporateReportGenerator
{
    /**
     * Create a new corporate report generator instance.
     */
    public function __construct(
        private readonly ?string $basePath = null,
    ) {}

    /**
     * Generate both HTML and Markdown corporate reports and save to disk.
     *
     * @param  array{
     *     run_name: string,
     *     document_id: string,
     *     sop: string,
     *     author: string,
     *     executed_at: string,
     *     company_name: string,
     *     classification: string,
     *     git: array{branch: string, commit: string},
     *     runtime_stack: string,
     *     reviewed_by: array<int, array{name: string, role: string}>,
     *     approved_by: array<int, array{name: string, role: string}>,
     *     acknowledged_by: array<int, array{name: string, role: string}>,
     * }  $metadata
     * @param  array{
     *     verdict: string,
     *     total_tests: int,
     *     passed_tests: int,
     *     failed_tests: int,
     *     total_assertions: int,
     *     duration: string,
     *     suites: array<int, array{
     *         name: string,
     *         file: string,
     *         status: string,
     *         total: int,
     *         passed: int,
     *         failed: int,
     *         duration: string,
     *         cases: array<int, array{
     *             name: string,
     *             status: string,
     *             duration: string,
     *             assertions: int,
     *             failure: array{message: string, location: string, snippet: string}|null
     *         }>
     *     }>,
     *     screenshots: array<int, array{
     *         file_name: string,
     *         relative_path: string,
     *         absolute_path: string,
     *         test_case: string,
     *         suite: string,
     *         base64: string,
     *     }>
     * }  $testData
     * @return array{
     *     run_name: string,
     *     document_id: string,
     *     html_path: string,
     *     markdown_path: string,
     * }
     */
    public function generate(array $metadata, array $testData): array
    {
        $baseDir = $this->basePath ?? (function_exists('base_path') ? base_path() : getcwd());
        $reportsSubdir = (string) config('unit-tester-documenter.reports_dir', 'doctest-reports');
        $outputDir = rtrim((string) $baseDir, '/\\').DIRECTORY_SEPARATOR.trim($reportsSubdir, '/\\');

        if (! is_dir($outputDir)) {
            @mkdir($outputDir, 0755, true);
        }

        $runName = $metadata['run_name'];
        $htmlFile = $outputDir.DIRECTORY_SEPARATOR."{$runName}.html";
        $markdownFile = $outputDir.DIRECTORY_SEPARATOR."{$runName}.md";

        $htmlContent = $this->buildHtml($metadata, $testData);
        $markdownContent = $this->buildMarkdown($metadata, $testData);

        file_put_contents($htmlFile, $htmlContent);
        file_put_contents($markdownFile, $markdownContent);

        return [
            'run_name' => $runName,
            'document_id' => $metadata['document_id'],
            'html_path' => $htmlFile,
            'markdown_path' => $markdownFile,
        ];
    }

    /**
     * Build print-ready standalone HTML report.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $data
     */
    public function buildHtml(array $meta, array $data): string
    {
        $isPassed = $data['verdict'] === 'PASSED';
        $verdictClass = $isPassed ? 'status-passed' : 'status-failed';
        $verdictBg = $isPassed ? '#10b981' : '#ef4444';

        $logoHtml = $this->renderLogoHtml();
        $suitesRows = $this->renderHtmlSuitesRows($data['suites'] ?? []);
        $detailedCasesHtml = $this->renderHtmlDetailedCases($data['suites'] ?? []);
        $screenshotsHtml = $this->renderHtmlScreenshots($data['screenshots'] ?? []);
        $signoffHtml = $this->renderHtmlSignoff($meta);

        $docId = htmlspecialchars((string) $meta['document_id'], ENT_QUOTES, 'UTF-8');
        $sop = htmlspecialchars((string) $meta['sop'], ENT_QUOTES, 'UTF-8');
        $author = htmlspecialchars((string) $meta['author'], ENT_QUOTES, 'UTF-8');
        $executedAt = htmlspecialchars((string) $meta['executed_at'], ENT_QUOTES, 'UTF-8');
        $company = htmlspecialchars((string) $meta['company_name'], ENT_QUOTES, 'UTF-8');
        $classification = htmlspecialchars((string) $meta['classification'], ENT_QUOTES, 'UTF-8');
        $branch = htmlspecialchars((string) ($meta['git']['branch'] ?? 'main'), ENT_QUOTES, 'UTF-8');
        $commit = htmlspecialchars((string) ($meta['git']['commit'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
        $stack = htmlspecialchars((string) $meta['runtime_stack'], ENT_QUOTES, 'UTF-8');

        $totalTests = (int) ($data['total_tests'] ?? 0);
        $passedTests = (int) ($data['passed_tests'] ?? 0);
        $failedTests = (int) ($data['failed_tests'] ?? 0);
        $totalAssertions = (int) ($data['total_assertions'] ?? 0);
        $duration = htmlspecialchars((string) ($data['duration'] ?? '0.00s'), ENT_QUOTES, 'UTF-8');
        $suitesCount = count($data['suites'] ?? []);
        $passRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formal Test Execution Record - {$docId}</title>
    <style>
        :root {
            --primary: #1e293b;
            --primary-light: #f8fafc;
            --border: #cbd5e1;
            --border-light: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--text-main);
            background-color: #f1f5f9;
            padding: 30px 15px;
            font-size: 13px;
            line-height: 1.5;
        }

        .report-sheet {
            max-width: 1020px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            padding: 40px;
        }

        .header-grid {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .header-title-box h1 {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--primary);
            text-transform: uppercase;
        }

        .header-title-box p {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .section-header {
            background-color: #f8fafc;
            border-left: 4px solid var(--primary);
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--primary);
            margin-top: 30px;
            margin-bottom: 12px;
        }

        .meta-table, .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .meta-table td {
            border: 1px solid var(--border-light);
            padding: 7px 10px;
            font-size: 12px;
        }

        .meta-label {
            background-color: #f8fafc;
            font-weight: 600;
            color: var(--text-muted);
            width: 16%;
        }

        .meta-val {
            font-weight: 500;
            width: 34%;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .metric-card {
            border: 1px solid var(--border-light);
            border-radius: 6px;
            padding: 12px;
            background: #ffffff;
            text-align: center;
        }

        .metric-card.verdict {
            background-color: {$verdictBg};
            color: #ffffff;
            border-color: {$verdictBg};
        }

        .metric-card.verdict .metric-title {
            color: rgba(255, 255, 255, 0.9);
        }

        .metric-card.verdict .metric-number {
            color: #ffffff;
        }

        .metric-title {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 600;
            color: var(--text-muted);
        }

        .metric-number {
            font-size: 20px;
            font-weight: 800;
            margin-top: 4px;
            color: var(--primary);
        }

        .data-table th {
            background-color: #f1f5f9;
            border: 1px solid var(--border);
            padding: 8px 10px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--primary);
        }

        .data-table td {
            border: 1px solid var(--border-light);
            padding: 8px 10px;
            font-size: 12px;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .badge-passed {
            background-color: #dcfce7;
            color: #15803d;
        }

        .badge-failed {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .suite-box {
            border: 1px solid var(--border-light);
            border-radius: 6px;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .suite-header {
            background: #f8fafc;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 12px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
        }

        .case-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12px;
        }

        .case-row:last-child {
            border-bottom: none;
        }

        .failure-trace {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: 4px;
            padding: 10px;
            margin: 8px 12px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 11px;
            color: #9f1239;
            white-space: pre-wrap;
        }

        .screenshot-item {
            border: 1px solid var(--border-light);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 15px;
            background: #fafafa;
        }

        .screenshot-meta {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .screenshot-img {
            max-width: 100%;
            height: auto;
            border: 1px solid var(--border);
            border-radius: 4px;
            display: block;
        }

        .signoff-section {
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .signoff-category-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--primary);
            margin: 15px 0 6px 0;
        }

        .signoff-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .signoff-table th {
            background-color: #f8fafc;
            border: 1px solid var(--border);
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            text-align: left;
            text-transform: uppercase;
        }

        .signoff-table td {
            border: 1px solid var(--border-light);
            padding: 10px 10px;
            font-size: 12px;
            vertical-align: middle;
        }

        .signature-line {
            display: inline-block;
            min-width: 140px;
            border-bottom: 1px dashed var(--border);
            color: var(--text-muted);
            font-style: italic;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .report-sheet {
                border: none;
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }

            .signoff-section, .suite-box, .screenshot-item {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="report-sheet">
        <div class="header-grid">
            <div class="header-title-box">
                <h1>Formal Test Execution Record</h1>
                <p>Quality Assurance & Compliance Audit Evidence | {$company}</p>
            </div>
            <div class="header-logo-box">
                {$logoHtml}
            </div>
        </div>

        <div class="section-header">1. Document Control & Audit Metadata</div>
        <table class="meta-table">
            <tr>
                <td class="meta-label">Document ID</td>
                <td class="meta-val"><code>{$docId}</code></td>
                <td class="meta-label">SOP Reference</td>
                <td class="meta-val"><code>{$sop}</code></td>
            </tr>
            <tr>
                <td class="meta-label">Author (Tester)</td>
                <td class="meta-val">{$author}</td>
                <td class="meta-label">Executed At</td>
                <td class="meta-val">{$executedAt}</td>
            </tr>
            <tr>
                <td class="meta-label">Git Branch</td>
                <td class="meta-val"><code>{$branch}</code></td>
                <td class="meta-label">Git Commit</td>
                <td class="meta-val"><code>{$commit}</code></td>
            </tr>
            <tr>
                <td class="meta-label">Classification</td>
                <td class="meta-val">{$classification}</td>
                <td class="meta-label">Environment</td>
                <td class="meta-val"><code>testing</code></td>
            </tr>
            <tr>
                <td class="meta-label">Runtime Stack</td>
                <td class="meta-val" colspan="3"><code>{$stack}</code></td>
            </tr>
        </table>

        <div class="section-header">2. Executive Test Verdict & Summary</div>
        <div class="metrics-grid">
            <div class="metric-card verdict">
                <div class="metric-title">Audit Verdict</div>
                <div class="metric-number">{$data['verdict']} ({$passRate}%)</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Total Tests</div>
                <div class="metric-number">{$totalTests}</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Passed / Failed</div>
                <div class="metric-number"><span style="color:#10b981;">{$passedTests}</span> / <span style="color:#ef4444;">{$failedTests}</span></div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Assertions / Duration</div>
                <div class="metric-number" style="font-size:16px;">{$totalAssertions} / {$duration}</div>
            </div>
        </div>

        <div class="section-header">3. Test Suites Summary Breakdown</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 55%;">Test Suite (File Path)</th>
                    <th style="text-align: center;">Tests</th>
                    <th style="text-align: center;">Passed</th>
                    <th style="text-align: center;">Failed</th>
                    <th style="text-align: center;">Duration</th>
                    <th style="text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                {$suitesRows}
            </tbody>
        </table>

        <div class="section-header">4. Detailed Test Case Execution Evidence</div>
        {$detailedCasesHtml}

        {$screenshotsHtml}

        <div class="signoff-section">
            <div class="section-header">6. Formal Sign-Off Approval Sheet</div>
            {$signoffHtml}
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build standard corporate Markdown document.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $data
     */
    public function buildMarkdown(array $meta, array $data): string
    {
        $docId = (string) $meta['document_id'];
        $sop = (string) $meta['sop'];
        $author = (string) $meta['author'];
        $executedAt = (string) $meta['executed_at'];
        $company = (string) $meta['company_name'];
        $classification = (string) $meta['classification'];
        $branch = (string) ($meta['git']['branch'] ?? 'main');
        $commit = (string) ($meta['git']['commit'] ?? 'N/A');
        $stack = (string) $meta['runtime_stack'];

        $totalTests = (int) ($data['total_tests'] ?? 0);
        $passedTests = (int) ($data['passed_tests'] ?? 0);
        $failedTests = (int) ($data['failed_tests'] ?? 0);
        $totalAssertions = (int) ($data['total_assertions'] ?? 0);
        $duration = (string) ($data['duration'] ?? '0.00s');
        $suitesCount = count($data['suites'] ?? []);
        $passRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
        $verdict = (string) ($data['verdict'] ?? 'UNKNOWN');

        $out = [];
        $out[] = '# Formal Test Execution Record';
        $out[] = '### Quality Assurance & Compliance Audit Evidence';
        $out[] = "**System:** {$company}  ";
        $out[] = '**Organization / Logo:** `[ COMPANY LOGO ]`';
        $out[] = '';
        $out[] = '---';
        $out[] = '';
        $out[] = '## 1. Document Control & Audit Metadata';
        $out[] = '';
        $out[] = '| Field | Value | Field | Value |';
        $out[] = '| :--- | :--- | :--- | :--- |';
        $out[] = "| **Document ID** | `{$docId}` | **SOP Reference** | `{$sop}` |";
        $out[] = "| **Author (Tester)** | {$author} | **Execution Timestamp** | `{$executedAt}` |";
        $out[] = "| **Git Branch** | `{$branch}` | **Git Commit** | `{$commit}` |";
        $out[] = "| **Environment** | `testing` | **Classification** | `{$classification}` |";
        $out[] = "| **Runtime Stack** | `{$stack}` | | |";
        $out[] = '';
        $out[] = '---';
        $out[] = '';
        $out[] = '## 2. Executive Test Verdict & Summary';
        $out[] = '';
        $out[] = '| Metric | Result | Metric | Result |';
        $out[] = '| :--- | :--- | :--- | :--- |';
        $out[] = "| **Status Verdict** | **{$verdict} ({$passRate}%)** | **Total Tests** | {$totalTests} |";
        $out[] = "| **Passed Tests** | {$passedTests} | **Failed Tests** | {$failedTests} |";
        $out[] = "| **Total Assertions** | {$totalAssertions} | **Execution Time** | {$duration} |";
        $out[] = "| **Total Test Suites** | {$suitesCount} | **Audit Compliance** | ".($verdict === 'PASSED' ? 'ACCEPTED' : 'REJECTED').' |';
        $out[] = '';
        $out[] = '---';
        $out[] = '';
        $out[] = '## 3. Test Suites Summary Breakdown';
        $out[] = '';
        $out[] = '| Test Suite (File Path) | Tests | Passed | Failed | Duration | Status |';
        $out[] = '| :--- | :---: | :---: | :---: | :---: | :---: |';

        foreach ($data['suites'] ?? [] as $s) {
            $file = (string) $s['file'];
            $t = (int) $s['total'];
            $p = (int) $s['passed'];
            $f = (int) $s['failed'];
            $d = (string) $s['duration'];
            $st = (string) $s['status'];
            $out[] = "| `{$file}` | {$t} | {$p} | {$f} | {$d} | `{$st}` |";
        }

        $out[] = '';
        $out[] = '---';
        $out[] = '';
        $out[] = '## 4. Detailed Test Case Execution Evidence';
        $out[] = '';

        foreach ($data['suites'] ?? [] as $suite) {
            $suiteFile = (string) $suite['file'];
            $out[] = "### Suite: `{$suiteFile}`";

            foreach ($suite['cases'] ?? [] as $c) {
                $statusIcon = $c['status'] === 'PASSED' ? '[x] **PASSED**' : '[ ] **FAILED**';
                $caseName = (string) $c['name'];
                $caseDuration = (string) $c['duration'];
                $assertions = (int) ($c['assertions'] ?? 1);
                $out[] = "- {$statusIcon} `{$caseName}` *({$assertions} assertions, {$duration})*";

                if ($c['status'] === 'FAILED' && ! empty($c['failure'])) {
                    $msg = trim((string) $c['failure']['message']);
                    $loc = trim((string) $c['failure']['location']);
                    $snip = trim((string) $c['failure']['snippet']);

                    $out[] = '';
                    $out[] = '> **FAILURE AUDIT TRACE**';
                    $out[] = "> - **Error:** {$msg}";
                    $out[] = "> - **Location:** `{$loc}`";

                    if ($snip !== '') {
                        $out[] = '> ```';
                        foreach (explode("\n", $snip) as $snipLine) {
                            $out[] = "> {$snipLine}";
                        }
                        $out[] = '> ```';
                    }
                    $out[] = '';
                }
            }
            $out[] = '';
        }

        if (! empty($data['screenshots'])) {
            $out[] = '---';
            $out[] = '';
            $out[] = '## 5. Browser Test Visual Evidence & Screenshot Gallery';
            $out[] = '';

            foreach ($data['screenshots'] as $shot) {
                $testCase = (string) $shot['test_case'];
                $suite = (string) $shot['suite'];
                $relPath = (string) $shot['relative_path'];
                $out[] = "### Test: `{$suite}` &rarr; `{$testCase}`";
                $out[] = "- **Snapshot File:** `{$relPath}`";
                $out[] = "! [{$testCase}]({$relPath})";
                $out[] = '';
            }
        }

        $out[] = '---';
        $out[] = '';
        $out[] = '## 6. Formal Sign-Off Approval Sheet';
        $out[] = '';
        $out[] = '### Prepared By';
        $out[] = '| Name | Date | Role | Signature |';
        $out[] = '| :--- | :--- | :--- | :--- |';
        $execDate = substr((string) $meta['executed_at'], 0, 10);
        $out[] = "| {$author} | {$execDate} | Developer (Test Executor) | Verified System Log |";
        $out[] = '';

        if (! empty($meta['reviewed_by'])) {
            $out[] = '### Reviewed By';
            $out[] = '| Name | Date | Role | Signature |';
            $out[] = '| :--- | :--- | :--- | :--- |';
            foreach ($meta['reviewed_by'] as $row) {
                $name = $row['name'] !== '' ? $row['name'] : '________________________';
                $role = $row['role'];
                $out[] = "| {$name} | ____________ | {$role} | ________________________ |";
            }
            $out[] = '';
        }

        if (! empty($meta['approved_by'])) {
            $out[] = '### Approved By';
            $out[] = '| Name | Date | Role | Signature |';
            $out[] = '| :--- | :--- | :--- | :--- |';
            foreach ($meta['approved_by'] as $row) {
                $name = $row['name'] !== '' ? $row['name'] : '________________________';
                $role = $row['role'];
                $out[] = "| {$name} | ____________ | {$role} | ________________________ |";
            }
            $out[] = '';
        }

        if (! empty($meta['acknowledged_by'])) {
            $out[] = '### Acknowledged By';
            $out[] = '| Name | Date | Role | Signature |';
            $out[] = '| :--- | :--- | :--- | :--- |';
            foreach ($meta['acknowledged_by'] as $row) {
                $name = $row['name'] !== '' ? $row['name'] : '________________________';
                $role = $row['role'];
                $out[] = "| {$name} | ____________ | {$role} | ________________________ |";
            }
            $out[] = '';
        }

        return implode("\n", $out);
    }

    /**
     * Render HTML suites breakdown table rows.
     *
     * @param  array<int, array<string, mixed>>  $suites
     */
    private function renderHtmlSuitesRows(array $suites): string
    {
        if (empty($suites)) {
            return '<tr><td colspan="6" style="text-align: center; color: #94a3b8;">No test suites recorded.</td></tr>';
        }

        $html = '';
        foreach ($suites as $s) {
            $file = htmlspecialchars((string) $s['file'], ENT_QUOTES, 'UTF-8');
            $total = (int) $s['total'];
            $passed = (int) $s['passed'];
            $failed = (int) $s['failed'];
            $duration = htmlspecialchars((string) $s['duration'], ENT_QUOTES, 'UTF-8');
            $status = (string) $s['status'];
            $badgeClass = $status === 'PASSED' ? 'badge-passed' : 'badge-failed';

            $html .= <<<HTML
            <tr>
                <td><code>{$file}</code></td>
                <td style="text-align: center;">{$total}</td>
                <td style="text-align: center; color: #10b981; font-weight: 600;">{$passed}</td>
                <td style="text-align: center; color: #ef4444; font-weight: 600;">{$failed}</td>
                <td style="text-align: center; color: #64748b;">{$duration}</td>
                <td style="text-align: center;"><span class="badge {$badgeClass}">{$status}</span></td>
            </tr>
HTML;
        }

        return $html;
    }

    /**
     * Render detailed test cases HTML.
     *
     * @param  array<int, array<string, mixed>>  $suites
     */
    private function renderHtmlDetailedCases(array $suites): string
    {
        if (empty($suites)) {
            return '<p style="color:#64748b; font-size:12px;">No test cases recorded.</p>';
        }

        $html = '';
        foreach ($suites as $suite) {
            $suiteFile = htmlspecialchars((string) $suite['file'], ENT_QUOTES, 'UTF-8');
            $status = (string) $suite['status'];
            $badgeClass = $status === 'PASSED' ? 'badge-passed' : 'badge-failed';

            $casesHtml = '';
            foreach ($suite['cases'] ?? [] as $case) {
                $caseName = htmlspecialchars((string) $case['name'], ENT_QUOTES, 'UTF-8');
                $caseStatus = (string) $case['status'];
                $caseDuration = htmlspecialchars((string) $case['duration'], ENT_QUOTES, 'UTF-8');
                $caseBadge = $caseStatus === 'PASSED'
                    ? '<span class="badge badge-passed">✔ PASSED</span>'
                    : '<span class="badge badge-failed">✖ FAILED</span>';

                $failureHtml = '';

                if ($caseStatus === 'FAILED' && ! empty($case['failure'])) {
                    $fMsg = htmlspecialchars((string) $case['failure']['message'], ENT_QUOTES, 'UTF-8');
                    $fLoc = htmlspecialchars((string) $case['failure']['location'], ENT_QUOTES, 'UTF-8');
                    $fSnip = htmlspecialchars((string) $case['failure']['snippet'], ENT_QUOTES, 'UTF-8');
                    $failureHtml = <<<HTML
                    <div class="failure-trace">
<strong>FAILURE AUDIT TRACE:</strong>
Location: {$fLoc}
Error: {$fMsg}

{$fSnip}
                    </div>
HTML;
                }

                $casesHtml .= <<<HTML
                <div class="case-row">
                    <div>
                        {$caseBadge}
                        <span style="margin-left: 8px; font-weight: 500;">{$caseName}</span>
                    </div>
                    <div style="color: #64748b; font-size: 11px;">{$caseDuration}</div>
                </div>
                {$failureHtml}
HTML;
            }

            $html .= <<<HTML
            <div class="suite-box">
                <div class="suite-header">
                    <span><code>{$suiteFile}</code></span>
                    <span class="badge {$badgeClass}">{$status}</span>
                </div>
                {$casesHtml}
            </div>
HTML;
        }

        return $html;
    }

    /**
     * Render screenshots gallery HTML.
     *
     * @param  array<int, array<string, mixed>>  $screenshots
     */
    private function renderHtmlScreenshots(array $screenshots): string
    {
        if (empty($screenshots)) {
            return '';
        }

        $items = '';
        foreach ($screenshots as $shot) {
            $testCase = htmlspecialchars((string) $shot['test_case'], ENT_QUOTES, 'UTF-8');
            $suite = htmlspecialchars((string) $shot['suite'], ENT_QUOTES, 'UTF-8');
            $base64 = (string) $shot['base64'];

            $items .= <<<HTML
            <div class="screenshot-item">
                <div style="font-weight: 700; font-size: 12px; margin-bottom: 4px;">{$suite} &rarr; {$testCase}</div>
                <div class="screenshot-meta">Visual snapshot evidence captured during browser execution</div>
                <img src="{$base64}" alt="{$testCase}" class="screenshot-img" />
            </div>
HTML;
        }

        return <<<HTML
        <div class="section-header">5. Browser Test Visual Evidence & Screenshot Gallery</div>
        {$items}
HTML;
    }

    /**
     * Render Closing Formal Sign-Off Approval Sheet HTML.
     *
     * @param  array<string, mixed>  $meta
     */
    private function renderHtmlSignoff(array $meta): string
    {
        $author = htmlspecialchars((string) $meta['author'], ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars(substr((string) $meta['executed_at'], 0, 10), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
        <div class="signoff-category-title">Prepared By:</div>
        <table class="signoff-table">
            <thead>
                <tr>
                    <th style="width: 28%;">Name</th>
                    <th style="width: 18%;">Date</th>
                    <th style="width: 28%;">Role</th>
                    <th style="width: 26%;">Signature</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>{$author}</strong></td>
                    <td>{$date}</td>
                    <td>Developer (Test Executor)</td>
                    <td><span class="signature-line" style="border-bottom:none; color:#10b981; font-weight:700;">[VERIFIED SYSTEM LOG]</span></td>
                </tr>
            </tbody>
        </table>
HTML;

        if (! empty($meta['reviewed_by'])) {
            $html .= '<div class="signoff-category-title">Reviewed By:</div>';
            $html .= $this->renderSignoffTableRows($meta['reviewed_by']);
        }

        if (! empty($meta['approved_by'])) {
            $html .= '<div class="signoff-category-title">Approved By:</div>';
            $html .= $this->renderSignoffTableRows($meta['approved_by']);
        }

        if (! empty($meta['acknowledged_by'])) {
            $html .= '<div class="signoff-category-title">Acknowledged By:</div>';
            $html .= $this->renderSignoffTableRows($meta['acknowledged_by']);
        }

        return $html;
    }

    /**
     * Render sign-off table rows for a category.
     *
     * @param  array<int, array{name: string, role: string}>  $rows
     */
    private function renderSignoffTableRows(array $rows): string
    {
        $rowsHtml = '';
        foreach ($rows as $row) {
            $nameVal = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
            $roleVal = htmlspecialchars($row['role'], ENT_QUOTES, 'UTF-8');
            $nameCell = $nameVal !== '' ? "<strong>{$nameVal}</strong>" : '<span class="signature-line">________________________</span>';

            $rowsHtml .= <<<HTML
            <tr>
                <td>{$nameCell}</td>
                <td><span class="signature-line">__________</span></td>
                <td>{$roleVal}</td>
                <td><span class="signature-line">________________________</span></td>
            </tr>
HTML;
        }

        return <<<HTML
        <table class="signoff-table">
            <thead>
                <tr>
                    <th style="width: 28%;">Name</th>
                    <th style="width: 18%;">Date</th>
                    <th style="width: 28%;">Role</th>
                    <th style="width: 26%;">Signature</th>
                </tr>
            </thead>
            <tbody>
                {$rowsHtml}
            </tbody>
        </table>
HTML;
    }

    /**
     * Render clean SVG placeholder logo badge.
     */
    private function renderLogoHtml(): string
    {
        return <<<'HTML'
        <svg width="180" height="52" viewBox="0 0 180 52" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="180" height="52" rx="6" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="1.5"/>
            <rect x="8" y="8" width="36" height="36" rx="4" fill="#0F172A"/>
            <path d="M26 16L33 28H19L26 16Z" fill="#38BDF8"/>
            <text x="52" y="24" fill="#0F172A" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="12" font-weight="700">COMPANY LOGO</text>
            <text x="52" y="38" fill="#64748B" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="10" font-weight="500">Official Audit Record</text>
        </svg>
HTML;
    }
}
