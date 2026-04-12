<?php

declare(strict_types=1);

namespace Scafera\Database\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;

#[AsCommand('db:schema:show', description: 'Show column definitions for a table')]
final class SchemaShowCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
        $this->addArg('table', 'The table name to inspect');
        $this->addFlag('json', null, 'Output as JSON');
    }

    protected function handle(Input $input, Output $output): int
    {
        $tableName = $input->argument('table');
        $schemaManager = $this->connection->createSchemaManager();

        try {
            $table = $schemaManager->introspectTable($tableName);
        } catch (\Throwable) {
            $output->error(sprintf('Table "%s" does not exist.', $tableName));
            return self::FAILURE;
        }

        $columns = $this->buildColumnData($table);

        if ($input->option('json')) {
            $jsonColumns = array_map(fn (array $d) => [
                'name' => $d['name'],
                'type' => $d['type'],
                'nullable' => $d['nullable_bool'],
                'default' => $d['default_raw'],
                'key' => $d['key_raw'],
            ], $columns);

            $output->writeln(json_encode([
                'table' => $tableName,
                'columns' => $jsonColumns,
            ], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $output->writeln(sprintf('<info>Table: %s</info>', $tableName));
        $output->newLine();

        $rows = array_map(
            fn (array $d) => [$d['name'], $d['type'], $d['nullable'], $d['default'], $d['key']],
            $columns,
        );

        $output->table(['Column', 'Type', 'Nullable', 'Default', 'Key'], $rows);

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function buildColumnData(Table $table): array
    {
        $primaryKey = $table->getPrimaryKey();
        $primaryColumns = $primaryKey !== null ? $primaryKey->getColumns() : [];

        $uniqueColumns = [];
        foreach ($table->getIndexes() as $index) {
            if ($index->isUnique() && !$index->isPrimary()) {
                foreach ($index->getColumns() as $col) {
                    $uniqueColumns[$col] = true;
                }
            }
        }

        $indexedColumns = [];
        foreach ($table->getIndexes() as $index) {
            if (!$index->isUnique() && !$index->isPrimary()) {
                foreach ($index->getColumns() as $col) {
                    $indexedColumns[$col] = true;
                }
            }
        }

        $data = [];

        foreach ($table->getColumns() as $column) {
            $name = $column->getName();
            $keyParts = $this->getKeyParts($name, $primaryColumns, $uniqueColumns, $indexedColumns);

            $data[] = [
                'name' => $name,
                'type' => $this->formatType($column),
                'nullable_bool' => !$column->getNotnull(),
                'nullable' => $column->getNotnull() ? 'NO' : 'YES',
                'default_raw' => $column->getDefault(),
                'default' => $column->getDefault() !== null ? (string) $column->getDefault() : '—',
                'key_raw' => $keyParts ?: null,
                'key' => implode(', ', $keyParts) ?: '—',
            ];
        }

        return $data;
    }

    private function formatType(Column $column): string
    {
        $type = $column->getType()->getTypeRegistry()->lookupName($column->getType());

        $length = $column->getLength();
        if ($length !== null) {
            return sprintf('%s(%d)', $type, $length);
        }

        $precision = $column->getPrecision();
        $scale = $column->getScale();
        if ($precision !== null && $scale > 0) {
            return sprintf('%s(%d,%d)', $type, $precision, $scale);
        }

        return $type;
    }

    /**
     * @param list<string> $primaryColumns
     * @return list<string>
     */
    private function getKeyParts(string $name, array $primaryColumns, array $uniqueColumns, array $indexedColumns): array
    {
        $keys = [];

        if (in_array($name, $primaryColumns, true)) {
            $keys[] = 'PRIMARY';
        }

        if (isset($uniqueColumns[$name])) {
            $keys[] = 'UNIQUE';
        }

        if (isset($indexedColumns[$name])) {
            $keys[] = 'INDEX';
        }

        return $keys;
    }
}
