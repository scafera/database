<?php

declare(strict_types=1);

namespace Scafera\Database\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Scafera\Database\Mapping\ScaferaMappingDriver;

/** @internal */
final class SchemaDiffInspector
{
    private const TYPE_EQUIVALENTS = [
        'datetime_immutable' => 'datetime',
        'date_immutable' => 'date',
        'time_immutable' => 'time',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly ScaferaMappingDriver $mappingDriver,
    ) {
    }

    /** @return list<array{entity: string, table: string, issues: list<array<string, mixed>>}> */
    public function inspect(): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $dbTableNames = array_flip($schemaManager->listTableNames());
        $diffs = [];

        foreach ($this->mappingDriver->getAllClassNames() as $className) {
            $metadata = new ClassMetadata($className);
            $this->mappingDriver->loadMetadataForClass($className, $metadata);

            $tableName = $metadata->getTableName();
            $entityDiff = ['entity' => $className, 'table' => $tableName, 'issues' => []];

            if (!isset($dbTableNames[$tableName])) {
                $entityDiff['issues'][] = [
                    'type' => 'missing_table',
                    'message' => sprintf('Table "%s" does not exist in database', $tableName),
                ];
                $diffs[] = $entityDiff;
                continue;
            }

            $dbTable = $schemaManager->introspectTable($tableName);
            $dbColumns = [];
            foreach ($dbTable->getColumns() as $col) {
                $dbColumns[$col->getName()] = $col;
            }

            foreach ($metadata->getFieldNames() as $fieldName) {
                $fieldMapping = $metadata->getFieldMapping($fieldName);
                $columnName = $fieldMapping->columnName ?? $fieldName;

                if (!isset($dbColumns[$columnName])) {
                    $entityDiff['issues'][] = [
                        'type' => 'missing_column',
                        'field' => $fieldName,
                        'column' => $columnName,
                        'message' => sprintf('Column "%s" missing in database', $columnName),
                    ];
                    continue;
                }

                $dbColumn = $dbColumns[$columnName];
                $dbTypeName = $dbColumn->getType()->getTypeRegistry()->lookupName($dbColumn->getType());

                // Type mismatch (normalize immutable variants — same DB column type)
                if ($this->normalizeType($fieldMapping->type) !== $this->normalizeType($dbTypeName)) {
                    $entityDiff['issues'][] = [
                        'type' => 'type_mismatch',
                        'field' => $fieldName,
                        'column' => $columnName,
                        'entity_type' => $fieldMapping->type,
                        'db_type' => $dbTypeName,
                        'message' => sprintf(
                            'Column "%s": entity expects "%s", database has "%s"',
                            $columnName,
                            $fieldMapping->type,
                            $dbTypeName,
                        ),
                    ];
                }

                // Nullable mismatch (skip for identifier fields — ?int $id is standard DDD)
                if (!$metadata->isIdentifier($fieldName)) {
                    $entityNullable = $fieldMapping->nullable ?? false;
                    $dbNullable = !$dbColumn->getNotnull();
                    if ($entityNullable !== $dbNullable) {
                        $entityDiff['issues'][] = [
                            'type' => 'nullable_mismatch',
                            'field' => $fieldName,
                            'column' => $columnName,
                            'entity_nullable' => $entityNullable,
                            'db_nullable' => $dbNullable,
                            'message' => sprintf(
                                'Column "%s": entity says %s, database says %s',
                                $columnName,
                                $entityNullable ? 'nullable' : 'not nullable',
                                $dbNullable ? 'nullable' : 'not nullable',
                            ),
                        ];
                    }
                }

                unset($dbColumns[$columnName]);
            }

            // Extra columns in database not in entity
            foreach ($dbColumns as $colName => $col) {
                $entityDiff['issues'][] = [
                    'type' => 'extra_column',
                    'column' => $colName,
                    'message' => sprintf('Column "%s" exists in database but not in entity', $colName),
                ];
            }

            if (!empty($entityDiff['issues'])) {
                $diffs[] = $entityDiff;
            }
        }

        return $diffs;
    }

    private function normalizeType(string $type): string
    {
        return self::TYPE_EQUIVALENTS[$type] ?? $type;
    }
}
