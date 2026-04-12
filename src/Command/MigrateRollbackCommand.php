<?php

declare(strict_types=1);

namespace Scafera\Database\Command;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Version\Direction;
use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;

#[AsCommand('db:migrate:rollback', description: 'Rollback the last migration')]
final class MigrateRollbackCommand extends Command
{
    public function __construct(
        private readonly DependencyFactory $dependencyFactory,
    ) {
        parent::__construct();
    }

    protected function handle(Input $input, Output $output): int
    {
        $this->dependencyFactory->getMetadataStorage()->ensureInitialized();
        $executedMigrations = $this->dependencyFactory->getMetadataStorage()->getExecutedMigrations();

        if (count($executedMigrations) === 0) {
            $output->warning('No migrations to rollback.');
            return self::SUCCESS;
        }

        $items = $executedMigrations->getItems();
        $last = end($items);

        $planCalculator = $this->dependencyFactory->getMigrationPlanCalculator();
        $plan = $planCalculator->getPlanForVersions([$last->getVersion()], Direction::DOWN);

        $output->writeln('Rolling back: <info>' . $last->getVersion() . '</info>');

        $migrator = $this->dependencyFactory->getMigrator();
        $migratorConfig = new \Doctrine\Migrations\MigratorConfiguration();
        $migrator->migrate($plan, $migratorConfig);

        $output->success('Rollback complete.');

        return self::SUCCESS;
    }
}
