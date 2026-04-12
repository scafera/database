<?php

declare(strict_types=1);

namespace Scafera\Database\Validator;

use Scafera\Database\Mapping\Auditable;
use Scafera\Kernel\Contract\ValidatorInterface;
use Scafera\Kernel\Tool\FileFinder;

final class AuditableInitValidator implements ValidatorInterface
{
    public function getName(): string
    {
        return 'Auditable initialization';
    }

    public function validate(string $projectDir): array
    {
        $entityDir = $projectDir . '/src/Entity';

        if (!is_dir($entityDir)) {
            return [];
        }

        $violations = [];

        foreach (FileFinder::findPhpFiles($entityDir) as $file) {
            $content = file_get_contents($file);

            if (!str_contains($content, 'use Auditable')) {
                continue;
            }

            if (!preg_match('/^\s*\$this->createdAt\s*=/m', $content)) {
                $className = $this->extractClassName($content);
                $violations[] = sprintf(
                    '%s uses Auditable but does not initialize $this->createdAt in its constructor. '
                    . 'Add: $this->createdAt = new \\DateTimeImmutable();',
                    $className ?: basename($file, '.php'),
                );
            }
        }

        return $violations;
    }

    private function extractClassName(string $content): ?string
    {
        if (preg_match('/^namespace\s+(.+);/m', $content, $ns)
            && preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $content, $cl)) {
            return $ns[1] . '\\' . $cl[1];
        }

        return null;
    }
}
