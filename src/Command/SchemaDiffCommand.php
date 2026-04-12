<?php

declare(strict_types=1);

namespace Scafera\Database\Command;

use Scafera\Database\Schema\SchemaDiffInspector;
use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;

#[AsCommand('db:schema:diff', description: 'Show mismatches between entities and database')]
final class SchemaDiffCommand extends Command
{
    public function __construct(
        private readonly SchemaDiffInspector $inspector,
    ) {
        parent::__construct();
        $this->addFlag('json', null, 'Output as JSON');
    }

    protected function handle(Input $input, Output $output): int
    {
        $diffs = $this->inspector->inspect();

        if ($input->option('json')) {
            $output->writeln(json_encode($diffs, JSON_PRETTY_PRINT));
            return empty($diffs) ? self::SUCCESS : self::FAILURE;
        }

        if (empty($diffs)) {
            $output->success('Database schema is in sync with entities.');
            return self::SUCCESS;
        }

        foreach ($diffs as $diff) {
            $output->writeln(sprintf('<comment>%s</comment> → <info>%s</info>', $diff['entity'], $diff['table']));

            foreach ($diff['issues'] as $issue) {
                $output->writeln(sprintf('  ✗ %s', $issue['message']));
            }

            $output->newLine();
        }

        $totalIssues = array_sum(array_map(fn (array $d) => count($d['issues']), $diffs));
        $output->error(sprintf('%d issue(s) found across %d entity/table pair(s).', $totalIssues, count($diffs)));

        return self::FAILURE;
    }
}
