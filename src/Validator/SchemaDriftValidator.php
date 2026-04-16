<?php

declare(strict_types=1);

namespace Scafera\Database\Validator;

use Scafera\Database\Schema\SchemaDiffInspector;
use Scafera\Kernel\Contract\ValidatorInterface;

final class SchemaDriftValidator implements ValidatorInterface
{
    public function __construct(
        private readonly SchemaDiffInspector $inspector,
    ) {
    }

    public function getId(): string
    {
        return 'database.schema-drift';
    }

    public function getName(): string
    {
        return 'Database schema sync';
    }

    public function validate(string $projectDir): array
    {
        try {
            $diffs = $this->inspector->inspect();
        } catch (\Throwable $e) {
            return [sprintf('Could not inspect schema: %s', $e->getMessage())];
        }

        $violations = [];

        foreach ($diffs as $diff) {
            foreach ($diff['issues'] as $issue) {
                $violations[] = sprintf('%s: %s', $diff['entity'], $issue['message']);
            }
        }

        return $violations;
    }
}
