<?php

declare(strict_types=1);

namespace Scafera\Database\Command;

use Doctrine\Migrations\DependencyFactory;
use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;

#[AsCommand('db:migrate:status', description: 'Show migration status')]
final class MigrateStatusCommand extends Command
{
    public function __construct(
        private readonly DependencyFactory $dependencyFactory,
    ) {
        parent::__construct();
    }

    protected function handle(Input $input, Output $output): int
    {
        $statusCalculator = $this->dependencyFactory->getMigrationStatusCalculator();
        $executedUnavailable = $statusCalculator->getExecutedUnavailableMigrations();
        $newMigrations = $statusCalculator->getNewMigrations();

        $allMigrations = $this->dependencyFactory->getMigrationRepository()->getMigrations();
        $executedMigrations = $this->dependencyFactory->getMetadataStorage()->getExecutedMigrations();

        $rows = [];
        foreach ($allMigrations->getItems() as $migration) {
            $version = (string) $migration->getVersion();
            $executed = $executedMigrations->hasMigration($migration->getVersion());
            $rows[] = [
                $version,
                $executed ? '<info>Yes</info>' : '<comment>No</comment>',
            ];
        }

        if (count($rows) === 0) {
            $output->info('No migrations found.');
            return self::SUCCESS;
        }

        $output->table(['Migration', 'Executed'], $rows);

        $output->writeln(sprintf(
            'Total: %d | Executed: %d | Pending: %d',
            count($allMigrations),
            count($executedMigrations),
            count($newMigrations),
        ));

        return self::SUCCESS;
    }
}
