<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Support;

use Symfony\Component\Process\Process;
use Throwable;

final class BrowserEnvironmentDoctor
{
    public const string STATUS_OK = 'OK';

    public const string STATUS_WARNING = 'WARNING';

    public const string STATUS_FAILED = 'FAILED';

    /**
     * @param  string  $basePath  Base path of the host Laravel application.
     */
    public function __construct(
        private readonly string $basePath,
        private readonly ?string $configuredChromiumBinary = null,
    ) {}

    /**
     * Run all diagnostic checks.
     *
     * @return array<int, array{name: string, status: string, message: string, suggestion: ?string}>
     */
    public function checkAll(): array
    {
        return [
            $this->checkNodeEnvironment(),
            $this->checkPlaywrightPackage(),
            $this->checkChromiumBrowser(),
            $this->checkPestFramework(),
            $this->checkBrowserTestDirectory(),
        ];
    }

    /**
     * Determine whether any diagnostic check resulted in a FAILED status.
     *
     * @param  array<int, array{name: string, status: string, message: string, suggestion: ?string}>  $checks
     */
    public function hasFailures(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] === self::STATUS_FAILED) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if Node.js runtime is installed.
     *
     * @return array{name: string, status: string, message: string, suggestion: ?string}
     */
    public function checkNodeEnvironment(): array
    {
        try {
            $process = new Process(['node', '-v']);
            $process->run();

            if ($process->isSuccessful()) {
                $version = trim($process->getOutput());

                return [
                    'name' => 'Node.js Runtime',
                    'status' => self::STATUS_OK,
                    'message' => "Found {$version}",
                    'suggestion' => null,
                ];
            }
        } catch (Throwable) {
            // Process execution failed
        }

        return [
            'name' => 'Node.js Runtime',
            'status' => self::STATUS_FAILED,
            'message' => 'Node.js is not found on system PATH.',
            'suggestion' => 'Install Node.js (v18+) from https://nodejs.org or your OS package manager.',
        ];
    }

    /**
     * Check if Playwright NPM package or CLI is available.
     *
     * @return array{name: string, status: string, message: string, suggestion: ?string}
     */
    public function checkPlaywrightPackage(): array
    {
        $nodeModulesPlaywright = $this->basePath.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright';
        $nodeModulesPlaywrightTest = $this->basePath.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'@playwright'.DIRECTORY_SEPARATOR.'test';

        if (is_dir($nodeModulesPlaywright) || is_dir($nodeModulesPlaywrightTest)) {
            return [
                'name' => 'Playwright Package',
                'status' => self::STATUS_OK,
                'message' => 'Playwright package is installed in node_modules.',
                'suggestion' => null,
            ];
        }

        try {
            $process = new Process(['npx', 'playwright', '--version'], $this->basePath);
            $process->run();

            if ($process->isSuccessful()) {
                $version = trim($process->getOutput());

                return [
                    'name' => 'Playwright Package',
                    'status' => self::STATUS_FAILED,
                    'message' => "Found {$version} via npx, but pest-plugin-browser requires it installed locally in node_modules.",
                    'suggestion' => 'Run `npm install -D playwright` in the root directory of your project.',
                ];
            }
        } catch (Throwable) {
            //
        }

        return [
            'name' => 'Playwright Package',
            'status' => self::STATUS_FAILED,
            'message' => 'Playwright NPM package not found in project node_modules.',
            'suggestion' => 'Run `npm install -D playwright` in the root directory of your project.',
        ];
    }

    /**
     * Check Chromium binary or Playwright browser presence.
     *
     * @return array{name: string, status: string, message: string, suggestion: ?string}
     */
    public function checkChromiumBrowser(): array
    {
        if ($this->configuredChromiumBinary !== null && $this->configuredChromiumBinary !== '' && file_exists($this->configuredChromiumBinary)) {
            return [
                'name' => 'Chromium / Browser Binary',
                'status' => self::STATUS_OK,
                'message' => "Detected configured browser at: {$this->configuredChromiumBinary}",
                'suggestion' => null,
            ];
        }

        $envPath = getenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH');

        if (is_string($envPath) && $envPath !== '' && file_exists($envPath)) {
            return [
                'name' => 'Chromium / Browser Binary',
                'status' => self::STATUS_OK,
                'message' => "Detected configured browser at: {$envPath}",
                'suggestion' => null,
            ];
        }

        $expectedRevision = $this->getExpectedPlaywrightChromiumRevision();
        $playwrightCache = $this->findPlaywrightCachedChromium();

        if ($playwrightCache !== null) {
            $msg = $expectedRevision !== null
                ? "Found Playwright Chromium (revision {$expectedRevision}) in: {$playwrightCache}"
                : "Found Playwright Chromium in: {$playwrightCache}";

            return [
                'name' => 'Chromium / Browser Binary',
                'status' => self::STATUS_OK,
                'message' => $msg,
                'suggestion' => null,
            ];
        }

        $outdated = $this->findAnyCachedChromium();
        $outdatedNote = '';

        if ($outdated !== null && $expectedRevision !== null) {
            $outdatedBase = basename($outdated);
            $outdatedNote = " (found incompatible/outdated browser: {$outdatedBase})";
        }

        $message = $expectedRevision !== null
            ? "Playwright Chromium browser (revision {$expectedRevision}) is not installed in the browser cache{$outdatedNote}."
            : 'Playwright Chromium browser is not installed in the browser cache.';

        return [
            'name' => 'Chromium / Browser Binary',
            'status' => self::STATUS_FAILED,
            'message' => $message,
            'suggestion' => 'Run `npx playwright install chromium` in your project root.',
        ];
    }

    /**
     * Check if Pest and pest-plugin-browser are installed.
     *
     * @return array{name: string, status: string, message: string, suggestion: ?string}
     */
    public function checkPestFramework(): array
    {
        $pestBinary = $this->detectPestBinary();
        $browserPlugin = $this->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pestphp'.DIRECTORY_SEPARATOR.'pest-plugin-browser';

        if ($pestBinary === null) {
            return [
                'name' => 'Pest Framework',
                'status' => self::STATUS_FAILED,
                'message' => 'Pest binary not found in vendor/bin/pest.',
                'suggestion' => 'Run `composer require pestphp/pest --dev` or `composer install`.',
            ];
        }

        if (! is_dir($browserPlugin)) {
            return [
                'name' => 'Pest Framework',
                'status' => self::STATUS_FAILED,
                'message' => 'pest-plugin-browser not found in vendor/pestphp/pest-plugin-browser.',
                'suggestion' => 'Run `composer require pestphp/pest-plugin-browser --dev`.',
            ];
        }

        return [
            'name' => 'Pest Framework',
            'status' => self::STATUS_OK,
            'message' => 'Pest and pest-plugin-browser are installed.',
            'suggestion' => null,
        ];
    }

    /**
     * Check if the tests/Browser directory exists.
     *
     * @return array{name: string, status: string, message: string, suggestion: ?string}
     */
    public function checkBrowserTestDirectory(): array
    {
        $dir = $this->basePath.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Browser';

        if (is_dir($dir)) {
            return [
                'name' => 'Browser Tests Directory',
                'status' => self::STATUS_OK,
                'message' => 'Found tests/Browser directory.',
                'suggestion' => null,
            ];
        }

        return [
            'name' => 'Browser Tests Directory',
            'status' => self::STATUS_WARNING,
            'message' => 'tests/Browser directory does not exist yet.',
            'suggestion' => 'Create browser tests under tests/Browser/ (e.g. tests/Browser/LoginTest.php).',
        ];
    }

    /**
     * Auto-detect the best available Chromium binary.
     */
    public function detectChromiumBinary(): ?string
    {
        if ($this->configuredChromiumBinary !== null && $this->configuredChromiumBinary !== '') {
            if (file_exists($this->configuredChromiumBinary)) {
                return $this->configuredChromiumBinary;
            }
        }

        $envPath = getenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH');

        if (is_string($envPath) && $envPath !== '' && file_exists($envPath)) {
            return $envPath;
        }

        // Standard Linux paths
        $linuxCandidates = [
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
        ];

        foreach ($linuxCandidates as $candidate) {
            if (file_exists($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        // Windows candidate paths
        if (PHP_OS_FAMILY === 'Windows') {
            $windowsCandidates = [
                getenv('PROGRAMFILES').'\\Google\\Chrome\\Application\\chrome.exe',
                getenv('PROGRAMFILES(X86)').'\\Google\\Chrome\\Application\\chrome.exe',
                getenv('LOCALAPPDATA').'\\Google\\Chrome\\Application\\chrome.exe',
                getenv('PROGRAMFILES(X86)').'\\Microsoft\\Edge\\Application\\msedge.exe',
                getenv('PROGRAMFILES').'\\Microsoft\\Edge\\Application\\msedge.exe',
            ];

            foreach ($windowsCandidates as $candidate) {
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Get candidate paths where Playwright stores cached browsers.
     *
     * @return list<string>
     */
    public function getPlaywrightCachePaths(): array
    {
        $paths = [];

        $browsersPath = getenv('PLAYWRIGHT_BROWSERS_PATH');

        if (is_string($browsersPath) && $browsersPath !== '') {
            $paths[] = $browsersPath;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $localAppData = getenv('LOCALAPPDATA');

            if (is_string($localAppData) && $localAppData !== '') {
                $paths[] = $localAppData.'\\ms-playwright';
            }
        } else {
            $home = getenv('HOME') ?: '/root';
            $paths[] = $home.'/.cache/ms-playwright';
        }

        return $paths;
    }

    /**
     * Read the required Chromium revision from the project's playwright-core browsers.json.
     */
    public function getExpectedPlaywrightChromiumRevision(): ?string
    {
        $manifestCandidates = [
            $this->basePath.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright-core'.DIRECTORY_SEPARATOR.'browsers.json',
            $this->basePath.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright'.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'playwright-core'.DIRECTORY_SEPARATOR.'browsers.json',
        ];

        foreach ($manifestCandidates as $manifest) {
            if (file_exists($manifest)) {
                $content = @file_get_contents($manifest);

                if ($content !== false && $content !== '') {
                    $data = json_decode($content, true);

                    if (is_array($data) && isset($data['browsers']) && is_array($data['browsers'])) {
                        foreach ($data['browsers'] as $browser) {
                            if (isset($browser['name']) && $browser['name'] === 'chromium' && isset($browser['revision'])) {
                                return (string) $browser['revision'];
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Look for cached Playwright chromium instances matching the installed Playwright revision.
     */
    public function findPlaywrightCachedChromium(): ?string
    {
        $pathsToCheck = $this->getPlaywrightCachePaths();
        $expectedRevision = $this->getExpectedPlaywrightChromiumRevision();

        foreach ($pathsToCheck as $base) {
            if (! is_dir($base)) {
                continue;
            }

            if ($expectedRevision !== null) {
                $revisionDir = $base.DIRECTORY_SEPARATOR.'chromium-'.$expectedRevision;

                if (is_dir($revisionDir)) {
                    return $revisionDir;
                }

                $headlessDir = $base.DIRECTORY_SEPARATOR.'chromium_headless_shell-'.$expectedRevision;

                if (is_dir($headlessDir)) {
                    return $headlessDir;
                }

                continue;
            }

            $matches = glob($base.DIRECTORY_SEPARATOR.'chromium*');

            if ($matches !== false && $matches !== []) {
                return $matches[0];
            }
        }

        return null;
    }

    /**
     * Look for any cached Playwright chromium instances (including outdated ones).
     */
    public function findAnyCachedChromium(): ?string
    {
        $pathsToCheck = $this->getPlaywrightCachePaths();

        foreach ($pathsToCheck as $base) {
            if (is_dir($base)) {
                $matches = glob($base.DIRECTORY_SEPARATOR.'chromium*');

                if ($matches !== false && $matches !== []) {
                    return $matches[0];
                }
            }
        }

        return null;
    }

    /**
     * Ensure Playwright Linux container headless shell symlinks point to system chromium.
     */
    public function fixLinuxContainerSymlinks(string $chromiumPath): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return;
        }

        $cachePatterns = [
            '/root/.cache/ms-playwright/chromium_headless_shell-*/chrome-headless-shell-linux64/chrome-headless-shell',
            '/root/.cache/ms-playwright/chromium-*/chrome-linux64/chrome',
        ];

        foreach ($cachePatterns as $pattern) {
            $files = glob($pattern);

            if (! is_array($files)) {
                continue;
            }

            foreach ($files as $shellBin) {
                if (file_exists($shellBin) && ! is_link($shellBin)) {
                    @unlink($shellBin);
                    @symlink($chromiumPath, $shellBin);
                }
            }
        }
    }

    /**
     * Detect the Pest binary path.
     */
    public function detectPestBinary(): ?string
    {
        $candidates = [
            $this->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest',
            $this->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest.bat',
            $this->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pestphp'.DIRECTORY_SEPARATOR.'pest'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
