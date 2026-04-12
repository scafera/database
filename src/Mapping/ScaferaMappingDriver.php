<?php

declare(strict_types=1);

namespace Scafera\Database\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Scafera\Database\Mapping\Contract\FieldAttribute;
use Scafera\Database\Mapping\Field\Id;
use Scafera\Database\Mapping\Field\Uuid;
use Scafera\Kernel\Tool\FileFinder;

/** @internal */
final class ScaferaMappingDriver implements MappingDriver
{
    public function __construct(
        private readonly string $entityDir,
        private readonly string $entityNamespace,
    ) {
    }

    public function loadMetadataForClass(string $className, ClassMetadataInterface $metadata): void
    {
        assert($metadata instanceof ClassMetadata);

        $ref = new \ReflectionClass($className);
        $tableName = $this->resolveTableName($ref);
        $metadata->setPrimaryTable(['name' => $tableName]);

        foreach ($ref->getProperties() as $property) {
            $fieldAttr = $this->findFieldAttribute($property);

            if ($fieldAttr === null) {
                continue;
            }

            $mapping = [
                'fieldName' => $property->getName(),
                'type' => $fieldAttr->doctrineType(),
                'nullable' => $property->getType() instanceof \ReflectionNamedType && $property->getType()->allowsNull(),
            ];

            $options = $fieldAttr->doctrineOptions();

            if (isset($options['length'])) {
                $mapping['length'] = $options['length'];
            }

            if (isset($options['precision'])) {
                $mapping['precision'] = $options['precision'];
            }

            if (isset($options['scale'])) {
                $mapping['scale'] = $options['scale'];
            }

            if (isset($options['unsigned'])) {
                $mapping['options'] = ['unsigned' => $options['unsigned']];
            }

            // Handle identity fields — always NOT NULL regardless of PHP type
            if ($fieldAttr instanceof Id) {
                $mapping['id'] = true;
                $mapping['nullable'] = false;
                $metadata->mapField($mapping);
                $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
                continue;
            }

            if ($fieldAttr instanceof Uuid) {
                $mapping['id'] = true;
                $mapping['nullable'] = false;
                $metadata->mapField($mapping);
                $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
                continue;
            }

            $metadata->mapField($mapping);
        }
    }

    /** @return list<class-string> */
    public function getAllClassNames(): array
    {
        if (!is_dir($this->entityDir)) {
            return [];
        }

        $classes = [];

        foreach (FileFinder::findPhpFiles($this->entityDir) as $file) {
            $relative = str_replace($this->entityDir . '/', '', $file);
            $className = $this->entityNamespace . '\\' . str_replace(['/', '.php'], ['\\', ''], $relative);

            if (!class_exists($className)) {
                continue;
            }

            if (!$this->isTransient($className)) {
                $classes[] = $className;
            }
        }

        return $classes;
    }

    public function isTransient(string $className): bool
    {
        if (!class_exists($className)) {
            return true;
        }

        $ref = new \ReflectionClass($className);

        foreach ($ref->getProperties() as $property) {
            if ($this->findFieldAttribute($property) !== null) {
                return false;
            }
        }

        return true;
    }

    private function findFieldAttribute(\ReflectionProperty $property): ?FieldAttribute
    {
        foreach ($property->getAttributes() as $attr) {
            try {
                $instance = $attr->newInstance();
            } catch (\Throwable) {
                continue;
            }

            if ($instance instanceof FieldAttribute) {
                return $instance;
            }
        }

        return null;
    }

    private function resolveTableName(\ReflectionClass $ref): string
    {
        $tableAttrs = $ref->getAttributes(Table::class);

        if ($tableAttrs !== []) {
            return $tableAttrs[0]->newInstance()->name;
        }

        return $this->deriveTableName($ref->getShortName());
    }

    private function deriveTableName(string $shortName): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($shortName)));
    }
}
