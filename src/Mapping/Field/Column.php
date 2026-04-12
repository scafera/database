<?php

declare(strict_types=1);

namespace Scafera\Database\Mapping\Field;

use Scafera\Database\Mapping\Contract\FieldAttribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Column implements FieldAttribute
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private readonly string $type,
        private readonly array $options = [],
    ) {
    }

    public function doctrineType(): string
    {
        return $this->type;
    }

    public function doctrineOptions(): array
    {
        return $this->options;
    }
}
