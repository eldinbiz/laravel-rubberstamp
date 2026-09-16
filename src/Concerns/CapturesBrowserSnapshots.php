<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Concerns;

use Eldinbiz\RubberStamp\Support\BrowserSnapshotManager;

trait CapturesBrowserSnapshots
{
    /**
     * Get the categorized snapshot subdirectory name derived from the test class name.
     */
    public function getBrowserSnapshotSubdirectory(?string $customClassName = null): string
    {
        $className = $customClassName ?? (method_exists($this, 'getPrintableTestCaseName')
            ? $this::getPrintableTestCaseName()
            : static::class);

        return BrowserSnapshotManager::getBrowserSnapshotSubdirectory($className);
    }

    /**
     * Capture the final snapshot of the active browser page.
     */
    public function captureBrowserSnapshot(?string $targetDir = null): ?string
    {
        return BrowserSnapshotManager::captureActiveSnapshot($this, $targetDir);
    }

    /**
     * Capture the active snapshot statically (delegates to BrowserSnapshotManager).
     */
    public static function captureActiveSnapshot(?object $testCase = null, ?string $targetDir = null): ?string
    {
        return BrowserSnapshotManager::captureActiveSnapshot($testCase, $targetDir);
    }

    /**
     * Register Pest browser hooks for snapshot capturing (delegates to BrowserSnapshotManager).
     */
    public static function registerPestHooks(): void
    {
        BrowserSnapshotManager::registerPestHooks();
    }

    /**
     * Explicitly reset the active browser page reference.
     */
    public static function resetActiveBrowserPage(): void
    {
        BrowserSnapshotManager::resetActiveBrowserPage();
    }
}

// Backward compatibility alias
if (! trait_exists(\UnitTesterDocumenter\UnitTesterDocumenter\Concerns\CapturesBrowserSnapshots::class, false)) {
    class_alias(CapturesBrowserSnapshots::class, \UnitTesterDocumenter\UnitTesterDocumenter\Concerns\CapturesBrowserSnapshots::class);
}
