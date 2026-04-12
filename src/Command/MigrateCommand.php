<?php

declare(strict_types=1);

namespace Scafera\Database\Command;

use Doctrine\Migrations\DependencyFactory;
use Scafera\Database\Migration as ScaferaMigration;
use Scafera\Database\Schema\Schema;
use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;

#[AsCommand('db:migrate', description: 'Run pending database migrations')]
final class MigrateCommand extends Command
{
    public function __construct(
        private readonly DependencyFactory $dependencyFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addFlag('dry-run', null, 'Preview SQL without executing');
        $this->addFlag('force', null, 'Force execution of destructive migrations');
    }

    protected function handle(Input $input, Output $output): int
    {
        $this->dependencyFactory->getMetadataStorage()->ensureInitialized();

        $planCalculator = $this->dependencyFactory->getMigrationPlanCalculator();

        $plan = $planCalculator->getPlanUntilVersion(
            $this->dependencyFactory->getVersionAliasResolver()->resolveVersionAlias('latest'),
        );

        if (count($plan) === 0) {
            $output->success('Already up to date — no migrations to run.');
            return self::SUCCESS;
        }

        $output->writeln(sprintf('Running <info>%d</info> migration(s):', count($plan)));

        foreach ($plan->getItems() as $item) {
            $output->writeln('  - ' . $item->getVersion());
        }

        $output->newLine();

        if ($this->hasDestructiveMigrations($plan)) {
            $env = $_SERVER['APP_ENV'] ?? 'dev';

            if ($env === 'prod' && !$input->option('force')) {
                $output->error(
                    'Destructive operations detected (table/column drops). '
                    . 'Use --force to execute in production.',
                );
                return self::FAILURE;
            }

            $output->warning('Destructive operations detected (table/column drops).');
        }

        if ($input->option('dry-run')) {
            $output->warning('Dry run — no changes applied.');
            return self::SUCCESS;
        }

        $migrator = $this->dependencyFactory->getMigrator();
        $migratorConfig = new \Doctrine\Migrations\MigratorConfiguration();
        $migrator->migrate($plan, $migratorConfig);

        $output->success('Migrations applied successfully.');

        return self::SUCCESS;
    }

    private function hasDestructiveMigrations(object $plan): bool
    {
        foreach ($plan->getItems() as $item) {
            $className = (string) $item->getVersion();

            // Not a ScaferaMigration — cannot inspect, assume destructive
            if (!is_subclass_of($className, ScaferaMigration::class)) {
                return true;
            }

            $migration = new $className();
            $schema = new Schema();
            $migration->up($schema);

            if ($schema->hasDestructiveOperations()) {
                return true;
            }
        }

        return false;
    }
}
