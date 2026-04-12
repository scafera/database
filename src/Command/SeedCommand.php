<?php

declare(strict_types=1);

namespace Scafera\Database\Command;

use Scafera\Database\SeederInterface;
use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;

#[AsCommand('db:seed', description: 'Run database seeders')]
final class SeedCommand extends Command
{
    /** @var iterable<SeederInterface> */
    private iterable $seeders;

    /** @param iterable<SeederInterface> $seeders */
    public function __construct(iterable $seeders)
    {
        $this->seeders = $seeders;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArg('seeder', 'Specific seeder class name to run (e.g. PageSeeder)', required: false);
    }

    protected function handle(Input $input, Output $output): int
    {
        $targetName = $input->argument('seeder');
        $ran = 0;

        foreach ($this->seeders as $seeder) {
            $className = (new \ReflectionClass($seeder))->getShortName();

            if ($targetName !== null && $className !== $targetName) {
                continue;
            }

            $output->writeln('Seeding: <info>' . $className . '</info>');
            $seeder->run();
            $ran++;
        }

        if ($ran === 0) {
            if ($targetName !== null) {
                $output->error('Seeder "' . $targetName . '" not found.');
                return self::FAILURE;
            }

            $output->warning('No seeders registered.');
            return self::SUCCESS;
        }

        $output->success($ran . ' seeder(s) completed.');

        return self::SUCCESS;
    }
}
