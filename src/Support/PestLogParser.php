<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Support;

final class PestLogParser
{
    /**
     * Parse raw Pest log content into structured test execution data.
     *
     * @param  string  $rawLog  Raw log content from .pest or terminal
     * @param  string|null  $snapshotDir  Optional path to snapshot directory (e.g. browser-test-results/{runName})
     * @return array{
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
     *             failure: array{
     *                 message: string,
     *                 location: string,
     *                 snippet: string,
     *             }|null
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
     * }
     */
    public function parse(string $rawLog, ?string $snapshotDir = null): array
    {
        $clean = $this->stripAnsi($rawLog);
        $lines = explode("\n", $clean);

        $suites = [];
        $currentSuite = null;
        $failureBlocks = $this->extractFailureBlocks($lines);

        $totalTestsFromSummary = 0;
        $passedTestsFromSummary = 0;
        $failedTestsFromSummary = 0;
        $assertionsFromSummary = 0;
        $durationFromSummary = '0.00s';

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^(PASS|FAIL)\s+([^\s]+)/', $trimmed, $m)) {
                if ($currentSuite !== null) {
                    $suites[] = $this->finalizeSuite($currentSuite, $failureBlocks);
                }

                $status = $m[1] === 'PASS' ? 'PASSED' : 'FAILED';
                $name = trim($m[2]);

                $currentSuite = [
                    'name' => $name,
                    'file' => $this->normalizeSuiteFile($name),
                    'status' => $status,
                    'cases' => [],
                    'total_duration' => 0.0,
                ];

                continue;
            }

            if ($currentSuite !== null && preg_match('/^[✓⨯x✔]\s+(.+)$/u', $trimmed, $m)) {
                $caseLine = trim($m[1]);
                $isPass = str_starts_with($trimmed, '✓') || str_starts_with($trimmed, '✔');

                $duration = '0.00s';
                $caseName = $caseLine;

                if (preg_match('/^(.*?)\s+([0-9\.]+(?:ms|s))\s*$/', $caseLine, $dm)) {
                    $caseName = trim($dm[1]);
                    $duration = trim($dm[2]);
                }

                $currentSuite['cases'][] = [
                    'name' => $caseName,
                    'status' => $isPass ? 'PASSED' : 'FAILED',
                    'duration' => $duration,
                    'assertions' => 1,
                    'failure' => null,
                ];

                continue;
            }

            if (preg_match('/Tests:\s*(.*?)$/i', $trimmed, $m)) {
                $testSummary = $m[1];

                if (preg_match('/(\d+)\s+passed/i', $testSummary, $pm)) {
                    $passedTestsFromSummary = (int) $pm[1];
                }

                if (preg_match('/(\d+)\s+failed/i', $testSummary, $fm)) {
                    $failedTestsFromSummary = (int) $fm[1];
                }

                if (preg_match('/\((\d+)\s+assertions?\)/i', $testSummary, $am)) {
                    $assertionsFromSummary = (int) $am[1];
                }
                $totalTestsFromSummary = $passedTestsFromSummary + $failedTestsFromSummary;

                continue;
            }

            if (preg_match('/Duration:\s*([0-9\.]+(?:ms|s))/i', $trimmed, $m)) {
                $durationFromSummary = $m[1];

                continue;
            }
        }

        if ($currentSuite !== null) {
            $suites[] = $this->finalizeSuite($currentSuite, $failureBlocks);
        }

        $calculatedTotal = 0;
        $calculatedPassed = 0;
        $calculatedFailed = 0;

        foreach ($suites as $s) {
            $calculatedTotal += $s['total'];
            $calculatedPassed += $s['passed'];
            $calculatedFailed += $s['failed'];
        }

        $totalTests = $totalTestsFromSummary > 0 ? $totalTestsFromSummary : $calculatedTotal;
        $passedTests = $passedTestsFromSummary > 0 ? $passedTestsFromSummary : $calculatedPassed;
        $failedTests = $failedTestsFromSummary > 0 ? $failedTestsFromSummary : $calculatedFailed;

        $verdict = ($failedTests === 0 && $totalTests > 0) ? 'PASSED' : ($totalTests > 0 ? 'FAILED' : 'NO TESTS');

        $screenshots = [];

        if ($snapshotDir !== null && is_dir($snapshotDir)) {
            $screenshots = $this->collectScreenshots($snapshotDir, $suites);
        }

        return [
            'verdict' => $verdict,
            'total_tests' => $totalTests,
            'passed_tests' => $passedTests,
            'failed_tests' => $failedTests,
            'total_assertions' => $assertionsFromSummary,
            'duration' => $durationFromSummary,
            'suites' => $suites,
            'screenshots' => $screenshots,
        ];
    }

    /**
     * Strip ANSI escape color sequences.
     */
    public function stripAnsi(string $text): string
    {
        return (string) preg_replace('/\x1B\[[0-9;]*[a-zA-Z]/', '', $text);
    }

    /**
     * Finalize suite counts and correlate failure traces.
     *
     * @param  array{name: string, file: string, status: string, cases: array<int, array<string, mixed>>, total_duration: float}  $suite
     * @param  array<string, array{message: string, location: string, snippet: string}>  $failureBlocks
     * @return array{
     *     name: string,
     *     file: string,
     *     status: string,
     *     total: int,
     *     passed: int,
     *     failed: int,
     *     duration: string,
     *     cases: array<int, array{
     *         name: string,
     *         status: string,
     *         duration: string,
     *         assertions: int,
     *         failure: array{message: string, location: string, snippet: string}|null
     *     }>
     * }
     */
    private function finalizeSuite(array $suite, array $failureBlocks): array
    {
        $passed = 0;
        $failed = 0;

        /** @var array<int, array{name: string, status: string, duration: string, assertions: int, failure: array{message: string, location: string, snippet: string}|null}> $updatedCases */
        $updatedCases = [];

        foreach ($suite['cases'] as $case) {
            $caseName = (string) $case['name'];
            $caseStatus = (string) $case['status'];
            $duration = (string) $case['duration'];
            $assertions = (int) ($case['assertions'] ?? 1);

            if ($caseStatus === 'PASSED') {
                $passed++;
                $updatedCases[] = [
                    'name' => $caseName,
                    'status' => 'PASSED',
                    'duration' => $duration,
                    'assertions' => $assertions,
                    'failure' => null,
                ];
            } else {
                $failed++;
                $failure = $this->findMatchingFailure($suite['name'], $caseName, $failureBlocks);
                $updatedCases[] = [
                    'name' => $caseName,
                    'status' => 'FAILED',
                    'duration' => $duration,
                    'assertions' => $assertions,
                    'failure' => $failure,
                ];
            }
        }

        $total = count($updatedCases);
        $status = $failed > 0 ? 'FAILED' : ($total > 0 ? 'PASSED' : $suite['status']);

        return [
            'name' => $suite['name'],
            'file' => $suite['file'],
            'status' => $status,
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'duration' => $this->calculateSuiteDuration($updatedCases),
            'cases' => $updatedCases,
        ];
    }

    /**
     * Calculate sum duration of test cases.
     *
     * @param  array<int, array<string, mixed>>  $cases
     */
    private function calculateSuiteDuration(array $cases): string
    {
        $totalSeconds = 0.0;

        foreach ($cases as $case) {
            $d = (string) ($case['duration'] ?? '0s');

            if (str_ends_with($d, 'ms')) {
                $totalSeconds += ((float) substr($d, 0, -2)) / 1000;
            } elseif (str_ends_with($d, 's')) {
                $totalSeconds += (float) substr($d, 0, -1);
            }
        }

        return sprintf('%.2fs', $totalSeconds);
    }

    /**
     * Normalize suite name to a standard relative file path.
     */
    private function normalizeSuiteFile(string $suiteName): string
    {
        $clean = str_replace('\\', '/', $suiteName);

        if (! str_ends_with($clean, '.php')) {
            $clean .= '.php';
        }

        if (! str_starts_with($clean, 'tests/')) {
            $clean = 'tests/'.ltrim($clean, '/');
        }

        return $clean;
    }

    /**
     * Extract detailed failure trace blocks from Pest log.
     *
     * @param  array<int, string>  $lines
     * @return array<string, array{message: string, location: string, snippet: string}>
     */
    private function extractFailureBlocks(array $lines): array
    {
        $blocks = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);

            if (preg_match('/^FAILED\s+([^>]+)\s*>\s*(.+)$/', $line, $m)) {
                $suite = trim($m[1]);
                $test = trim($m[2]);
                $key = "{$suite} > {$test}";

                $message = '';
                $location = '';
                $snippetLines = [];

                $j = $i + 1;
                while ($j < $count) {
                    $next = trim($lines[$j]);

                    if (str_starts_with($next, '─────') || preg_match('/^FAILED\s+/', $next) || preg_match('/^Tests:\s+/', $next)) {
                        break;
                    }

                    if (str_starts_with($next, 'at ')) {
                        $location = trim(substr($next, 3));
                    } elseif (preg_match('/^\d+▕|➜\s*\d+▕/', $next)) {
                        $snippetLines[] = $lines[$j];
                    } elseif ($location === '' && $next !== '') {
                        $message .= ($message !== '' ? "\n" : '').$next;
                    }

                    $j++;
                }

                $blocks[$key] = [
                    'message' => $message,
                    'location' => $location,
                    'snippet' => implode("\n", $snippetLines),
                ];

                $i = $j - 1;
            }
        }

        return $blocks;
    }

    /**
     * Match a failure trace to a test case.
     *
     * @param  array<string, array{message: string, location: string, snippet: string}>  $blocks
     * @return array{message: string, location: string, snippet: string}|null
     */
    private function findMatchingFailure(string $suite, string $testCase, array $blocks): ?array
    {
        $directKey = "{$suite} > {$testCase}";

        if (isset($blocks[$directKey])) {
            return $blocks[$directKey];
        }

        foreach ($blocks as $key => $block) {
            $keyParts = explode('>', $key);
            $rightPart = isset($keyParts[1]) ? trim($keyParts[1]) : '';

            if (str_contains($key, $testCase) || ($rightPart !== '' && str_contains($testCase, $rightPart))) {
                return $block;
            }
        }

        return null;
    }

    /**
     * Scan directory for snapshots and match with test suites and cases.
     *
     * @param  array<int, array<string, mixed>>  $suites
     * @return array<int, array{
     *     file_name: string,
     *     relative_path: string,
     *     absolute_path: string,
     *     test_case: string,
     *     suite: string,
     *     base64: string,
     * }>
     */
    private function collectScreenshots(string $snapshotDir, array $suites): array
    {
        $results = [];
        $files = $this->scanDirectoryRecursively($snapshotDir, 'png');

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            $subfolder = basename(dirname($filePath));

            $testCaseDescription = $this->slugToDescription(pathinfo($fileName, PATHINFO_FILENAME));
            $matchedSuite = $this->findSuiteForSubfolder($subfolder, $suites);

            $fileData = @file_get_contents($filePath);
            $base64 = $fileData !== false ? 'data:image/png;base64,'.base64_encode($fileData) : '';

            $results[] = [
                'file_name' => $fileName,
                'relative_path' => $subfolder.'/'.$fileName,
                'absolute_path' => $filePath,
                'test_case' => $testCaseDescription,
                'suite' => $matchedSuite,
                'base64' => $base64,
            ];
        }

        return $results;
    }

    /**
     * Recursively find files matching extension.
     *
     * @return array<int, string>
     */
    private function scanDirectoryRecursively(string $dir, string $extension): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $matches = [];
        $items = scandir($dir);

        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $matches = array_merge($matches, $this->scanDirectoryRecursively($path, $extension));
            } elseif (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === strtolower($extension)) {
                $matches[] = $path;
            }
        }

        return $matches;
    }

    /**
     * Convert snapshot filename slug to readable description.
     */
    private function slugToDescription(string $slug): string
    {
        $cleaned = str_replace(['__', '_', '-'], ' ', $slug);

        return trim(preg_replace('/\s+/', ' ', $cleaned) ?? $slug);
    }

    /**
     * Match subfolder (e.g. Tests-Browser-LoginTest) to suite name.
     *
     * @param  array<int, array<string, mixed>>  $suites
     */
    private function findSuiteForSubfolder(string $subfolder, array $suites): string
    {
        $normalizedSubfolder = strtolower(str_replace(['-', '_', '\\'], '', $subfolder));

        foreach ($suites as $suite) {
            $suiteName = (string) $suite['name'];
            $normalizedSuite = strtolower(str_replace(['-', '_', '\\', '/', '.'], '', $suiteName));

            if (str_contains($normalizedSubfolder, $normalizedSuite) || str_contains($normalizedSuite, $normalizedSubfolder)) {
                return (string) $suite['file'];
            }
        }

        return str_replace('-', '/', $subfolder).'.php';
    }
}
