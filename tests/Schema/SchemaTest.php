<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Scafera\Database\Schema\AddColumn;
use Scafera\Database\Schema\ColumnType;
use Scafera\Database\Schema\CreateTable;
use Scafera\Database\Schema\DropColumn;
use Scafera\Database\Schema\DropTable;
use Scafera\Database\Schema\Schema;

class SchemaTest extends TestCase
{
    public function testCreateTableRecordsOperation(): void
    {
        $schema = new Schema();
        $schema->create('users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $ops = $schema->getOperations();
        $this->assertCount(1, $ops);
        $this->assertInstanceOf(CreateTable::class, $ops[0]);
        $this->assertSame('users', $ops[0]->table);
        $this->assertTrue($ops[0]->hasId);
        $this->assertCount(2, $ops[0]->columns);
    }

    public function testCreateTableWithoutId(): void
    {
        $schema = new Schema();
        $schema->create('settings', function ($table) {
            $table->string('key');
            $table->text('value');
        });

        $ops = $schema->getOperations();
        $this->assertFalse($ops[0]->hasId);
        $this->assertCount(2, $ops[0]->columns);
    }

    public function testDropTableRecordsOperation(): void
    {
        $schema = new Schema();
        $schema->drop('users');

        $ops = $schema->getOperations();
        $this->assertCount(1, $ops);
        $this->assertInstanceOf(DropTable::class, $ops[0]);
        $this->assertSame('users', $ops[0]->table);
    }

    public function testModifyTableAddColumn(): void
    {
        $schema = new Schema();
        $schema->modify('users', function ($table) {
            $table->string('email');
        });

        $ops = $schema->getOperations();
        $this->assertCount(1, $ops);
        $this->assertInstanceOf(AddColumn::class, $ops[0]);
        $this->assertSame('users', $ops[0]->table);
        $this->assertSame('email', $ops[0]->name);
    }

    public function testModifyTableDropColumn(): void
    {
        $schema = new Schema();
        $schema->modify('users', function ($table) {
            $table->dropColumn('legacy_field');
        });

        $ops = $schema->getOperations();
        $this->assertCount(1, $ops);
        $this->assertInstanceOf(DropColumn::class, $ops[0]);
        $this->assertSame('legacy_field', $ops[0]->name);
    }

    public function testModifyTableMixedOperations(): void
    {
        $schema = new Schema();
        $schema->modify('users', function ($table) {
            $table->string('email');
            $table->dropColumn('old_email');
        });

        $ops = $schema->getOperations();
        $this->assertCount(2, $ops);
        $this->assertInstanceOf(AddColumn::class, $ops[0]);
        $this->assertInstanceOf(DropColumn::class, $ops[1]);
    }

    public function testHasDestructiveOperationsReturnsFalseForSafeOperations(): void
    {
        $schema = new Schema();
        $schema->create('users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $this->assertFalse($schema->hasDestructiveOperations());
    }

    public function testHasDestructiveOperationsReturnsTrueForDropTable(): void
    {
        $schema = new Schema();
        $schema->drop('users');

        $this->assertTrue($schema->hasDestructiveOperations());
    }

    public function testHasDestructiveOperationsReturnsTrueForDropColumn(): void
    {
        $schema = new Schema();
        $schema->modify('users', function ($table) {
            $table->dropColumn('old_field');
        });

        $this->assertTrue($schema->hasDestructiveOperations());
    }

    public function testHasDestructiveOperationsWithMixedOperations(): void
    {
        $schema = new Schema();
        $schema->create('pages', function ($table) {
            $table->id();
            $table->string('title');
        });
        $schema->drop('legacy');

        $this->assertTrue($schema->hasDestructiveOperations());
    }

    public function testEmptySchemaHasNoDestructiveOperations(): void
    {
        $schema = new Schema();
        $this->assertFalse($schema->hasDestructiveOperations());
    }

    public function testMultipleOperationsInSequence(): void
    {
        $schema = new Schema();
        $schema->create('users', function ($table) {
            $table->id();
            $table->string('name');
        });
        $schema->create('posts', function ($table) {
            $table->id();
            $table->text('body');
        });
        $schema->drop('legacy');

        $ops = $schema->getOperations();
        $this->assertCount(3, $ops);
        $this->assertInstanceOf(CreateTable::class, $ops[0]);
        $this->assertInstanceOf(CreateTable::class, $ops[1]);
        $this->assertInstanceOf(DropTable::class, $ops[2]);
    }
}
