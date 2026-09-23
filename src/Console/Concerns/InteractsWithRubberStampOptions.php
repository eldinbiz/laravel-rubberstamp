<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Console\Concerns;

use Dotenv\Exception\InvalidPathException;
use Dotenv\Parser\Parser;
use Dotenv\Store\StoreBuilder;
use Eldinbiz\RubberStamp\Support\AuditMetadataResolver;
use Eldinbiz\RubberStamp\Support\CorporateReportGenerator;
use Eldinbiz\RubberStamp\Support\PestLogParser;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Helper\QuestionHelper;

use function Laravel\Prompts\multiselect;

/**
 * @mixin Command
 */
trait InteractsWithRubberStampOptions
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
            $defaultDocId = (string) config('rubberstamp.document_id_prefix', 'DOC-TEST-');

            return [
                'author' => $resolver->resolveAuthor($cliAuthor),
                'document_id_prefix' => $cliDocId ?: $defaultDocId,
                'sop' => $resolver->resolveSop($cliSop),
                'reviewed_by' => $cliReviewed,
                'approved_by' => $cliApproved,
                'acknowledged_by' => $cliAck,
            ];
        }

        $this->disableSttyOnWindows();

        $this->newLine();
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->line('<fg=cyan;options=bold>       Interactive Test Documentation & Audit Setup             </>');
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->newLine();

        $defaultAuthor = $resolver->resolveAuthor($cliAuthor);
        $authorInput = $this->ask('Tester / Author Name', $defaultAuthor);
        $author = is_string($authorInput) && trim($authorInput) !== '' ? trim($authorInput) : $defaultAuthor;

        $defaultPrefix = $cliDocId ?: (string) config('rubberstamp.document_id_prefix', 'DOC-TEST-');
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
        $autoDoc = (bool) config('rubberstamp.auto_document', true);

        if ($this->option('no-doc') || ! $autoDoc) {
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

        $reviewedByConfig = (array) config('rubberstamp.signoff.reviewed_by', []);
        $approvedByConfig = (array) config('rubberstamp.signoff.approved_by', []);
        $ackByConfig = (array) config('rubberstamp.signoff.acknowledged_by', []);

        $reviewedByRows = $resolver->parseSignoffOption(
            $docOptions['reviewed_by'],
            $reviewedByConfig,
        );

        $approvedByRows = $resolver->parseSignoffOption(
            $docOptions['approved_by'],
            $approvedByConfig,
        );

        $acknowledgedByRows = $resolver->parseSignoffOption(
            $docOptions['acknowledged_by'],
            $ackByConfig,
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

    /**
     * Clears any set Environment variables set by Laravel's .env if the --env option is empty.
     */
    protected function clearEnv(): void
    {
        if (! $this->option('env')) {
            $path = function_exists('base_path') ? base_path() : (string) getcwd();
            $environmentPath = $path;
            $environmentFile = '.env';

            if (function_exists('app')) {
                /** @var mixed $app */
                $app = app();

                if (is_object($app) && method_exists($app, 'environmentPath')) {
                    $environmentPath = (string) $app->environmentPath();
                }

                if (is_object($app) && method_exists($app, 'environmentFile')) {
                    $environmentFile = (string) $app->environmentFile();
                }
            }

            $vars = $this->getEnvironmentVariables(
                $environmentPath,
                $environmentFile,
            );

            $repository = Env::getRepository();

            foreach ($vars as $name) {
                $repository->clear($name);
                unset($_ENV[$name], $_SERVER[$name]);
                putenv($name);
            }
        }
    }

    /**
     * Parse variable names from an environment file.
     *
     * @return array<int, string>
     */
    protected function getEnvironmentVariables(string $path, string $file): array
    {
        $fullPath = $path.DIRECTORY_SEPARATOR.$file;

        if (! file_exists($fullPath)) {
            return [];
        }

        try {
            $content = StoreBuilder::createWithNoNames()
                ->addPath($path)
                ->addName($file)
                ->make()
                ->read();
        } catch (InvalidPathException) {
            return [];
        }

        $vars = [];

        foreach ((new Parser)->parse($content) as $entry) {
            $vars[] = $entry->getName();
        }

        return $vars;
    }

    /**
     * Interactively prompt the developer to select test suites or test classes.
     *
     * @param  string  $scope  'features' or 'browser'
     * @param  string|null  $target  Optional target directory or file
     * @return array<int, string> List of selected target paths
     */
    protected function promptForTestSuites(string $scope, ?string $target = null): array
    {
        $discovered = $this->discoverAvailableTestSuites($scope, $target);

        if (empty($discovered)) {
            if ($this->output !== null) {
                $this->warn("No test suites or classes discovered for [{$scope}].");
            }

            return [];
        }

        if ($this->output !== null) {
            $this->newLine();
            $this->line('<fg=cyan;options=bold>=================================================================</>');
            $this->line('<fg=cyan;options=bold>       RubberStamp Interactive Test Suite Selection             </>');
            $this->line('<fg=cyan;options=bold>=================================================================</>');
            $this->newLine();
            $this->line('  <fg=yellow;options=bold>How to select:</> Enter comma-separated numbers (e.g. <comment>1, 3</comment>) or press Enter to cancel.');
            $this->newLine();
        }

        $this->configurePrompts($this->input);
        $this->disableSttyOnWindows();

        $options = [];
        $indexToTargetMap = [];
        $i = 1;

        foreach ($discovered as $targetPath => $label) {
            $idx = (string) $i;
            $options[$idx] = "{$label} <fg=gray>({$targetPath})</>";
            $indexToTargetMap[$idx] = $targetPath;
            $i++;
        }

        /** @var array<int|string> $selected */
        $selected = [];

        if (function_exists('Laravel\Prompts\multiselect')) {
            $selected = multiselect(
                label: "Select {$scope} test suites or classes to run:",
                options: $options,
                required: false,
                scroll: 15,
                hint: 'Use Space to toggle checkboxes, Ctrl+A to select/deselect all, Enter to submit',
            );
        } else {
            $labels = array_values($options);
            /** @var array<int, string> $chosenLabels */
            $chosenLabels = (array) $this->choice(
                "Select {$scope} test suites or classes to run (comma-separated)",
                $labels,
                null,
                null,
                true,
            );

            $flipped = array_flip($options);
            foreach ($chosenLabels as $label) {
                if (isset($flipped[$label])) {
                    $selected[] = $flipped[$label];
                }
            }
        }

        if ($this->output !== null) {
            $this->line('<fg=cyan;options=bold>=================================================================</>');
            $this->newLine();
        }

        return $this->resolveSelectedTargets((array) $selected, $discovered, $indexToTargetMap);
    }

    /**
     * Discover available test suites and test classes for the given scope.
     *
     * @param  string  $scope  'features' or 'browser'
     * @return array<string, string> Map of target path/suite key to formatted display label
     */
    public function discoverAvailableTestSuites(string $scope, ?string $target = null): array
    {
        $basePath = $this->resolveTestSuiteBasePath();
        $options = [];

        if ($target !== null && $target !== '') {
            $fullTarget = $basePath.DIRECTORY_SEPARATOR.ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $target), DIRECTORY_SEPARATOR);

            if (is_file($fullTarget)) {
                $relPath = str_replace('\\', '/', $target);
                $displayName = $this->resolveTestClassDisplayName($fullTarget, $basePath);
                $options[$relPath] = "[Class] {$displayName}";

                return $options;
            }

            if (is_dir($fullTarget)) {
                $relDir = str_replace('\\', '/', $target);
                $options[$relDir] = "[Suite] All in {$relDir}";
                $files = $this->scanForTestFiles($fullTarget);
                foreach ($files as $file) {
                    $relFile = $this->getRelativePath($file, $basePath);
                    $displayName = $this->resolveTestClassDisplayName($file, $basePath);
                    $options[$relFile] = "[Class] {$displayName}";
                }

                return $options;
            }

            return [];
        }

        if ($scope === 'browser') {
            $browserDirs = [];
            $defaultBrowserDir = $basePath.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Browser';

            if (is_dir($defaultBrowserDir)) {
                $browserDirs[] = $defaultBrowserDir;
            }

            $moduleBrowserDirs = glob($basePath.DIRECTORY_SEPARATOR.'app-modules'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Browser', GLOB_ONLYDIR);

            if (is_array($moduleBrowserDirs)) {
                foreach ($moduleBrowserDirs as $modDir) {
                    if (is_dir($modDir)) {
                        $browserDirs[] = $modDir;
                    }
                }
            }

            if (is_dir($defaultBrowserDir)) {
                $options['tests/Browser'] = '[Suite] All Browser Tests';
            }

            foreach ($browserDirs as $dir) {
                $files = $this->scanForTestFiles($dir);
                foreach ($files as $file) {
                    $relFile = $this->getRelativePath($file, $basePath);
                    $displayName = $this->resolveTestClassDisplayName($file, $basePath);
                    $options[$relFile] = "[Class] {$displayName}";
                }
            }

            return $options;
        }

        // Feature / general test scope:
        // 1. Discover XML test suites from phpunit.xml / phpunit.xml.dist
        $xmlSuites = $this->discoverXmlTestSuites($basePath);
        $scannedDirectories = [];

        foreach ($xmlSuites as $suiteName => $dirPattern) {
            $options[$dirPattern] = "[Suite] {$suiteName}";
            $resolved = $this->resolveDirectoryPattern($dirPattern, $basePath);
            foreach ($resolved as $dir) {
                $scannedDirectories[] = $dir;
            }
        }

        if (empty($scannedDirectories)) {
            $featureDir = $basePath.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Feature';

            if (is_dir($featureDir)) {
                $options['tests/Feature'] = '[Suite] Feature';
                $scannedDirectories[] = $featureDir;
            }

            $unitDir = $basePath.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit';

            if (is_dir($unitDir)) {
                $options['tests/Unit'] = '[Suite] Unit';
                $scannedDirectories[] = $unitDir;
            }

            $moduleDirs = glob($basePath.DIRECTORY_SEPARATOR.'app-modules'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'tests', GLOB_ONLYDIR);

            if (is_array($moduleDirs) && ! empty($moduleDirs)) {
                $options['app-modules/*/tests'] = '[Suite] Modules';
                foreach ($moduleDirs as $modDir) {
                    $scannedDirectories[] = $modDir;
                }
            }
        }

        $scannedDirectories = array_unique($scannedDirectories);

        // 2. Discover individual test classes / files
        foreach ($scannedDirectories as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $files = $this->scanForTestFiles($dir);
            foreach ($files as $file) {
                $relFile = $this->getRelativePath($file, $basePath);
                $displayName = $this->resolveTestClassDisplayName($file, $basePath);
                $options[$relFile] = "[Class] {$displayName}";
            }
        }

        return $options;
    }

    /**
     * Recursively scan a directory for *Test.php files.
     *
     * @return array<int, string>
     */
    private function scanForTestFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $dirName = $item->getFilename();

                if (in_array($dirName, ['Screenshots', '.temp', '.pest', 'vendor', 'node_modules', 'build'], true)) {
                    continue;
                }
            } elseif ($item->isFile() && str_ends_with($item->getFilename(), 'Test.php')) {
                $fullPath = $item->getPathname();
                $normalized = str_replace('\\', '/', $fullPath);

                if (str_contains($normalized, '/Screenshots/') || str_contains($normalized, '/vendor/')) {
                    continue;
                }
                $files[] = $fullPath;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Convert an absolute path to a relative forward-slash path.
     */
    private function getRelativePath(string $absolutePath, string $basePath): string
    {
        $normalizedBase = rtrim(str_replace('\\', '/', $basePath), '/').'/';
        $normalizedPath = str_replace('\\', '/', $absolutePath);

        if (str_starts_with($normalizedPath, $normalizedBase)) {
            return substr($normalizedPath, strlen($normalizedBase));
        }

        return $normalizedPath;
    }

    /**
     * Resolve the human-friendly test class / suite display name.
     */
    private function resolveTestClassDisplayName(string $filePath, string $basePath): string
    {
        $relPath = $this->getRelativePath($filePath, $basePath);
        $content = @file_get_contents($filePath);

        $namespace = null;
        $class = null;

        if (is_string($content) && $content !== '') {
            if (preg_match('/^[ \t]*namespace\s+([^;]+);/m', $content, $mNs)) {
                $namespace = trim($mNs[1]);
            }

            if (preg_match('/^[ \t]*(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+Test)\b/m', $content, $mCl)) {
                $class = trim($mCl[1]);
            }
        }

        if ($class === null) {
            $withoutExt = preg_replace('/\.php$/i', '', basename($relPath)) ?? basename($relPath);
            $class = $withoutExt;
        }

        if (str_starts_with($relPath, 'app-modules/')) {
            $parts = explode('/', $relPath);
            $moduleName = $parts[1] ?? 'module';

            if ($namespace !== null) {
                $subNs = preg_replace('/^(?:Modules|Appmodules)\\\\[^\\\\]+\\\\(?:Tests?\\\\[^\\\\]+\\\\)?/i', '', $namespace);

                if ($subNs !== null && $subNs !== '' && $subNs !== $namespace) {
                    return "[{$moduleName}] {$subNs}\\{$class}";
                }
            }

            return "[{$moduleName}] {$class}";
        }

        if ($namespace !== null) {
            $subNs = preg_replace('/^Tests?\\\\(?:Feature|Browser|Unit)(?:\\\\|$)/i', '', $namespace);

            if ($subNs !== null && $subNs !== '') {
                return "{$subNs}\\{$class}";
            }
        }

        return $class;
    }

    /**
     * Resolve the base path containing test suites (with fallback for package workbench tests).
     */
    protected function resolveTestSuiteBasePath(): string
    {
        $basePath = function_exists('base_path') ? base_path() : (string) getcwd();

        $hasAppTests = is_dir($basePath.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Feature')
            || is_dir($basePath.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Browser')
            || is_dir($basePath.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit')
            || file_exists($basePath.DIRECTORY_SEPARATOR.'phpunit.xml');

        if (! $hasAppTests) {
            $cwd = (string) getcwd();
            $hasCwdTests = is_dir($cwd.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Feature')
                || is_dir($cwd.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Browser')
                || is_dir($cwd.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit')
                || file_exists($cwd.DIRECTORY_SEPARATOR.'phpunit.xml.dist')
                || file_exists($cwd.DIRECTORY_SEPARATOR.'phpunit.xml');

            if ($hasCwdTests) {
                return $cwd;
            }
        }

        return $basePath;
    }

    /**
     * Discover test suites defined in phpunit.xml or phpunit.xml.dist.
     *
     * @return array<string, string> Suite Name => Directory Pattern
     */
    private function discoverXmlTestSuites(string $basePath): array
    {
        $xmlPath = file_exists($basePath.DIRECTORY_SEPARATOR.'phpunit.xml')
            ? $basePath.DIRECTORY_SEPARATOR.'phpunit.xml'
            : (file_exists($basePath.DIRECTORY_SEPARATOR.'phpunit.xml.dist')
                ? $basePath.DIRECTORY_SEPARATOR.'phpunit.xml.dist'
                : null);

        if ($xmlPath === null) {
            return [];
        }

        $content = @file_get_contents($xmlPath);

        if (! is_string($content) || $content === '') {
            return [];
        }

        $suites = [];

        if (preg_match_all('/<testsuite\s+name=["\']([^"\']+)["\']\s*>\s*<(?:directory|file)[^>]*>([^<]+)<\/(?:directory|file)>/is', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = trim($match[1]);
                $dir = trim($match[2]);

                if (strtolower($name) === 'browser' || strtolower($dir) === 'tests/browser') {
                    continue;
                }

                $suites[$name] = str_replace('\\', '/', $dir);
            }
        }

        return $suites;
    }

    /**
     * Resolve a directory pattern (including wildcards) to existing absolute directory paths.
     *
     * @return array<int, string>
     */
    private function resolveDirectoryPattern(string $pattern, string $basePath): array
    {
        $pattern = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pattern);

        if (str_contains($pattern, '*')) {
            $matches = glob($basePath.DIRECTORY_SEPARATOR.$pattern, GLOB_ONLYDIR);

            return is_array($matches) ? $matches : [];
        }

        $fullPath = $basePath.DIRECTORY_SEPARATOR.$pattern;

        return is_dir($fullPath) ? [$fullPath] : [];
    }

    /**
     * Resolve selected option keys into normalized, deduplicated target paths for Pest.
     *
     * @param  array<int, int|string>  $selectedKeys
     * @param  array<string, string>  $options
     * @param  array<int|string, string>  $indexMap
     * @return array<int, string>
     */
    private function resolveSelectedTargets(array $selectedKeys, array $options, array $indexMap = []): array
    {
        $basePath = $this->resolveTestSuiteBasePath();
        $optionKeys = array_keys($options);
        $rawTargets = [];

        foreach ($selectedKeys as $key) {
            $keyStr = (string) $key;

            if (isset($indexMap[$keyStr])) {
                $rawTargets[] = $indexMap[$keyStr];
            } elseif (isset($indexMap[$key])) {
                $rawTargets[] = $indexMap[$key];
            } elseif (isset($options[$keyStr])) {
                $rawTargets[] = $keyStr;
            } elseif (is_int($key) && isset($optionKeys[$key])) {
                $rawTargets[] = $optionKeys[$key];
            } elseif (is_numeric($keyStr) && isset($optionKeys[(int) $keyStr])) {
                $rawTargets[] = $optionKeys[(int) $keyStr];
            } else {
                $rawTargets[] = $keyStr;
            }
        }

        $normalizedTargets = [];
        foreach ($rawTargets as $target) {
            if (str_contains($target, '*')) {
                $expanded = $this->resolveDirectoryPattern($target, $basePath);
                foreach ($expanded as $expDir) {
                    $normalizedTargets[] = $this->getRelativePath($expDir, $basePath);
                }
            } else {
                $normalizedTargets[] = str_replace('\\', '/', $target);
            }
        }

        $normalizedTargets = array_values(array_unique($normalizedTargets));

        $directories = [];
        $files = [];

        foreach ($normalizedTargets as $target) {
            $targetNormalized = str_replace('\\', '/', $target);

            if (! str_ends_with(strtolower($targetNormalized), '.php')) {
                $directories[] = rtrim($targetNormalized, '/');
            } else {
                $files[] = $targetNormalized;
            }
        }

        $filteredFiles = [];
        foreach ($files as $file) {
            $covered = false;
            foreach ($directories as $dir) {
                if (str_starts_with($file, $dir.'/')) {
                    $covered = true;

                    break;
                }
            }

            if (! $covered) {
                $filteredFiles[] = $file;
            }
        }

        $finalTargets = array_merge($directories, $filteredFiles);

        return array_values(array_unique($finalTargets));
    }

    /**
     * Disable stty in Symfony QuestionHelper on Windows to avoid invalid 2>/dev/null redirects.
     */
    protected function disableSttyOnWindows(): void
    {
        if (PHP_OS_FAMILY === 'Windows' && class_exists(QuestionHelper::class)) {
            QuestionHelper::disableStty();
        }
    }
}
