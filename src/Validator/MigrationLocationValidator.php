<?php

declare(strict_types=1);

namespace Scafera\Database\Validator;

use Scafera\Kernel\Contract\ValidatorInterface;
use Scafera\Kernel\Tool\FileFinder;

final class MigrationLocationValidator implements ValidatorInterface
{
    public function getId(): string
    {
        return 'database.migration-location';
    }

    public function getName(): string
    {
        return 'Migration location';
    }

    public function validate(string $projectDir): array
    {
        $violations = [];

        foreach ($this->candidateDirs($projectDir) as $dir) {
            foreach (FileFinder::findPhpFiles($dir) as $file) {
                $contents = file_get_contents($file);

                if (!$this->isMigration($contents)) {
                    continue;
                }

                $relative = str_replace($projectDir . '/', '', $file);

                if (!str_starts_with($relative, 'support/migrations/')) {
                    $violations[] = $relative . ' extends Scafera\\Database\\Migration but is not in support/migrations/ — Doctrine discovers migrations only from support/migrations/';
                }
            }
        }

        return $violations;
    }

    /** @return list<string> */
    private function candidateDirs(string $projectDir): array
    {
        $dirs = [];
        foreach (['src', 'support'] as $name) {
            $path = $projectDir . '/' . $name;
            if (is_dir($path)) {
                $dirs[] = $path;
            }
        }
        return $dirs;
    }

    private function isMigration(string $contents): bool
    {
        if (
            preg_match('/use\s+Scafera\\\\Database\\\\Migration\b/', $contents)
            && preg_match('/\bextends\s+Migration\b/', $contents)
        ) {
            return true;
        }

        if (preg_match('/\bextends\s+\\\\?Scafera\\\\Database\\\\Migration\b/', $contents)) {
            return true;
        }

        return false;
    }
}
