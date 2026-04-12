<?php

declare(strict_types=1);

namespace Scafera\Database\Schema;

final class ColumnBuilder
{
    private bool $nullable = false;
    private mixed $default = null;

    public function __construct(
        private readonly string $table,
        private readonly string $name,
        private readonly ColumnType $type,
        private readonly ?int $length = null,
        private readonly ?int $precision = null,
        private readonly ?int $scale = null,
    ) {
    }

    public function nullable(): self
    {
        $this->nullable = true;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    /** @internal */
    public function build(): AddColumn
    {
        return new AddColumn(
            table: $this->table,
            name: $this->name,
            type: $this->type,
            length: $this->length,
            precision: $this->precision,
            scale: $this->scale,
            nullable: $this->nullable,
            default: $this->default,
        );
    }
}
