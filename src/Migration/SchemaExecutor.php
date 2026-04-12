<?php

declare(strict_types=1);

namespace Scafera\Database\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table as DoctrineTable;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Scafera\Database\Schema\AddColumn;
use Scafera\Database\Schema\CreateTable;
use Scafera\Database\Schema\DropColumn;
use Scafera\Database\Schema\DropTable;
use Scafera\Database\Schema\Operation;
use Scafera\Database\Schema\Schema;

/** @internal */
final class SchemaExecutor
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /** @return list<string> */
    public function toSql(Schema $schema): array
    {
        $platform = $this->connection->getDatabasePlatform();
        $sql = [];

        foreach ($schema->getOperations() as $operation) {
            array_push($sql, ...match (true) {
                $operation instanceof CreateTable => $this->createTable($operation, $platform),
                $operation instanceof DropTable => [$this->dropTable($operation, $platform)],
                $operation instanceof AddColumn => $this->addColumn($operation, $platform),
                $operation instanceof DropColumn => $this->dropColumn($operation, $platform),
                default => throw new UnsupportedOperationException(
                    sprintf('Unsupported operation type: %s', $operation::class),
                ),
            });
        }

        return $sql;
    }

    /** @return list<string> */
    private function createTable(CreateTable $op, AbstractPlatform $platform): array
    {
        $table = new DoctrineTable($op->table);

        foreach ($op->columns as $col) {
            $options = $this->columnOptions($col);

            if ($op->hasId && $col->name === 'id') {
                $options['autoincrement'] = true;
            }

            $table->addColumn($col->name, $col->type->value, $options);
        }

        if ($op->hasId) {
            $table->setPrimaryKey(['id']);
        }

        return $platform->getCreateTableSQL($table);
    }

    private function dropTable(DropTable $op, AbstractPlatform $platform): string
    {
        return $platform->getDropTableSQL($op->table);
    }

    /** @return list<string> */
    private function addColumn(AddColumn $op, AbstractPlatform $platform): array
    {
        $column = new Column($op->name, Type::getType($op->type->value), $this->columnOptions($op));
        $oldTable = new DoctrineTable($op->table);

        $tableDiff = new TableDiff(
            oldTable: $oldTable,
            addedColumns: [$column],
        );

        return $platform->getAlterTableSQL($tableDiff);
    }

    /** @return list<string> */
    private function dropColumn(DropColumn $op, AbstractPlatform $platform): array
    {
        $oldTable = $this->connection->createSchemaManager()->introspectTable($op->table);
        $column = $oldTable->getColumn($op->name);

        $tableDiff = new TableDiff(
            oldTable: $oldTable,
            droppedColumns: [$column],
        );

        return $platform->getAlterTableSQL($tableDiff);
    }

    /** @return array<string, mixed> */
    private function columnOptions(AddColumn $col): array
    {
        $options = ['notnull' => !$col->nullable];

        if ($col->length !== null) {
            $options['length'] = $col->length;
        }

        if ($col->precision !== null) {
            $options['precision'] = $col->precision;
        }

        if ($col->scale !== null) {
            $options['scale'] = $col->scale;
        }

        if ($col->default !== null) {
            $options['default'] = $col->default;
        }

        return $options;
    }
}
