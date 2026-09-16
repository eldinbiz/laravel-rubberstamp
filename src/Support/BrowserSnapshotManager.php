<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Support;

use Pest\Browser\Playwright\Playwright;
use Pest\TestSuite;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

final class BrowserSnapshotManager
{
    public static ?object $activeBrowserPage = null;

    public static bool $pestHooksRegistered = false;

    /**
     * Get the categorized snapshot subdirectory name derived from the test class name.
     */
    public static function getBrowserSnapshotSubdirectory(string $className): string
    {
        return AuditMetadataResolver::formatSnapshotSubdirectory($className);
    }

    /**
     * Capture the active snapshot statically (invoked from test hooks or directly).
     */
    public static function captureActiveSnapshot(?object $testCase = null, ?string $targetDir = null): ?string
    {
        if (self::$activeBrowserPage === null) {
            return null;
        }

        try {
            if ($testCase === null && class_exists(TestSuite::class)) {
                try {
                    $testCase = TestSuite::getInstance()->test;
                } catch (Throwable) {
                    $testCase = null;
                }
            }

            if ($testCase === null && function_exists('test')) {
                try {
                    $testCase = test();
                } catch (Throwable) {
                    $testCase = null;
                }
            }

            if (is_object($testCase) && property_exists($testCase, 'target') && is_object($testCase->target)) {
                $testCase = $testCase->target;
            }

            if (! class_exists('Pest\Browser\Api\PendingAwaitablePage')) {
                return null;
            }

            $pendingPageRef = new ReflectionProperty(self::$activeBrowserPage, 'waitablePage');
            $pendingPageRef->setAccessible(true);
            /** @var object|null $waitablePage */
            $waitablePage = $pendingPageRef->getValue(self::$activeBrowserPage);

            if (! is_object($waitablePage) || ! is_a($waitablePage, 'Pest\Browser\Api\AwaitableWebpage')) {
                return null;
            }

            $awaitableRef = new ReflectionProperty($waitablePage, 'page');
            $awaitableRef->setAccessible(true);
            /** @var object $playwrightPage */
            $playwrightPage = $awaitableRef->getValue($waitablePage);

            $method = new ReflectionMethod($playwrightPage, 'screenshotBinary');
            $method->setAccessible(true);
            $binary = $method->invoke($playwrightPage, true);

            if (! is_string($binary) || $binary === '') {
                return null;
            }

            $className = 'BrowserTest';

            if ($testCase !== null) {
                $className = method_exists($testCase, 'getPrintableTestCaseName')
                    ? $testCase::getPrintableTestCaseName()
                    : get_class($testCase);
            }

            $subDir = AuditMetadataResolver::formatSnapshotSubdirectory($className);
            $defaultSnapshotBase = function_exists('base_path') ? base_path('tests/Browser/Screenshots') : 'tests/Browser/Screenshots';
            $targetBaseDir = $targetDir ?? (getenv('BROWSER_SNAPSHOT_DIR') ?: $defaultSnapshotBase);
            $targetFullDir = str_ends_with(str_replace('\\', '/', $targetBaseDir), '/'.$subDir)
                ? $targetBaseDir
                : rtrim($targetBaseDir, '/\\').DIRECTORY_SEPARATOR.$subDir;

            if (! is_dir($targetFullDir)) {
                @mkdir($targetFullDir, 0755, true);
            }

            $rawTestName = 'test_case';

            if ($testCase !== null) {
                if (method_exists($testCase, 'getPrintableTestCaseMethodName') && $testCase->getPrintableTestCaseMethodName() !== '') {
                    $rawTestName = $testCase->getPrintableTestCaseMethodName();
                } elseif (method_exists($testCase, 'name') && $testCase->name() !== '') {
                    $rawTestName = (string) $testCase->name();
                }
            }

            $testName = str_replace('__pest_evaluable_', '', $rawTestName);
            $testName = (string) preg_replace('/[^a-zA-Z0-9_-]/', '_', $testName);
            $testName = trim((string) preg_replace('/_+/', '_', $testName), '_');
            $filePath = rtrim($targetFullDir, '/\\').DIRECTORY_SEPARATOR.$testName.'.png';

            file_put_contents($filePath, (string) base64_decode($binary, true));

            $screenshotsBaseDir = function_exists('base_path') ? base_path('tests/Browser/Screenshots') : 'tests/Browser/Screenshots';
            $screenshotsFullDir = rtrim($screenshotsBaseDir, '/\\').DIRECTORY_SEPARATOR.$subDir;

            if (! is_dir($screenshotsFullDir)) {
                @mkdir($screenshotsFullDir, 0755, true);
            }
            file_put_contents(rtrim($screenshotsFullDir, '/\\').DIRECTORY_SEPARATOR.$testName.'.png', (string) base64_decode($binary, true));

            return $filePath;
        } catch (Throwable) {
            return null;
        } finally {
            self::$activeBrowserPage = null;
        }
    }

    /**
     * Register Pest browser hooks for snapshot capturing.
     *
     * Directly binds an afterEach hook on TestRepository without modifying host test files.
     */
    public static function registerPestHooks(): void
    {
        if (self::$pestHooksRegistered) {
            return;
        }

        self::$pestHooksRegistered = true;

        if (class_exists(Playwright::class)) {
            $timeout = 10_000;

            if (function_exists('config')) {
                try {
                    $timeout = (int) config('unit-tester-documenter.browser_timeout', 10_000);
                } catch (Throwable) {
                    $timeout = 10_000;
                }
            }
            Playwright::setTimeout($timeout > 0 ? $timeout : 10_000);
        }

        if (class_exists(TestSuite::class)) {
            try {
                $testSuite = TestSuite::getInstance();
                $rootPath = (string) $testSuite->rootPath;
                $rawBrowserDir = $rootPath.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Browser';
                $browserDir = realpath($rawBrowserDir) ?: $rawBrowserDir;

                $browserDirs = [$browserDir];

                if ('\\' === DIRECTORY_SEPARATOR) {
                    $lc = lcfirst($browserDir);

                    if ($lc !== $browserDir) {
                        $browserDirs[] = $lc;
                    }
                }

                $testSuite->tests->use(
                    [],
                    [],
                    $browserDirs,
                    [
                        2 => function (): void {
                            /** @var object $this */
                            BrowserSnapshotManager::captureActiveSnapshot($this);
                            BrowserSnapshotManager::resetActiveBrowserPage();
                        },
                    ],
                );
            } catch (Throwable) {
                // Gracefully ignore if TestSuite is not yet initialized
            }
        }
    }

    /**
     * Explicitly reset the active browser page reference.
     */
    public static function resetActiveBrowserPage(): void
    {
        self::$activeBrowserPage = null;
    }
}
