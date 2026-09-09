<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Support;

use Symfony\Component\Process\Process;

final class BrowserEnvironmentDoctor
{
    public const STATUS_OK = 'OK';
    public const STATUS_WARNING = 'WARNING';
    public const STATUS_FAILED = 'FAILED';

    /**
     * @param string $basePath Base path of the host Laravel application.
     */
    public function __construct(
        private readonly string $basePath,
        private readonly ?string $configuredChromiumBinary = null,
    ) {
    }

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
     * @param array<int, array{name: string, status: string, message: string, suggestion: ?string}> $checks
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
        } catch (\Throwable) {
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
                    'status' => self::STATUS_OK,
                    'message' => "Found {$version} via npx.",
                    'suggestion' => null,
                ];
            }
        } catch (\Throwable) {
            //
        }

        return [
            'name' => 'Playwright Package',
            'status' => self::STATUS_WARNING,
            'message' => 'Playwright NPM package not found in project node_modules.',
            'suggestion' => 'Run `npm install -D playwright` or `npm install --ignore-scripts` to install Playwright.',
        ];
    }

    /**
     * Check Chromium binary or Playwright browser presence.
     *
     * @return array{name: string, status: string, message: string, suggestion: ?string}
     */
    public function checkChromiumBrowser(): array
    {
        $binary = $this->detectChromiumBinary();

        if ($binary !== null) {
            return [
                'name' => 'Chromium / Browser Binary',
                'status' => self::STATUS_OK,
                'message' => "Detected browser at: {$binary}",
                'suggestion' => null,
            ];
        }

        // Check if Playwright cache contains chromium
        $playwrightCache = $this->findPlaywrightCachedChromium();
        if ($playwrightCache !== null) {
            return [
                'name' => 'Chromium / Browser Binary',
                'status' => self::STATUS_OK,
                'message' => "Found Playwright Chromium in: {$playwrightCache}",
                'suggestion' => null,
            ];
        }

        return [
            'name' => 'Chromium / Browser Binary',
            'status' => self::STATUS_FAILED,
            'message' => 'No Chromium or Playwright browser executable detected.',
            'suggestion' => 'Run `npx playwright install chromium` or configure PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH in .env.',
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
                if (is_string($candidate) && file_exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Look for cached Playwright chromium instances.
     */
    public function findPlaywrightCachedChromium(): ?string
    {
        $pathsToCheck = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $localAppData = getenv('LOCALAPPDATA');
            if (is_string($localAppData) && $localAppData !== '') {
                $pathsToCheck[] = $localAppData.'\\ms-playwright';
            }
        } else {
            $home = getenv('HOME') ?: '/root';
            $pathsToCheck[] = $home.'/.cache/ms-playwright';
        }

        foreach ($pathsToCheck as $base) {
            if (is_dir($base)) {
                $matches = glob($base.DIRECTORY_SEPARATOR.'chromium-*');
                if ($matches && count($matches) > 0) {
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
