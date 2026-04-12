<?php

declare(strict_types=1);

namespace Scafera\Database\Schema;

final class AddColumn implements Operation
{
    public function __construct(
        public readonly string $table,
        public readonly string $name,
        public readonly ColumnType $type,
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly bool $nullable = false,
        public readonly mixed $default = null,
    ) {
    }

    public function isDestructive(): bool
    {
        return false;
    }
}
