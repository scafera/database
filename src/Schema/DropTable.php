<?php

declare(strict_types=1);

namespace Scafera\Database\Schema;

final class DropTable implements Operation
{
    public function __construct(
        public readonly string $table,
    ) {
    }

    public function isDestructive(): bool
    {
        return true;
    }
}
