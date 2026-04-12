<?php

declare(strict_types=1);

namespace Scafera\Database\Command;

use Doctrine\Migrations\DependencyFactory;
use Scafera\Database\Migration\CodeGenerator;
use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;

#[AsCommand('db:migrate:drop', description: 'Generate a migration to drop a table')]
final class MigrateDropCommand extends Command
{
    public function __construct(
        private readonly DependencyFactory $dependencyFactory,
    ) {
        parent::__construct();
        $this->addArg('table', 'The table name to drop');
        $this->addFlag('force', null, 'Generate even if the table does not exist');
    }

    protected function handle(Input $input, Output $output): int
    {
        $tableName = $input->argument('table');

        $connection = $this->dependencyFactory->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        if (!in_array($tableName, $tables, true)) {
            if (!$input->option('force')) {
                $output->error(sprintf('Table "%s" does not exist in database. Use --force to generate anyway.', $tableName));
                return self::FAILURE;
            }

            $output->warning(sprintf('Table "%s" does not exist in database — generating anyway.', $tableName));
        }

        $config = $this->dependencyFactory->getConfiguration();
        $dirs = $config->getMigrationDirectories();

        if (count($dirs) === 0) {
            $output->error('No migration directories configured.');
            return self::FAILURE;
        }

        $namespace = array_key_first($dirs);
        $dir = $dirs[$namespace];
        $fqcn = $namespace . '\\Version' . date('YmdHis');

        $codeGenerator = new CodeGenerator();
        $path = $codeGenerator->generateDrop($fqcn, $dir, $tableName);

        $output->warning(sprintf('This migration will DROP table "%s" and all its data.', $tableName));
        $output->success('Generated migration: ' . $path);
        $output->writeln('Run <info>db:migrate</info> to execute.');

        return self::SUCCESS;
    }
}
