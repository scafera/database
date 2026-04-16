<?php

declare(strict_types=1);

namespace Scafera\Database\Validator;

use Scafera\Kernel\Contract\ValidatorInterface;
use Scafera\Kernel\Tool\FileFinder;

final class SeederNamingValidator implements ValidatorInterface
{
    public function getId(): string
    {
        return 'database.seeder-naming';
    }

    public function getName(): string
    {
        return 'Seed naming';
    }

    public function validate(string $projectDir): array
    {
        $seedDir = $projectDir . '/support/seeds';
        if (!is_dir($seedDir)) {
            return [];
        }

        $violations = [];

        foreach (FileFinder::findPhpFiles($seedDir) as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);

            if (!str_ends_with($name, 'Seed')) {
                $clean = str_ends_with($name, 'Seeder') ? substr($name, 0, -6) : $name;
                $violations[] = 'support/seeds/' . basename($file) . ' must use Seed suffix — rename to ' . $clean . 'Seed.php';
            }
        }

        return $violations;
    }
}
