<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\Process\Process;
use Throwable;

final class AuditMetadataResolver
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(
        private readonly ?string $basePath = null,
    ) {}

    /**
     * Resolve author / tester name with git user.name fallback.
     */
    public function resolveAuthor(?string $cliAuthor = null): string
    {
        if (is_string($cliAuthor) && trim($cliAuthor) !== '') {
            return trim($cliAuthor);
        }

        $configAuthor = config('rubberstamp.author_name', config('unit-tester-documenter.author_name'));

        if (is_string($configAuthor) && trim($configAuthor) !== '') {
            return trim($configAuthor);
        }

        $gitAuthor = $this->runGitCommand(['config', 'user.name']);

        if ($gitAuthor !== null && trim($gitAuthor) !== '') {
            return trim($gitAuthor);
        }

        $systemUser = get_current_user();

        if (trim($systemUser) !== '') {
            return trim($systemUser);
        }

        $envUser = getenv('USERNAME') ?: getenv('USER');

        if (is_string($envUser) && trim($envUser) !== '') {
            return trim($envUser);
        }

        return 'Developer (Test Executor)';
    }

    /**
     * Resolve Document ID with custom prefix and timestamp.
     */
    public function resolveDocumentId(?string $cliPrefix = null, ?string $timestamp = null): string
    {
        $rawPrefix = is_string($cliPrefix) && trim($cliPrefix) !== ''
            ? trim($cliPrefix)
            : (string) config('rubberstamp.document_id_prefix', config('unit-tester-documenter.document_id_prefix', 'DOC-TEST-'));

        $cleanPrefix = rtrim($rawPrefix, '-_');
        $ts = $timestamp ?? now()->format('Ymd_His');

        return "{$cleanPrefix}-{$ts}";
    }

    /**
     * Resolve Test Case ID prefix derived from Document ID prefix using CASE token.
     */
    public function resolveTestCasePrefix(?string $cliPrefix = null, ?string $docId = null): string
    {
        if (is_string($cliPrefix) && trim($cliPrefix) !== '') {
            $cleanPrefix = rtrim(trim($cliPrefix), '-_');

            if ($cleanPrefix !== '') {
                return "{$cleanPrefix}-CASE-";
            }
        }

        if (is_string($docId) && trim($docId) !== '') {
            if (preg_match('/^(.*?)-(?:\d{8}[-_]\d{6}|\d{8}-\d{6}|\d{4,}.*)$/', trim($docId), $matches)) {
                $clean = rtrim(trim($matches[1]), '-_');

                if ($clean !== '') {
                    return "{$clean}-CASE-";
                }
            }

            $lastDash = strrpos(trim($docId), '-');

            if ($lastDash !== false && $lastDash > 0) {
                $clean = rtrim(substr(trim($docId), 0, $lastDash), '-_');

                if ($clean !== '') {
                    return "{$clean}-CASE-";
                }
            }
        }

        $rawPrefix = (string) config('rubberstamp.document_id_prefix', config('unit-tester-documenter.document_id_prefix', 'DOC-TEST-'));
        $cleanPrefix = rtrim(trim($rawPrefix), '-_');

        return ($cleanPrefix !== '' ? $cleanPrefix : 'DOC-TEST').'-CASE-';
    }

    /**
     * Resolve SOP reference code (or 'N/A').
     */
    public function resolveSop(?string $cliSop = null): string
    {
        if (is_string($cliSop) && trim($cliSop) !== '') {
            return trim($cliSop);
        }

        return 'N/A';
    }

    /**
     * Resolve document classification label.
     */
    public function resolveClassification(): string
    {
        return (string) (config('rubberstamp.classification')
            ?: config('unit-tester-documenter.classification')
            ?: 'INTERNAL USE ONLY');
    }

    /**
     * Resolve organization or application name.
     */
    public function resolveCompanyName(): string
    {
        $configured = config('rubberstamp.company_name', config('unit-tester-documenter.company_name'));

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        $appName = config('app.name');

        if (is_string($appName) && trim($appName) !== '') {
            return trim($appName);
        }

        return 'Scaffolding App Laravel';
    }

    /**
     * Resolve Git branch and short commit hash.
     *
     * @return array{branch: string, commit: string}
     */
    public function resolveGitMetadata(): array
    {
        $branch = $this->runGitCommand(['rev-parse', '--abbrev-ref', 'HEAD']) ?: 'main';
        $commit = $this->runGitCommand(['rev-parse', '--short', 'HEAD']) ?: 'N/A';

        return [
            'branch' => trim($branch),
            'commit' => trim($commit),
        ];
    }

    /**
     * Resolve runtime stack string including PHP, Laravel, active DB engine/version, and OS.
     */
    public function resolveRuntimeStack(): string
    {
        $phpVersion = PHP_VERSION;
        $laravelVersion = app()->version();
        $os = PHP_OS_FAMILY;

        $dbInfo = $this->resolveDatabaseInfo();

        return "PHP {$phpVersion} / Laravel {$laravelVersion} / {$dbInfo} ({$os})";
    }

    /**
     * Resolve active database driver and engine version.
     */
    public function resolveDatabaseInfo(): string
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            $pdo = $connection->getPdo();

            /** @var string|null $serverVersion */
            $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

            if (is_string($serverVersion) && trim($serverVersion) !== '') {
                return ucfirst($driver).' '.trim($serverVersion);
            }

            return ucfirst($driver);
        } catch (Throwable) {
            $defaultDriver = (string) config('database.default', 'sqlite');

            return ucfirst($defaultDriver);
        }
    }

    /**
     * Parse pipe-delimited sign-off option string into structured array.
     *
     * Format: "Name,Role|Role|Name,Role"
     *
     * @param  array<int, string>  $default
     * @return array<int, array{name: string, role: string}>
     */
    public function parseSignoffOption(?string $rawOption, array $default = []): array
    {
        $tokens = [];

        if (is_string($rawOption) && trim($rawOption) !== '') {
            $tokens = explode('|', $rawOption);
        } elseif (! empty($default)) {
            $tokens = $default;
        }

        $results = [];

        foreach ($tokens as $token) {
            $item = trim((string) $token);

            if ($item === '') {
                continue;
            }

            if (str_contains($item, ',')) {
                $parts = explode(',', $item, 2);
                $name = trim($parts[0]);
                $role = trim($parts[1]);
            } else {
                $name = '';
                $role = $item;
            }

            if ($role !== '') {
                $results[] = [
                    'name' => $name,
                    'role' => $role,
                ];
            }
        }

        return $results;
    }

    /**
     * Execute a Git command safely and return output or null.
     *
     * @param  array<int, string>  $args
     */
    private function runGitCommand(array $args): ?string
    {
        try {
            $command = array_merge(['git'], $args);
            $cwd = $this->basePath ?? (function_exists('base_path') ? base_path() : getcwd());

            $process = new Process($command, is_string($cwd) ? $cwd : null);
            $process->setTimeout(5);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $output = trim($process->getOutput());

            return $output !== '' ? $output : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Format a test class name into a standardized snapshot subdirectory.
     */
    public static function formatSnapshotSubdirectory(string $className): string
    {
        $className = ltrim((string) preg_replace('/^P\\\\/', '', $className), '\\');
        $subDir = str_replace(['\\', '/'], '-', $className);
        $subDir = (string) preg_replace('/[^a-zA-Z0-9_-]/', '-', $subDir);

        return trim((string) preg_replace('/-+/', '-', $subDir), '-');
    }
}
