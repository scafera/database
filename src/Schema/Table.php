<?php

declare(strict_types=1);

namespace Scafera\Database\Schema;

final class Table
{
    /** @var list<ColumnBuilder> */
    private array $columnBuilders = [];

    /** @var list<DropColumn> */
    private array $dropColumns = [];

    private bool $hasId = false;

    public function __construct(
        private readonly string $tableName,
    ) {
    }

    public function id(): self
    {
        $this->hasId = true;
        $this->columnBuilders[] = new ColumnBuilder(
            table: $this->tableName,
            name: 'id',
            type: ColumnType::Integer,
        );

        return $this;
    }

    public function string(string $name, int $length = 255): ColumnBuilder
    {
        return $this->column($name, ColumnType::String, length: $length);
    }

    public function text(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::Text);
    }

    public function integer(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::Integer);
    }

    public function bigInteger(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::BigInteger);
    }

    public function smallInteger(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::SmallInteger);
    }

    public function boolean(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::Boolean);
    }

    public function timestamp(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::Timestamp);
    }

    public function date(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::Date);
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnBuilder
    {
        return $this->column($name, ColumnType::Decimal, precision: $precision, scale: $scale);
    }

    public function json(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::Json);
    }

    public function dropColumn(string $name): self
    {
        $this->dropColumns[] = new DropColumn($this->tableName, $name);

        return $this;
    }

    public function hasId(): bool
    {
        return $this->hasId;
    }

    /** @return list<AddColumn|DropColumn> */
    public function getOperations(): array
    {
        $operations = [];

        foreach ($this->columnBuilders as $builder) {
            $operations[] = $builder->build();
        }

        foreach ($this->dropColumns as $drop) {
            $operations[] = $drop;
        }

        return $operations;
    }

    /** @return list<AddColumn> */
    public function getAddColumns(): array
    {
        return array_map(
            fn(ColumnBuilder $b) => $b->build(),
            $this->columnBuilders,
        );
    }

    private function column(
        string $name,
        ColumnType $type,
        ?int $length = null,
        ?int $precision = null,
        ?int $scale = null,
    ): ColumnBuilder {
        $builder = new ColumnBuilder(
            table: $this->tableName,
            name: $name,
            type: $type,
            length: $length,
            precision: $precision,
            scale: $scale,
        );

        $this->columnBuilders[] = $builder;

        return $builder;
    }
}
