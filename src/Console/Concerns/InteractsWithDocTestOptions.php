<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Console\Concerns;

use UnitTesterDocumenter\UnitTesterDocumenter\Support\AuditMetadataResolver;
use UnitTesterDocumenter\UnitTesterDocumenter\Support\CorporateReportGenerator;
use UnitTesterDocumenter\UnitTesterDocumenter\Support\PestLogParser;

trait InteractsWithDocTestOptions
{
    /**
     * Resolve documentation metadata either interactively or from CLI options.
     *
     * @return array{
     *     author: string,
     *     document_id_prefix: string,
     *     sop: string,
     *     reviewed_by: string|null,
     *     approved_by: string|null,
     *     acknowledged_by: string|null,
     * }
     */
    protected function resolveDocOptions(AuditMetadataResolver $resolver): array
    {
        $cliAuthor = is_string($this->option('author')) ? $this->option('author') : null;
        $cliDocId = is_string($this->option('document-id')) ? $this->option('document-id') : null;
        $cliSop = is_string($this->option('sop')) ? $this->option('sop') : null;
        $cliReviewed = is_string($this->option('reviewed-by')) ? $this->option('reviewed-by') : null;
        $cliApproved = is_string($this->option('approved-by')) ? $this->option('approved-by') : null;
        $cliAck = is_string($this->option('acknowledged-by')) ? $this->option('acknowledged-by') : null;

        $isInteractive = (bool) $this->option('interactive');

        if (! $isInteractive) {
            return [
                'author' => $resolver->resolveAuthor($cliAuthor),
                'document_id_prefix' => $cliDocId ?: (string) config('unit-tester-documenter.document_id_prefix', 'DOC-TEST-'),
                'sop' => $resolver->resolveSop($cliSop),
                'reviewed_by' => $cliReviewed,
                'approved_by' => $cliApproved,
                'acknowledged_by' => $cliAck,
            ];
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->line('<fg=cyan;options=bold>       Interactive Test Documentation & Audit Setup             </>');
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->newLine();

        $defaultAuthor = $resolver->resolveAuthor($cliAuthor);
        $authorInput = $this->ask('Tester / Author Name', $defaultAuthor);
        $author = is_string($authorInput) && trim($authorInput) !== '' ? trim($authorInput) : $defaultAuthor;

        $defaultPrefix = $cliDocId ?: (string) config('unit-tester-documenter.document_id_prefix', 'DOC-TEST-');
        $prefixInput = $this->ask('Document ID Prefix', $defaultPrefix);
        $docIdPrefix = is_string($prefixInput) && trim($prefixInput) !== '' ? trim($prefixInput) : $defaultPrefix;

        $defaultSop = $cliSop ?: 'N/A';
        $sopInput = $this->ask('SOP Reference Code (or N/A)', $defaultSop);
        $sop = is_string($sopInput) && trim($sopInput) !== '' ? trim($sopInput) : 'N/A';

        $reviewedInput = $this->ask('Reviewed By (e.g. "Mr Smith,QA Engineer|QA Head" or press Enter to skip)', $cliReviewed ?: '');
        $reviewedBy = is_string($reviewedInput) && trim($reviewedInput) !== '' ? trim($reviewedInput) : null;

        $approvedInput = $this->ask('Approved By (e.g. "Jane,QA Lead|Technical Lead" or press Enter to skip)', $cliApproved ?: '');
        $approvedBy = is_string($approvedInput) && trim($approvedInput) !== '' ? trim($approvedInput) : null;

        $ackInput = $this->ask('Acknowledged By (e.g. "Bob,Project Manager|Product Owner" or press Enter to skip)', $cliAck ?: '');
        $acknowledgedBy = is_string($ackInput) && trim($ackInput) !== '' ? trim($ackInput) : null;

        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->newLine();

        return [
            'author' => $author,
            'document_id_prefix' => $docIdPrefix,
            'sop' => $sop,
            'reviewed_by' => $reviewedBy,
            'approved_by' => $approvedBy,
            'acknowledged_by' => $acknowledgedBy,
        ];
    }

    /**
     * Compile and display corporate test documentation report.
     *
     * @param  array{
     *     author: string,
     *     document_id_prefix: string,
     *     sop: string,
     *     reviewed_by: string|null,
     *     approved_by: string|null,
     *     acknowledged_by: string|null,
     * }  $docOptions
     * @return array{
     *     run_name: string,
     *     document_id: string,
     *     html_path: string,
     * }|null
     */
    protected function generateAndRenderReport(
        string $runName,
        string $pestLogPath,
        ?string $snapshotDir,
        array $docOptions,
        string $timestamp,
    ): ?array {
        if ($this->option('no-doc') || ! (bool) config('unit-tester-documenter.auto_document', true)) {
            return null;
        }

        if (! file_exists($pestLogPath)) {
            $this->warn("Pest log file not found at [{$pestLogPath}]. Skipping documentation generation.");

            return null;
        }

        $rawLog = (string) file_get_contents($pestLogPath);

        $resolver = new AuditMetadataResolver(base_path());
        $parser = new PestLogParser;
        $generator = new CorporateReportGenerator(base_path());

        $parsedData = $parser->parse($rawLog, $snapshotDir);

        $reviewedByRows = $resolver->parseSignoffOption(
            $docOptions['reviewed_by'],
            (array) config('unit-tester-documenter.signoff.reviewed_by', []),
        );

        $approvedByRows = $resolver->parseSignoffOption(
            $docOptions['approved_by'],
            (array) config('unit-tester-documenter.signoff.approved_by', []),
        );

        $acknowledgedByRows = $resolver->parseSignoffOption(
            $docOptions['acknowledged_by'],
            (array) config('unit-tester-documenter.signoff.acknowledged_by', []),
        );

        $metadata = [
            'run_name' => $runName,
            'document_id' => $resolver->resolveDocumentId($docOptions['document_id_prefix'], $timestamp),
            'document_id_prefix' => $docOptions['document_id_prefix'],
            'sop' => $docOptions['sop'],
            'author' => $docOptions['author'],
            'executed_at' => now()->format('Y-m-d H:i:s T'),
            'company_name' => $resolver->resolveCompanyName(),
            'classification' => $resolver->resolveClassification(),
            'git' => $resolver->resolveGitMetadata(),
            'runtime_stack' => $resolver->resolveRuntimeStack(),
            'reviewed_by' => $reviewedByRows,
            'approved_by' => $approvedByRows,
            'acknowledged_by' => $acknowledgedByRows,
        ];

        $report = $generator->generate($metadata, $parsedData);

        $htmlUrl = $this->formatFileUrl($report['html_path']);

        $this->newLine();
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->line('<fg=cyan;options=bold>       Corporate Test Documentation & Evidence Generated        </>');
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->line(" <fg=gray>Document ID:</>   <options=bold>{$report['document_id']}</>");
        $this->line(" <fg=gray>Author:</>        {$metadata['author']}");
        $this->line(" <fg=gray>SOP Reference:</> {$metadata['sop']}");
        $this->line(' <fg=gray>Verdict:</>       '.($parsedData['verdict'] === 'PASSED' ? '<fg=green;options=bold>PASSED</>' : '<fg=red;options=bold>FAILED</>'));
        $this->newLine();
        $this->line(' <fg=yellow;options=bold>Print-Ready HTML Report:</>');
        $this->line("   <fg=white>{$htmlUrl}</>");
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->newLine();

        return $report;
    }

    /**
     * Format a path into a clickable file:/// URL with forward slashes.
     */
    protected function formatFileUrl(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);

        if (! str_starts_with($normalized, '/')) {
            $normalized = '/'.$normalized;
        }

        return "file://{$normalized}";
    }
}
