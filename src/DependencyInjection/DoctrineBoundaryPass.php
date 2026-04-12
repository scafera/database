<?php

declare(strict_types=1);

namespace Scafera\Database\DependencyInjection;

use Scafera\Kernel\Tool\FileFinder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal Enforces that Doctrine types do not leak outside allowed zones.
 *
 * Zones:
 *   Entity/     — all Doctrine imports forbidden (use Scafera\Database\Mapping\Field attributes)
 *   Repository/ — all Doctrine usage allowed (controlled leakage)
 *   Everywhere else — all Doctrine imports forbidden except Common Collections
 */
final class DoctrineBoundaryPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container): void
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        $srcDir = $projectDir . '/src';

        if (!is_dir($srcDir)) {
            return;
        }

        $violations = [];

        foreach (FileFinder::findPhpFiles($srcDir) as $file) {
            $relative = str_replace($projectDir . '/', '', $file);
            $contents = file_get_contents($file);
            $zone = $this->detectZone($relative);

            $fileViolations = match ($zone) {
                'entity' => $this->checkEntity($contents, $relative),
                'repository' => [],
                default => $this->checkStrict($contents, $relative),
            };

            array_push($violations, ...$fileViolations);
        }

        if (!empty($violations)) {
            throw new \LogicException(
                "Scafera\\Database boundary violation:\n\n"
                . implode("\n", $violations)
                . "\n\nUse Scafera\\Database\\EntityStore and Scafera\\Database\\Transaction "
                . "for persistence. Doctrine runtime types are only allowed inside Repository/ classes.",
            );
        }
    }

    private function detectZone(string $relativePath): string
    {
        if (preg_match('#/Entity/#', $relativePath)) {
            return 'entity';
        }

        if (preg_match('#/Repository/#', $relativePath)) {
            return 'repository';
        }

        return 'strict';
    }

    /** @return list<string> */
    private function checkEntity(string $contents, string $relative): array
    {
        $violations = [];

        // Forbid ALL Doctrine imports — entities must use Scafera\Database\Mapping attributes
        if (preg_match('/^use\s+Doctrine\\\\/m', $contents)) {
            $violations[] = "  - {$relative}: imports Doctrine types in entity — use Scafera\\Database\\Mapping attributes instead";
        } else {
            // Only check for FQCN lifecycle attributes if no Doctrine import was found —
            // import-style usage (#[ORM\PrePersist]) is already caught by the import check above.
            $lifecyclePattern = '/#\[\s*\\\\?Doctrine\\\\ORM\\\\Mapping\\\\(PrePersist|PostPersist|PreUpdate|PostUpdate|PreRemove|PostRemove|PostLoad|PreFlush|PostFlush)\b/';
            if (preg_match($lifecyclePattern, $contents, $m)) {
                $violations[] = "  - {$relative}: uses lifecycle callback #[{$m[1]}] — handle this logic in a Service instead";
            }
        }

        return $violations;
    }

    /** @return list<string> */
    private function checkStrict(string $contents, string $relative): array
    {
        $violations = [];

        // Forbid all Doctrine ORM imports (except Common\Collections which is just a data structure)
        if (preg_match('/^use\s+Doctrine\\\\ORM\\\\/m', $contents)) {
            $violations[] = "  - {$relative}: imports Doctrine ORM types — use Scafera\\Database\\EntityStore instead";
        }

        // Forbid all Doctrine DBAL imports
        if (preg_match('/^use\s+Doctrine\\\\DBAL\\\\/m', $contents)) {
            $violations[] = "  - {$relative}: imports Doctrine DBAL types — use Scafera\\Database\\EntityStore instead";
        }

        return $violations;
    }
}
