<?php

declare(strict_types=1);

namespace Scafera\Database\Mapping\Field;

use Scafera\Database\Mapping\Contract\FieldAttribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Percentage implements FieldAttribute
{
    public function doctrineType(): string
    {
        return 'decimal';
    }

    public function doctrineOptions(): array
    {
        return ['precision' => 8, 'scale' => 4];
    }
}
