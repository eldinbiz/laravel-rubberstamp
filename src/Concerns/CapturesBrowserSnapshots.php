<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Concerns;

use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Browsable;
use Pest\Browser\Playwright\Page as PlaywrightPage;
use Pest\Browser\Playwright\Playwright;
use Pest\Repositories\TestRepository;
use Pest\TestSuite;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;
use UnitTesterDocumenter\UnitTesterDocumenter\Support\AuditMetadataResolver;

trait CapturesBrowserSnapshots
{
    use Browsable {
        Browsable::visit as browsableVisit;
    }

    public static ?PendingAwaitablePage $activeBrowserPage = null;

    /**
     * Browse to the given URL while tracking active browser page for snapshot capture.
     *
     * @template TUrl of array<int, string>|string
     *
     * @param  TUrl  $url
     * @param  array<string, mixed>  $options
     * @return (TUrl is array<int, string> ? ArrayablePendingAwaitablePage : PendingAwaitablePage)
     */
    public function visit(array|string $url, array $options = []): ArrayablePendingAwaitablePage|PendingAwaitablePage
    {
        $page = $this->browsableVisit($url, $options);

        if ($page instanceof PendingAwaitablePage) {
            self::$activeBrowserPage = $page;
        }

        return $page;
    }

    /**
     * Get the categorized snapshot subdirectory name derived from the test class name.
     */
    public function getBrowserSnapshotSubdirectory(?string $customClassName = null): string
    {
        $className = $customClassName ?? (method_exists($this, 'getPrintableTestCaseName')
            ? $this::getPrintableTestCaseName()
            : static::class);

        return AuditMetadataResolver::formatSnapshotSubdirectory($className);
    }

    /**
     * Capture the final snapshot of the active browser page.
     */
    public function captureBrowserSnapshot(?string $targetDir = null): ?string
    {
        if (self::$activeBrowserPage === null) {
            return null;
        }

        try {
            $pendingPageRef = new ReflectionProperty(PendingAwaitablePage::class, 'waitablePage');
            $pendingPageRef->setAccessible(true);
            /** @var AwaitableWebpage|null $waitablePage */
            $waitablePage = $pendingPageRef->getValue(self::$activeBrowserPage);

            if (! $waitablePage instanceof AwaitableWebpage) {
                return null;
            }

            $awaitableRef = new ReflectionProperty(AwaitableWebpage::class, 'page');
            $awaitableRef->setAccessible(true);
            /** @var PlaywrightPage $playwrightPage */
            $playwrightPage = $awaitableRef->getValue($waitablePage);

            $method = new ReflectionMethod($playwrightPage, 'screenshotBinary');
            $method->setAccessible(true);
            $binary = $method->invoke($playwrightPage, true);

            if (! is_string($binary) || $binary === '') {
                return null;
            }

            $subDir = $this->getBrowserSnapshotSubdirectory();
            $defaultSnapshotBase = function_exists('base_path') ? base_path('tests/Browser/Screenshots') : 'tests/Browser/Screenshots';
            $targetBaseDir = $targetDir ?? (getenv('BROWSER_SNAPSHOT_DIR') ?: $defaultSnapshotBase);
            $targetFullDir = str_ends_with(str_replace('\\', '/', $targetBaseDir), '/'.$subDir)
                ? $targetBaseDir
                : rtrim($targetBaseDir, '/\\').DIRECTORY_SEPARATOR.$subDir;

            if (! is_dir($targetFullDir)) {
                @mkdir($targetFullDir, 0755, true);
            }

            $rawTestName = method_exists($this, 'name') ? (string) $this->name() : 'test_case';
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
     * Unbinds Pest's dynamic Browsable trait so that test case's visit() interceptor
     * takes precedence, and registers an afterEach hook to capture final screenshots.
     */
    public static function registerPestHooks(): void
    {
        if (class_exists('Tests\\TestCase')) {
            $traits = function_exists('class_uses_recursive')
                ? class_uses_recursive('Tests\\TestCase')
                : class_uses('Tests\\TestCase');

            if (! in_array(self::class, $traits, true)) {
                return;
            }
        }

        try {
            if (class_exists(TestRepository::class) && class_exists(TestSuite::class)) {
                $usesProp = new ReflectionProperty(TestRepository::class, 'uses');
                $usesProp->setAccessible(true);
                $uses = $usesProp->getValue(TestSuite::getInstance()->tests);

                if (is_array($uses)) {
                    foreach ($uses as $path => &$config) {
                        if (isset($config[0]) && is_array($config[0])) {
                            $config[0] = array_values(array_filter($config[0], fn ($c) => $c !== Browsable::class));
                        }
                    }
                    unset($config);
                    $usesProp->setValue(TestSuite::getInstance()->tests, $uses);
                }
            }
        } catch (Throwable) {
            // Gracefully ignore if Pest internals are not yet loaded
        }

        if (class_exists(Playwright::class)) {
            $timeout = (int) (function_exists('config') ? config('unit-tester-documenter.browser_timeout', 10_000) : 10_000);
            Playwright::setTimeout($timeout > 0 ? $timeout : 10_000);
        }

        if (function_exists('uses')) {
            uses()->afterEach(function (): void {
                try {
                    if (method_exists($this, 'captureBrowserSnapshot')) {
                        $this->captureBrowserSnapshot();
                    }
                } finally {
                    self::resetActiveBrowserPage();
                }
            })->in('Browser');
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
