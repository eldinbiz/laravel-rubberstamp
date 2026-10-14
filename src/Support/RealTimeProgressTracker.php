<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Support;

use ReflectionClass;
use ReflectionMethod;
use Throwable;

final class RealTimeProgressTracker
{
    /**
     * @var array<int, float>
     */
    private static array $startTimes = [];

    /**
     * Emit a test start event over stderr.
     */
    public static function emitStart(object $testCase): void
    {
        $id = spl_object_id($testCase);
        self::$startTimes[$id] = microtime(true);

        $name = self::resolveTestName($testCase);
        $file = self::resolveTestFile($testCase);
        $line = self::resolveTestLine($testCase);

        self::emit('START', [
            'name' => $name,
            'file' => $file,
            'line' => $line,
            'started_at' => self::$startTimes[$id],
        ]);
    }

    /**
     * Emit a test end event over stderr.
     */
    public static function emitEnd(object $testCase, string $status = 'PASS'): void
    {
        $id = spl_object_id($testCase);
        $startTime = self::$startTimes[$id] ?? microtime(true);
        $duration = max(0.001, microtime(true) - $startTime);
        unset(self::$startTimes[$id]);

        $name = self::resolveTestName($testCase);
        $file = self::resolveTestFile($testCase);
        $line = self::resolveTestLine($testCase);

        self::emit('END', [
            'name' => $name,
            'file' => $file,
            'line' => $line,
            'status' => $status,
            'duration' => $duration,
        ]);
    }

    /**
     * Resolve the human-readable scenario name from the test case object.
     */
    public static function resolveTestName(object $testCase): string
    {
        $rawTestName = '';

        if (method_exists($testCase, 'getPrintableTestCaseMethodName') && $testCase->getPrintableTestCaseMethodName() !== '') {
            $rawTestName = (string) $testCase->getPrintableTestCaseMethodName();
        } elseif (method_exists($testCase, 'name') && $testCase->name() !== '') {
            $rawTestName = (string) $testCase->name();
        }

        if ($rawTestName === '') {
            return 'test scenario';
        }

        $cleaned = str_replace('__pest_evaluable_', '', $rawTestName);

        // If the name starts with `test_` and contains underscores, format to spaces
        if (str_starts_with($cleaned, 'test_')) {
            $cleaned = substr($cleaned, 5);
            $cleaned = str_replace('_', ' ', $cleaned);
        }

        return trim($cleaned);
    }

    /**
     * Resolve relative file path of the test suite.
     */
    public static function resolveTestFile(object $testCase): string
    {
        try {
            $reflection = new ReflectionClass($testCase);
            $file = (string) ($reflection->getFileName() ?: '');

            if ($file !== '' && function_exists('base_path')) {
                $basePath = (string) base_path();

                if (str_starts_with($file, $basePath)) {
                    $file = ltrim(substr($file, strlen($basePath)), '/\\');
                }
            }

            return str_replace('\\', '/', $file);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Resolve line number of the test case if discoverable.
     */
    public static function resolveTestLine(object $testCase): ?int
    {
        try {
            if (method_exists($testCase, 'name')) {
                $name = (string) $testCase->name();

                if ($name !== '' && method_exists($testCase, $name)) {
                    $method = new ReflectionMethod($testCase, $name);

                    return $method->getStartLine() ?: null;
                }
            }

            $reflection = new ReflectionClass($testCase);

            return $reflection->getStartLine() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Emit a structured event token via stderr.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function emit(string $event, array $payload): void
    {
        try {
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

            if ($json === false) {
                return;
            }

            $token = "__RUBBERSTAMP_EVENT__:{$event}:".base64_encode($json)."\n";

            $stream = defined('STDERR') && is_resource(STDERR) ? STDERR : fopen('php://stderr', 'wb');

            if (is_resource($stream)) {
                fwrite($stream, $token);
                fflush($stream);
            }
        } catch (Throwable) {
            // Suppress any emission failure so test execution is never disrupted
        }
    }
}
