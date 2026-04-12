<?php

declare(strict_types=1);

namespace Scafera\Database\Schema;

final class Schema
{
    /** @var list<Operation> */
    private array $operations = [];

    public function create(string $table, callable $callback): void
    {
        $builder = new Table($table);
        $callback($builder);

        $this->operations[] = new CreateTable($table, $builder->getAddColumns(), $builder->hasId());
    }

    public function drop(string $table): void
    {
        $this->operations[] = new DropTable($table);
    }

    public function modify(string $table, callable $callback): void
    {
        $builder = new Table($table);
        $callback($builder);

        foreach ($builder->getOperations() as $operation) {
            $this->operations[] = $operation;
        }
    }

    /** @return list<Operation> */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function hasDestructiveOperations(): bool
    {
        foreach ($this->operations as $operation) {
            if ($operation->isDestructive()) {
                return true;
            }
        }

        return false;
    }
}
