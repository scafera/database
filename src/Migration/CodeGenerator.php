<?php

declare(strict_types=1);

namespace Scafera\Database\Migration;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

/** @internal */
final class CodeGenerator
{
    private const TEMPLATE = <<<'PHP'
<?php

declare(strict_types=1);

namespace <namespace>;

use Scafera\Database\Migration;
use Scafera\Database\Schema\Schema;
use Scafera\Database\Schema\Table;

final class <className> extends Migration
{
    public function up(Schema $schema): void
    {
<up>
    }

    public function down(Schema $schema): void
    {
<down>
    }
}

PHP;

    private const BLANK_TEMPLATE = <<<'PHP'
<?php

declare(strict_types=1);

namespace <namespace>;

use Scafera\Database\Migration;
use Scafera\Database\Schema\Schema;
use Scafera\Database\Schema\Table;

final class <className> extends Migration
{
    public function up(Schema $schema): void
    {
        //
    }

    public function down(Schema $schema): void
    {
        //
    }
}

PHP;

    private readonly TypeMapper $typeMapper;

    public function __construct()
    {
        $this->typeMapper = new TypeMapper();
    }

    public function generateBlank(string $fqcn, string $dir): string
    {
        [$namespace, $className] = $this->parseFqcn($fqcn);

        $code = strtr(self::BLANK_TEMPLATE, [
            '<namespace>' => $namespace,
            '<className>' => $className,
        ]);

        return $this->writeFile($dir, $className, $code);
    }

    public function generateDrop(string $fqcn, string $dir, string $tableName): string
    {
        [$namespace, $className] = $this->parseFqcn($fqcn);

        $up = "        \$schema->drop('{$tableName}');";
        $down = "        // TODO: recreate the '{$tableName}' table if needed";

        $code = strtr(self::TEMPLATE, [
            '<namespace>' => $namespace,
            '<className>' => $className,
            '<up>' => $up,
            '<down>' => $down,
        ]);

        return $this->writeFile($dir, $className, $code);
    }

    /**
     * @return array{path: ?string, warnings: list<string>}
     *
     * @throws UnsupportedOperationException for operations that cannot be safely skipped (column modifications, renames)
     */
    public function generate(string $fqcn, string $dir, SchemaDiff $diff): array
    {
        $warnings = $this->analyzeScope($diff);

        $upCode = $this->generateUp($diff);
        $downCode = $this->generateDown($diff);

        if (trim($upCode) === '' && trim($downCode) === '') {
            return ['path' => null, 'warnings' => $warnings];
        }

        [$namespace, $className] = $this->parseFqcn($fqcn);

        $code = strtr(self::TEMPLATE, [
            '<namespace>' => $namespace,
            '<className>' => $className,
            '<up>' => $upCode,
            '<down>' => $downCode,
        ]);

        $path = $this->writeFile($dir, $className, $code);

        return ['path' => $path, 'warnings' => $warnings];
    }

    /**
     * @return list<string> Warnings for skipped features
     *
     * @throws UnsupportedOperationException for operations that affect data correctness
     */
    private function analyzeScope(SchemaDiff $diff): array
    {
        $warnings = [];

        // Warn about indexes/FKs on new tables
        foreach ($diff->getCreatedTables() as $table) {
            $tableName = $table->getName();

            foreach ($table->getIndexes() as $index) {
                if ($index->isPrimary()) {
                    continue;
                }

                $columns = implode(', ', $index->getColumns());
                $type = $index->isUnique() ? 'unique index' : 'index';
                $warnings[] = sprintf(
                    'Table "%s": %s on (%s) was not included — indexes are not supported in Schema API v1. '
                    . 'Create a manual migration with `db:migrate:create` to add it.',
                    $tableName,
                    $type,
                    $columns,
                );
            }

            if (count($table->getForeignKeys()) > 0) {
                $warnings[] = sprintf(
                    'Table "%s": foreign key constraints were not included — foreign keys are not supported in Schema API v1. '
                    . 'Create a manual migration with `db:migrate:create` to add them.',
                    $tableName,
                );
            }
        }

        // Validate altered tables
        foreach ($diff->getAlteredTables() as $tableDiff) {
            $tableName = $tableDiff->getOldTable()->getName();

            // Column modifications and renames affect data — cannot be safely skipped
            if (count($tableDiff->getChangedColumns()) > 0) {
                $colNames = implode(', ', array_keys($tableDiff->getChangedColumns()));
                throw new UnsupportedOperationException(
                    sprintf(
                        'Column(s) "%s" on table "%s" were modified. '
                        . 'Scafera Schema API v1 does not support column modifications. '
                        . 'Use `db:migrate:create` to write a manual migration.',
                        $colNames,
                        $tableName,
                    ),
                );
            }

            // Index and FK changes on existing tables can be safely skipped with a warning
            foreach ($tableDiff->getAddedIndexes() as $index) {
                $columns = implode(', ', $index->getColumns());
                $type = $index->isUnique() ? 'unique index' : 'index';
                $warnings[] = sprintf(
                    'Table "%s": %s on (%s) was not included — indexes are not supported in Schema API v1. '
                    . 'Create a manual migration with `db:migrate:create` to add it.',
                    $tableName,
                    $type,
                    $columns,
                );
            }

            if (count($tableDiff->getDroppedIndexes()) > 0) {
                $warnings[] = sprintf(
                    'Table "%s": index drop(s) were not included — indexes are not supported in Schema API v1. '
                    . 'Create a manual migration with `db:migrate:create` to handle this.',
                    $tableName,
                );
            }

            if (count($tableDiff->getAddedForeignKeys()) > 0 || count($tableDiff->getDroppedForeignKeys()) > 0) {
                $warnings[] = sprintf(
                    'Table "%s": foreign key changes were not included — foreign keys are not supported in Schema API v1. '
                    . 'Create a manual migration with `db:migrate:create` to handle this.',
                    $tableName,
                );
            }

            if (count($tableDiff->getRenamedIndexes()) > 0) {
                $warnings[] = sprintf(
                    'Table "%s": index rename(s) were not included — index renames are not supported in Schema API v1. '
                    . 'Create a manual migration with `db:migrate:create` to handle this.',
                    $tableName,
                );
            }
        }

        return $warnings;
    }

    private function generateUp(SchemaDiff $diff): string
    {
        $lines = [];

        foreach ($diff->getCreatedTables() as $table) {
            $lines[] = $this->generateCreateTable($table);
        }

        foreach ($diff->getAlteredTables() as $tableDiff) {
            $modify = $this->generateModifyTable($tableDiff);
            if ($modify !== null) {
                $lines[] = $modify;
            }
        }

        foreach ($diff->getDroppedTables() as $table) {
            $lines[] = $this->indent("        \$schema->drop('{$table->getName()}');", 0);
        }

        return implode("\n\n", $lines);
    }

    private function generateDown(SchemaDiff $diff): string
    {
        $lines = [];

        foreach ($diff->getDroppedTables() as $table) {
            $lines[] = $this->generateCreateTable($table);
        }

        foreach ($diff->getAlteredTables() as $tableDiff) {
            $reverse = $this->generateReverseModifyTable($tableDiff);
            if ($reverse !== null) {
                $lines[] = $reverse;
            }
        }

        foreach ($diff->getCreatedTables() as $table) {
            $lines[] = $this->indent("        \$schema->drop('{$table->getName()}');", 0);
        }

        return implode("\n\n", $lines);
    }

    private function generateCreateTable(Table $table): string
    {
        $tableName = $table->getName();
        $columnLines = [];

        $idColumn = $this->detectIdColumn($table);

        if ($idColumn !== null) {
            $columnLines[] = '            $table->id();';
        }

        foreach ($table->getColumns() as $column) {
            if ($idColumn !== null && $column->getName() === $idColumn) {
                continue;
            }

            $columnLines[] = '            ' . $this->generateColumnCall($column);
        }

        $columnsCode = implode("\n", $columnLines);

        return <<<PHP
                \$schema->create('{$tableName}', function (Table \$table) {
        {$columnsCode}
                });
        PHP;
    }

    private function generateModifyTable(TableDiff $tableDiff): ?string
    {
        $added = $tableDiff->getAddedColumns();
        $dropped = $tableDiff->getDroppedColumns();

        if (count($added) === 0 && count($dropped) === 0) {
            return null;
        }

        $tableName = $tableDiff->getOldTable()->getName();
        $columnLines = [];

        foreach ($added as $column) {
            $columnLines[] = '            ' . $this->generateColumnCall($column);
        }

        foreach ($dropped as $column) {
            $columnLines[] = "            \$table->dropColumn('{$column->getName()}');";
        }

        $columnsCode = implode("\n", $columnLines);

        return <<<PHP
                \$schema->modify('{$tableName}', function (Table \$table) {
        {$columnsCode}
                });
        PHP;
    }

    private function generateReverseModifyTable(TableDiff $tableDiff): ?string
    {
        $added = $tableDiff->getAddedColumns();
        $dropped = $tableDiff->getDroppedColumns();

        if (count($added) === 0 && count($dropped) === 0) {
            return null;
        }

        $tableName = $tableDiff->getOldTable()->getName();
        $columnLines = [];

        foreach ($dropped as $column) {
            $columnLines[] = '            ' . $this->generateColumnCall($column);
        }

        foreach ($added as $column) {
            $columnLines[] = "            \$table->dropColumn('{$column->getName()}');";
        }

        $columnsCode = implode("\n", $columnLines);

        return <<<PHP
                \$schema->modify('{$tableName}', function (Table \$table) {
        {$columnsCode}
                });
        PHP;
    }

    private function generateColumnCall(Column $column): string
    {
        $type = $this->typeMapper->fromDoctrine($column->getType());
        $methodName = $this->typeMapper->toMethodName($type);
        $name = $column->getName();

        $args = "'{$name}'";

        if ($type === \Scafera\Database\Schema\ColumnType::String && $column->getLength() !== null) {
            $args .= ", {$column->getLength()}";
        }

        if ($type === \Scafera\Database\Schema\ColumnType::Decimal) {
            $precision = $column->getPrecision() ?? 8;
            $scale = $column->getScale();
            $args .= ", {$precision}, {$scale}";
        }

        $call = "\$table->{$methodName}({$args})";

        if (!$column->getNotnull()) {
            $call .= '->nullable()';
        }

        if ($column->getDefault() !== null) {
            $default = var_export($column->getDefault(), true);
            $call .= "->default({$default})";
        }

        return $call . ';';
    }

    private function detectIdColumn(Table $table): ?string
    {
        $pk = $table->getPrimaryKeyConstraint();

        if ($pk === null) {
            return null;
        }

        $pkColumns = $pk->getColumnNames();

        if (count($pkColumns) !== 1) {
            return null;
        }

        $pkColName = $pkColumns[0] instanceof \Doctrine\DBAL\Schema\Name\UnqualifiedName
            ? $pkColumns[0]->toString()
            : (string) $pkColumns[0];
        $column = $table->getColumn($pkColName);

        if (!$column->getAutoincrement()) {
            return null;
        }

        $typeName = \Doctrine\DBAL\Types\Type::lookupName($column->getType());

        if (!in_array($typeName, ['integer', 'bigint', 'smallint'], true)) {
            return null;
        }

        return $pkColName;
    }

    private function indent(string $code, int $level): string
    {
        $prefix = str_repeat('    ', $level);

        return $prefix . $code;
    }

    /** @return array{string, string} */
    private function parseFqcn(string $fqcn): array
    {
        if (preg_match('~(.*)\\\\([^\\\\]+)~', $fqcn, $matches) !== 1) {
            throw new \InvalidArgumentException('Invalid FQCN: ' . $fqcn);
        }

        return [$matches[1], $matches[2]];
    }

    private function writeFile(string $dir, string $className, string $code): string
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = rtrim($dir, '/') . '/' . $className . '.php';
        file_put_contents($path, $code);

        return $path;
    }
}
