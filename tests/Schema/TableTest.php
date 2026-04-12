<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Scafera\Database\Schema\AddColumn;
use Scafera\Database\Schema\ColumnBuilder;
use Scafera\Database\Schema\ColumnType;
use Scafera\Database\Schema\DropColumn;
use Scafera\Database\Schema\Table;

class TableTest extends TestCase
{
    public function testIdSetsHasIdAndAddsColumn(): void
    {
        $table = new Table('users');
        $table->id();

        $this->assertTrue($table->hasId());

        $columns = $table->getAddColumns();
        $this->assertCount(1, $columns);
        $this->assertSame('id', $columns[0]->name);
        $this->assertSame(ColumnType::Integer, $columns[0]->type);
    }

    public function testStringColumn(): void
    {
        $table = new Table('users');
        $builder = $table->string('name');

        $this->assertInstanceOf(ColumnBuilder::class, $builder);

        $columns = $table->getAddColumns();
        $this->assertCount(1, $columns);
        $this->assertSame('name', $columns[0]->name);
        $this->assertSame(ColumnType::String, $columns[0]->type);
        $this->assertSame(255, $columns[0]->length);
    }

    public function testStringColumnWithCustomLength(): void
    {
        $table = new Table('users');
        $table->string('code', 10);

        $columns = $table->getAddColumns();
        $this->assertSame(10, $columns[0]->length);
    }

    public function testTextColumn(): void
    {
        $table = new Table('posts');
        $table->text('body');

        $columns = $table->getAddColumns();
        $this->assertSame(ColumnType::Text, $columns[0]->type);
    }

    public function testIntegerColumn(): void
    {
        $table = new Table('items');
        $table->integer('quantity');

        $columns = $table->getAddColumns();
        $this->assertSame(ColumnType::Integer, $columns[0]->type);
    }

    public function testBigIntegerColumn(): void
    {
        $table = new Table('items');
        $table->bigInteger('views');

        $columns = $table->getAddColumns();
        $this->assertSame(ColumnType::BigInteger, $columns[0]->type);
    }

    public function testSmallIntegerColumn(): void
    {
        $table = new Table('items');
        $table->smallInteger('priority');

        $columns = $table->getAddColumns();
        $this->assertSame(ColumnType::SmallInteger, $columns[0]->type);
    }

    public function testBooleanColumn(): void
    {
        $table = new Table('posts');
        $table->boolean('published');

        $columns = $table->getAddColumns();
        $this->assertSame(ColumnType::Boolean, $columns[0]->type);
    }

    public function testTimestampColumn(): void
    {
        $table = new Table('posts');
        $table->timestamp('createdAt');

        $columns = $table->getAddColumns();
        $this->assertSame(ColumnType::Timestamp, $columns[0]->type);
    }

    public function testDateColumn(): void
    {
        $table = new Table('events');
        $table->date('eventDate');

        $columns = $table->getAddColumns();
        $this->assertSame(ColumnType::Date, $columns[0]->type);
    }

    public function testDecimalColumn(): void
    {
        $table = new Table('products');
        $table->decimal('price', 10, 2);

        $columns = $table->getAddColumns();
        $this->assertSame(ColumnType::Decimal, $columns[0]->type);
        $this->assertSame(10, $columns[0]->precision);
        $this->assertSame(2, $columns[0]->scale);
    }

    public function testDecimalColumnDefaults(): void
    {
        $table = new Table('products');
        $table->decimal('price');

        $columns = $table->getAddColumns();
        $this->assertSame(8, $columns[0]->precision);
        $this->assertSame(2, $columns[0]->scale);
    }

    public function testJsonColumn(): void
    {
        $table = new Table('settings');
        $table->json('metadata');

        $columns = $table->getAddColumns();
        $this->assertSame(ColumnType::Json, $columns[0]->type);
    }

    public function testNullableModifier(): void
    {
        $table = new Table('users');
        $table->string('bio')->nullable();

        $columns = $table->getAddColumns();
        $this->assertTrue($columns[0]->nullable);
    }

    public function testDefaultModifier(): void
    {
        $table = new Table('posts');
        $table->boolean('published')->default(false);

        $columns = $table->getAddColumns();
        $this->assertFalse($columns[0]->default);
    }

    public function testChainedModifiers(): void
    {
        $table = new Table('users');
        $table->string('bio')->nullable()->default('');

        $columns = $table->getAddColumns();
        $this->assertTrue($columns[0]->nullable);
        $this->assertSame('', $columns[0]->default);
    }

    public function testModifiersApplyToCorrectColumn(): void
    {
        $table = new Table('users');
        $table->string('name');
        $table->string('bio')->nullable();
        $table->string('email');

        $columns = $table->getAddColumns();
        $this->assertFalse($columns[0]->nullable); // name
        $this->assertTrue($columns[1]->nullable);   // bio
        $this->assertFalse($columns[2]->nullable);  // email
    }

    public function testDropColumn(): void
    {
        $table = new Table('users');
        $table->dropColumn('legacy');

        $ops = $table->getOperations();
        $this->assertCount(1, $ops);
        $this->assertInstanceOf(DropColumn::class, $ops[0]);
        $this->assertSame('users', $ops[0]->table);
        $this->assertSame('legacy', $ops[0]->name);
    }

    public function testGetOperationsCombinesAddAndDrop(): void
    {
        $table = new Table('users');
        $table->string('email');
        $table->dropColumn('old_email');

        $ops = $table->getOperations();
        $this->assertCount(2, $ops);
        $this->assertInstanceOf(AddColumn::class, $ops[0]);
        $this->assertInstanceOf(DropColumn::class, $ops[1]);
    }

    public function testHasIdDefaultsFalse(): void
    {
        $table = new Table('settings');
        $table->string('key');

        $this->assertFalse($table->hasId());
    }

    public function testMultipleColumns(): void
    {
        $table = new Table('pages');
        $table->id();
        $table->string('title', 255);
        $table->string('slug', 255);
        $table->text('content');
        $table->boolean('published');
        $table->timestamp('createdAt');

        $columns = $table->getAddColumns();
        $this->assertCount(6, $columns);
        $this->assertSame('id', $columns[0]->name);
        $this->assertSame('title', $columns[1]->name);
        $this->assertSame('slug', $columns[2]->name);
        $this->assertSame('content', $columns[3]->name);
        $this->assertSame('published', $columns[4]->name);
        $this->assertSame('createdAt', $columns[5]->name);
    }
}
