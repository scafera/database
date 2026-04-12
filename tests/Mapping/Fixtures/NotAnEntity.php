<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Mapping\Fixtures;

final class NotAnEntity
{
    public function __construct(
        private string $name,
    ) {
    }
}
