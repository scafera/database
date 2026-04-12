<?php

declare(strict_types=1);

namespace Scafera\Database\Mapping\Contract;

/** @internal Marker for Scafera field-type attributes */
interface FieldAttribute
{
    public function doctrineType(): string;

    /** @return array<string, mixed> */
    public function doctrineOptions(): array;
}
