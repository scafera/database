<?php

declare(strict_types=1);

namespace Scafera\Database\Validator;

use Scafera\Kernel\Contract\ValidatorInterface;

/**
 * Soft validator that detects patterns in Repository/ files which bypass
 * the Transaction::run() safety guarantee.
 *
 * Repositories are a controlled leakage zone — Doctrine usage is allowed.
 * But certain operations (flush, clear, raw connection access) silently
 * break the framework's commit boundary. This validator catches them early.
 *
 * Advisory, not blocking — runs during `scafera validate`.
 */
final class RepositoryDisciplineValidator implements ValidatorInterface
{
    /** @var array<string, string> pattern => human-readable warning */
    private const DANGEROUS_PATTERNS = [
        '/->flush\s*\(/' => 'calls flush() directly — this bypasses Transaction::run() and breaks the commit boundary guarantee',
        '/->clear\s*\(/' => 'calls clear() — this resets the identity map and can cause detached entity errors',
        '/->getConnection\s*\(/' => 'accesses the raw DBAL connection — prefer query methods on EntityManager for reads',
    ];

    public function getId(): string
    {
        return 'database.repository-discipline';
    }

    public function getName(): string
    {
        return 'Repository discipline';
    }

    public function validate(string $projectDir): array
    {
        $violations = [];

        foreach ($this->findRepositoryFiles($projectDir . '/src') as $file) {
            $contents = file_get_contents($file);
            $relative = 'src/' . str_replace($projectDir . '/src/', '', $file);
            $stripped = $this->stripCommentsAndStrings($contents);

            foreach (self::DANGEROUS_PATTERNS as $pattern => $description) {
                if (preg_match($pattern, $stripped)) {
                    $violations[] = $relative . ': ' . $description;
                }
            }
        }

        return $violations;
    }

    private function stripCommentsAndStrings(string $contents): string
    {
        // Remove block comments, line comments, then double- and single-quoted strings
        $contents = preg_replace('#/\*.*?\*/#s', '', $contents);
        $contents = preg_replace('#//[^\n]*#', '', $contents);
        $contents = preg_replace('#"(?:[^"\\\\]|\\\\.)*"#s', '""', $contents);
        $contents = preg_replace("#'(?:[^'\\\\]|\\\\.)*'#s", "''", $contents);

        return $contents;
    }

    /** @return list<string> */
    private function findRepositoryFiles(string $srcDir): array
    {
        $files = [];

        $this->scanForRepositories($srcDir, $files);

        return $files;
    }

    /** @param list<string> $files */
    private function scanForRepositories(string $dir, array &$files): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // Match any file under a /Repository/ directory segment
            if (preg_match('#/Repository/#', $file->getPathname())) {
                $files[] = $file->getPathname();
            }
        }
    }
}
