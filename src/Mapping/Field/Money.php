<?php

declare(strict_types=1);

namespace Scafera\Database\Mapping\Field;

use Scafera\Database\Mapping\Contract\FieldAttribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Money implements FieldAttribute
{
    public function doctrineType(): string
    {
        return 'decimal';
    }

    public function doctrineOptions(): array
    {
        return ['precision' => 10, 'scale' => 4];
    }
}
