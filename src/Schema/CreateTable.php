<?php

declare(strict_types=1);

namespace Scafera\Database\Schema;

final class CreateTable implements Operation
{
    /** @param list<AddColumn> $columns */
    public function __construct(
        public readonly string $table,
        public readonly array $columns,
        public readonly bool $hasId = false,
    ) {
    }

    public function isDestructive(): bool
    {
        return false;
    }
}
