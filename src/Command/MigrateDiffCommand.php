<?php

declare(strict_types=1);

namespace Scafera\Database\Command;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Generator\Exception\NoChangesDetected;
use Scafera\Database\Migration\CodeGenerator;
use Scafera\Database\Migration\UnsupportedOperationException;
use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;

#[AsCommand('db:migrate:diff', description: 'Generate a migration by comparing entities to database')]
final class MigrateDiffCommand extends Command
{
    public function __construct(
        private readonly DependencyFactory $dependencyFactory,
    ) {
        parent::__construct();
    }

    protected function handle(Input $input, Output $output): int
    {
        $config = $this->dependencyFactory->getConfiguration();
        $dirs = $config->getMigrationDirectories();

        if (count($dirs) === 0) {
            $output->error('No migration directories configured.');
            return self::FAILURE;
        }

        $namespace = array_key_first($dirs);
        $dir = $dirs[$namespace];
        $fqcn = $namespace . '\\Version' . date('YmdHis');

        $connection = $this->dependencyFactory->getConnection();
        $schemaManager = $connection->createSchemaManager();

        $fromSchema = $schemaManager->introspectSchema();
        $toSchema = $this->dependencyFactory->getSchemaProvider()->createSchema();

        // Only compare tables that have a matching entity — never drop unmanaged tables
        foreach ($fromSchema->getTables() as $table) {
            if (!$toSchema->hasTable($table->getName())) {
                $fromSchema->dropTable($table->getName());
            }
        }

        $comparator = $schemaManager->createComparator();
        $diff = $comparator->compareSchemas($fromSchema, $toSchema);

        if ($diff->isEmpty()) {
            $output->success('No changes detected — database is in sync with entities.');
            return self::SUCCESS;
        }

        try {
            $codeGenerator = new CodeGenerator();
            $result = $codeGenerator->generate($fqcn, $dir, $diff);
        } catch (UnsupportedOperationException $e) {
            $output->error($e->getMessage());
            return self::FAILURE;
        }

        foreach ($result['warnings'] as $warning) {
            $output->warning($warning);
        }

        if ($result['path'] === null) {
            $output->info('No migration generated — all detected changes are outside Schema API v1 scope.');
            return self::SUCCESS;
        }

        $output->success('Generated migration: ' . $result['path']);

        return self::SUCCESS;
    }
}
