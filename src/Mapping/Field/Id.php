<?php

declare(strict_types=1);

namespace Scafera\Database\Mapping\Field;

use Scafera\Database\Mapping\Contract\FieldAttribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Id implements FieldAttribute
{
    public function doctrineType(): string
    {
        return 'integer';
    }

    public function doctrineOptions(): array
    {
        return [];
    }
}
