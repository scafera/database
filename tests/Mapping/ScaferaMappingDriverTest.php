<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Scafera\Database\Mapping\ScaferaMappingDriver;
use Scafera\Database\Tests\Mapping\Fixtures\AuditableEntity;
use Scafera\Database\Tests\Mapping\Fixtures\BlogPost;
use Scafera\Database\Tests\Mapping\Fixtures\Category;
use Scafera\Database\Tests\Mapping\Fixtures\EscapeHatchEntity;
use Scafera\Database\Tests\Mapping\Fixtures\FullEntity;
use Scafera\Database\Tests\Mapping\Fixtures\NotAnEntity;
use Scafera\Database\Tests\Mapping\Fixtures\NullableEntity;
use Scafera\Database\Tests\Mapping\Fixtures\SimpleEntity;
use Scafera\Database\Tests\Mapping\Fixtures\UuidEntity;

class ScaferaMappingDriverTest extends TestCase
{
    private ScaferaMappingDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new ScaferaMappingDriver(
            __DIR__ . '/Fixtures',
            'Scafera\\Database\\Tests\\Mapping\\Fixtures',
        );
    }

    public function testGetAllClassNamesDiscoversMappedClasses(): void
    {
        $classes = $this->driver->getAllClassNames();

        $this->assertContains(SimpleEntity::class, $classes);
        $this->assertContains(FullEntity::class, $classes);
        $this->assertContains(AuditableEntity::class, $classes);
        $this->assertNotContains(NotAnEntity::class, $classes);
    }

    public function testIsTransientReturnsTrueForNonEntity(): void
    {
        $this->assertTrue($this->driver->isTransient(NotAnEntity::class));
    }

    public function testIsTransientReturnsFalseForEntity(): void
    {
        $this->assertFalse($this->driver->isTransient(SimpleEntity::class));
    }

    public function testSimpleEntityTableName(): void
    {
        $metadata = new ClassMetadata(SimpleEntity::class);
        $this->driver->loadMetadataForClass(SimpleEntity::class, $metadata);

        $this->assertSame('simple_entity', $metadata->getTableName());
    }

    public function testSimpleEntityFieldMappings(): void
    {
        $metadata = new ClassMetadata(SimpleEntity::class);
        $this->driver->loadMetadataForClass(SimpleEntity::class, $metadata);

        $this->assertTrue($metadata->hasField('id'));
        $this->assertTrue($metadata->hasField('name'));

        $idMapping = $metadata->getFieldMapping('id');
        $this->assertSame('integer', $idMapping->type);

        $nameMapping = $metadata->getFieldMapping('name');
        $this->assertSame('string', $nameMapping->type);
        $this->assertSame(255, $nameMapping->length);
    }

    public function testIdGeneratorTypeIdentity(): void
    {
        $metadata = new ClassMetadata(SimpleEntity::class);
        $this->driver->loadMetadataForClass(SimpleEntity::class, $metadata);

        $this->assertTrue($metadata->isIdGeneratorIdentity());
    }

    public function testUuidGeneratorTypeNone(): void
    {
        $metadata = new ClassMetadata(UuidEntity::class);
        $this->driver->loadMetadataForClass(UuidEntity::class, $metadata);

        $this->assertTrue($metadata->isIdentifierNatural());
        $idMapping = $metadata->getFieldMapping('id');
        $this->assertSame('guid', $idMapping->type);
    }

    public function testNullableDetectedFromPhpType(): void
    {
        $metadata = new ClassMetadata(NullableEntity::class);
        $this->driver->loadMetadataForClass(NullableEntity::class, $metadata);

        $bioMapping = $metadata->getFieldMapping('bio');
        $this->assertTrue($bioMapping->nullable);

        $idMapping = $metadata->getFieldMapping('id');
        $this->assertFalse($idMapping->nullable);
    }

    public function testAuditableTraitFieldsAreMapped(): void
    {
        $metadata = new ClassMetadata(AuditableEntity::class);
        $this->driver->loadMetadataForClass(AuditableEntity::class, $metadata);

        $this->assertTrue($metadata->hasField('createdAt'));
        $this->assertTrue($metadata->hasField('updatedAt'));

        $createdMapping = $metadata->getFieldMapping('createdAt');
        $this->assertSame('datetime_immutable', $createdMapping->type);
        $this->assertFalse($createdMapping->nullable);

        $updatedMapping = $metadata->getFieldMapping('updatedAt');
        $this->assertSame('datetime_immutable', $updatedMapping->type);
        $this->assertTrue($updatedMapping->nullable);
    }

    public function testEscapeHatchPassesThrough(): void
    {
        $metadata = new ClassMetadata(EscapeHatchEntity::class);
        $this->driver->loadMetadataForClass(EscapeHatchEntity::class, $metadata);

        $isoMapping = $metadata->getFieldMapping('isoCode');
        $this->assertSame('string', $isoMapping->type);
        $this->assertSame(15, $isoMapping->length);
    }

    public function testTableNameConventionPage(): void
    {
        // Page → pages (simple plural)
        $metadata = new ClassMetadata(SimpleEntity::class);
        $this->driver->loadMetadataForClass(SimpleEntity::class, $metadata);
        // SimpleEntity → simple_entity (singular snake_case, no pluralization)
        $this->assertSame('simple_entity', $metadata->getTableName());
    }

    public function testTableAttributeOverridesDefaultName(): void
    {
        $metadata = new ClassMetadata(Category::class);
        $this->driver->loadMetadataForClass(Category::class, $metadata);

        // Category uses #[Table(name: 'categories')] to override the default
        $this->assertSame('categories', $metadata->getTableName());
    }

    public function testTableNameConventionBlogPost(): void
    {
        $metadata = new ClassMetadata(BlogPost::class);
        $this->driver->loadMetadataForClass(BlogPost::class, $metadata);

        // BlogPost → blog_post (singular snake_case, no pluralization)
        $this->assertSame('blog_post', $metadata->getTableName());
    }

    public function testFullEntityMapsAllTypes(): void
    {
        $metadata = new ClassMetadata(FullEntity::class);
        $this->driver->loadMetadataForClass(FullEntity::class, $metadata);

        $expectations = [
            'title' => 'string',
            'code' => 'string',
            'body' => 'text',
            'count' => 'integer',
            'bigCount' => 'bigint',
            'views' => 'bigint',
            'price' => 'decimal',
            'active' => 'boolean',
            'createdAt' => 'datetime_immutable',
            'birthDate' => 'date_immutable',
            'startTime' => 'time',
            'metadata' => 'json',
            'lastLogin' => 'integer',
            'balance' => 'decimal',
            'taxRate' => 'decimal',
        ];

        foreach ($expectations as $field => $type) {
            $this->assertTrue($metadata->hasField($field), "Missing field: $field");
            $mapping = $metadata->getFieldMapping($field);
            $this->assertSame($type, $mapping->type, "Wrong type for $field");
        }
    }

    public function testGetAllClassNamesWithNonExistentDir(): void
    {
        $driver = new ScaferaMappingDriver('/nonexistent/path', 'Fake\\Namespace');
        $this->assertSame([], $driver->getAllClassNames());
    }

    public function testIsTransientWithNonExistentClass(): void
    {
        $this->assertTrue($this->driver->isTransient('NonExistent\\Class'));
    }
}
