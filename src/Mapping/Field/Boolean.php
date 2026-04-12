<?php

declare(strict_types=1);

namespace Scafera\Database\Mapping\Field;

use Scafera\Database\Mapping\Contract\FieldAttribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Boolean implements FieldAttribute
{
    public function doctrineType(): string
    {
        return 'boolean';
    }

    public function doctrineOptions(): array
    {
        return [];
    }
}
