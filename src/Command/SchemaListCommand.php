<?php

declare(strict_types=1);

namespace Scafera\Database\Command;

use Doctrine\DBAL\Connection;
use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;

#[AsCommand('db:schema:list', description: 'List all database tables')]
final class SchemaListCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
        $this->addFlag('json', null, 'Output as JSON');
    }

    protected function handle(Input $input, Output $output): int
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->introspectTables();

        if (count($tables) === 0) {
            if ($input->option('json')) {
                $output->writeln(json_encode([], JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }

            $output->info('No tables found in database.');
            return self::SUCCESS;
        }

        $data = [];

        foreach ($tables as $table) {
            $name = $table->getName();
            $columnCount = count($table->getColumns());

            $rowCount = (int) $this->connection->fetchOne(
                sprintf('SELECT COUNT(*) FROM %s', $this->connection->quoteIdentifier($name)),
            );

            $data[] = [
                'table' => $name,
                'columns' => $columnCount,
                'rows' => $rowCount,
            ];
        }

        if ($input->option('json')) {
            $output->writeln(json_encode($data, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $rows = array_map(
            fn (array $d) => [$d['table'], (string) $d['columns'], (string) $d['rows']],
            $data,
        );

        $output->table(['Table', 'Columns', 'Rows'], $rows);

        return self::SUCCESS;
    }
}
