<?php

declare(strict_types=1);

namespace Scafera\Database\Mapping;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Table
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
