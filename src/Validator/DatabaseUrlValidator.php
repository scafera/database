<?php

declare(strict_types=1);

namespace Scafera\Database\Validator;

use Scafera\Kernel\Contract\ValidatorInterface;

final class DatabaseUrlValidator implements ValidatorInterface
{
    public function getId(): string
    {
        return 'database.database-url';
    }

    public function getName(): string
    {
        return 'DATABASE_URL defined';
    }

    public function validate(string $projectDir): array
    {
        if (getenv('DATABASE_URL') !== false || isset($_ENV['DATABASE_URL']) || isset($_SERVER['DATABASE_URL'])) {
            return [];
        }

        return ['DATABASE_URL environment variable is not defined. Set it in config/config.yaml under env: or as an OS environment variable.'];
    }
}
