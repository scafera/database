<?php

declare(strict_types=1);

namespace Scafera\Database\Command;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;

#[AsCommand('db:reset', description: 'Drop all tables and re-run all migrations')]
final class ResetCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DependencyFactory $dependencyFactory,
    ) {
        parent::__construct();
        $this->addFlag('force', null, 'Required to confirm the reset');
    }

    protected function handle(Input $input, Output $output): int
    {
        if (!$input->option('force')) {
            $output->error('This will DROP all tables and data. Use --force to confirm.');
            return self::FAILURE;
        }

        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        if (count($tables) === 0) {
            $output->info('Database is already empty.');
        } else {
            $platform = $this->connection->getDatabasePlatform();
            $isMySQL = $platform instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

            if ($isMySQL) {
                $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            }

            foreach ($tables as $table) {
                $this->connection->executeStatement(
                    $platform->getDropTableSQL($this->connection->quoteIdentifier($table)),
                );
            }

            if ($isMySQL) {
                $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            }

            $output->writeln(sprintf('Dropped <info>%d</info> table(s).', count($tables)));
        }

        // Re-run all migrations
        $this->dependencyFactory->getMetadataStorage()->ensureInitialized();

        $planCalculator = $this->dependencyFactory->getMigrationPlanCalculator();
        $plan = $planCalculator->getPlanUntilVersion(
            $this->dependencyFactory->getVersionAliasResolver()->resolveVersionAlias('latest'),
        );

        if (count($plan) === 0) {
            $output->success('No migrations to run.');
            return self::SUCCESS;
        }

        $output->writeln(sprintf('Running <info>%d</info> migration(s):', count($plan)));

        foreach ($plan->getItems() as $item) {
            $output->writeln('  - ' . $item->getVersion());
        }

        $migrator = $this->dependencyFactory->getMigrator();
        $migrator->migrate($plan, new \Doctrine\Migrations\MigratorConfiguration());

        $output->newLine();
        $output->success('Database reset complete.');

        return self::SUCCESS;
    }
}
