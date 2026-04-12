<?php

declare(strict_types=1);

namespace Scafera\Database\Command;

use Doctrine\Migrations\DependencyFactory;
use Scafera\Database\Migration\CodeGenerator;
use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;

#[AsCommand('db:migrate:create', description: 'Create a blank migration file')]
final class MigrateCreateCommand extends Command
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

        $codeGenerator = new CodeGenerator();
        $path = $codeGenerator->generateBlank($fqcn, $dir);

        $output->success('Created migration: ' . $path);

        return self::SUCCESS;
    }
}
