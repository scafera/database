<?php

declare(strict_types=1);

namespace Scafera\Database\Schema;

final class DropColumn implements Operation
{
    public function __construct(
        public readonly string $table,
        public readonly string $name,
    ) {
    }

    public function isDestructive(): bool
    {
        return true;
    }
}
