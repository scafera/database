<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Migration;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Scafera\Database\Migration\CodeGenerator;
use Scafera\Database\Migration\UnsupportedOperationException;

class CodeGeneratorTest extends TestCase
{
    private CodeGenerator $generator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->generator = new CodeGenerator();
        $this->tmpDir = sys_get_temp_dir() . '/scafera_codegen_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*.php');
        foreach ($files as $file) {
            unlink($file);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testGenerateBlankCreatesFile(): void
    {
        $path = $this->generator->generateBlank('App\\Migrations\\Version20260403000000', $this->tmpDir);

        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('namespace App\\Migrations;', $content);
        $this->assertStringContainsString('class Version20260403000000 extends Migration', $content);
        $this->assertStringContainsString('use Scafera\\Database\\Migration;', $content);
        $this->assertStringContainsString('use Scafera\\Database\\Schema\\Schema;', $content);
        $this->assertStringContainsString('use Scafera\\Database\\Schema\\Table;', $content);
        $this->assertStringNotContainsString('Doctrine', $content);
    }

    public function testGenerateCreateTable(): void
    {
        $table = new Table('pages');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('title', 'string', ['length' => 255]);
        $table->addColumn('content', 'text');
        $table->addColumn('published', 'boolean');
        $table->setPrimaryKey(['id']);

        $diff = new SchemaDiff([], [], [$table], [], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000001', $this->tmpDir, $diff);

        $this->assertFileExists($result['path']);
        $this->assertSame([], $result['warnings']);

        $content = file_get_contents($result['path']);
        $this->assertStringContainsString('$schema->create(\'pages\'', $content);
        $this->assertStringContainsString('$table->id();', $content);
        $this->assertStringContainsString('$table->string(\'title\', 255);', $content);
        $this->assertStringContainsString('$table->text(\'content\');', $content);
        $this->assertStringContainsString('$table->boolean(\'published\');', $content);
        $this->assertStringNotContainsString('Doctrine', $content);
    }

    public function testGenerateDropTable(): void
    {
        $table = new Table('legacy');
        $table->addColumn('id', 'integer');

        $diff = new SchemaDiff([], [], [], [], [$table], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000002', $this->tmpDir, $diff);

        $content = file_get_contents($result['path']);

        // up() should drop
        $this->assertStringContainsString('$schema->drop(\'legacy\')', $content);
        // down() should recreate
        $this->assertStringContainsString('$schema->create(\'legacy\'', $content);
    }

    public function testGenerateModifyTableAddColumn(): void
    {
        $oldTable = new Table('users');
        $oldTable->addColumn('id', 'integer');

        $newColumn = new Column('email', Type::getType('string'), ['length' => 255]);

        $tableDiff = new TableDiff(
            oldTable: $oldTable,
            addedColumns: [$newColumn],
        );

        $diff = new SchemaDiff([], [], [], [$tableDiff], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000003', $this->tmpDir, $diff);

        $content = file_get_contents($result['path']);

        // up() should modify with add
        $this->assertStringContainsString('$schema->modify(\'users\'', $content);
        $this->assertStringContainsString('$table->string(\'email\', 255);', $content);

        // down() should modify with drop
        $this->assertStringContainsString('$table->dropColumn(\'email\')', $content);
    }

    public function testGenerateModifyTableDropColumn(): void
    {
        $oldTable = new Table('users');
        $oldTable->addColumn('id', 'integer');
        $oldTable->addColumn('legacy', 'string', ['length' => 100]);

        $droppedColumn = new Column('legacy', Type::getType('string'), ['length' => 100]);

        $tableDiff = new TableDiff(
            oldTable: $oldTable,
            droppedColumns: [$droppedColumn],
        );

        $diff = new SchemaDiff([], [], [], [$tableDiff], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000004', $this->tmpDir, $diff);

        $content = file_get_contents($result['path']);

        // up() should drop the column
        $this->assertStringContainsString('$table->dropColumn(\'legacy\')', $content);
        // down() should re-add it
        $this->assertStringContainsString('$table->string(\'legacy\', 100);', $content);
    }

    public function testGenerateNullableColumn(): void
    {
        $table = new Table('users');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('bio', 'text', ['notnull' => false]);
        $table->setPrimaryKey(['id']);

        $diff = new SchemaDiff([], [], [$table], [], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000005', $this->tmpDir, $diff);

        $content = file_get_contents($result['path']);
        $this->assertStringContainsString('->nullable()', $content);
    }

    public function testGenerateColumnWithDefault(): void
    {
        $table = new Table('posts');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('status', 'string', ['length' => 20, 'default' => 'draft']);
        $table->setPrimaryKey(['id']);

        $diff = new SchemaDiff([], [], [$table], [], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000006', $this->tmpDir, $diff);

        $content = file_get_contents($result['path']);
        $this->assertStringContainsString("->default('draft')", $content);
    }

    public function testGenerateDecimalColumn(): void
    {
        $table = new Table('products');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2]);
        $table->setPrimaryKey(['id']);

        $diff = new SchemaDiff([], [], [$table], [], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000007', $this->tmpDir, $diff);

        $content = file_get_contents($result['path']);
        $this->assertStringContainsString('$table->decimal(\'price\', 10, 2);', $content);
    }

    public function testWarningForIndexOnCreatedTable(): void
    {
        $table = new Table('pages');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('slug', 'string', ['length' => 255]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['slug'], 'uniq_slug');

        $diff = new SchemaDiff([], [], [$table], [], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000008', $this->tmpDir, $diff);

        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('unique index', $result['warnings'][0]);
        $this->assertStringContainsString('slug', $result['warnings'][0]);
        // Migration should still be generated
        $this->assertNotNull($result['path']);
    }

    public function testWarningForRegularIndexOnCreatedTable(): void
    {
        $table = new Table('pages');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('title', 'string', ['length' => 255]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['title'], 'idx_title');

        $diff = new SchemaDiff([], [], [$table], [], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000009', $this->tmpDir, $diff);

        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('index', $result['warnings'][0]);
    }

    public function testWarningForForeignKeyOnCreatedTable(): void
    {
        $table = new Table('posts');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer');
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('users', ['user_id'], ['id']);

        $diff = new SchemaDiff([], [], [$table], [], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000010', $this->tmpDir, $diff);

        $this->assertNotEmpty($result['warnings']);
        $found = false;
        foreach ($result['warnings'] as $warning) {
            if (str_contains($warning, 'foreign key')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected a foreign key warning');
    }

    public function testWarningForIndexOnAlteredTable(): void
    {
        $oldTable = new Table('users');
        $oldTable->addColumn('id', 'integer');
        $oldTable->addColumn('email', 'string', ['length' => 255]);

        $index = new Index('idx_email', ['email']);

        $tableDiff = new TableDiff(
            oldTable: $oldTable,
            addedIndexes: [$index],
        );

        $diff = new SchemaDiff([], [], [], [$tableDiff], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000011', $this->tmpDir, $diff);

        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('index', $result['warnings'][0]);
    }

    public function testThrowsForColumnModification(): void
    {
        $oldTable = new Table('users');
        $oldTable->addColumn('name', 'string', ['length' => 100]);

        $oldColumn = new Column('name', Type::getType('string'), ['length' => 100]);
        $newColumn = new Column('name', Type::getType('string'), ['length' => 255]);

        $columnDiff = new ColumnDiff($oldColumn, $newColumn);

        $tableDiff = new TableDiff(
            oldTable: $oldTable,
            changedColumns: ['name' => $columnDiff],
        );

        $diff = new SchemaDiff([], [], [], [$tableDiff], [], [], [], []);

        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessage('modified');

        $this->generator->generate('App\\Migrations\\Version20260403000012', $this->tmpDir, $diff);
    }

    public function testNullPathWhenOnlyUnsupportedChanges(): void
    {
        $oldTable = new Table('users');
        $oldTable->addColumn('id', 'integer');
        $oldTable->addColumn('email', 'string', ['length' => 255]);

        $index = new Index('idx_email', ['email']);

        $tableDiff = new TableDiff(
            oldTable: $oldTable,
            addedIndexes: [$index],
        );

        $diff = new SchemaDiff([], [], [], [$tableDiff], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000013', $this->tmpDir, $diff);

        $this->assertNull($result['path']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function testPrimaryKeyNotReportedAsIndex(): void
    {
        $table = new Table('items');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->setPrimaryKey(['id']);

        $diff = new SchemaDiff([], [], [$table], [], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000014', $this->tmpDir, $diff);

        $this->assertSame([], $result['warnings']);
    }

    public function testInvalidFqcnThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->generator->generateBlank('NoNamespace', $this->tmpDir);
    }

    public function testTimestampColumnType(): void
    {
        $table = new Table('events');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('createdAt', 'datetime_immutable');
        $table->setPrimaryKey(['id']);

        $diff = new SchemaDiff([], [], [$table], [], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000015', $this->tmpDir, $diff);

        $content = file_get_contents($result['path']);
        $this->assertStringContainsString('$table->timestamp(\'createdAt\')', $content);
    }

    public function testDateColumnType(): void
    {
        $table = new Table('events');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('eventDate', 'date_immutable');
        $table->setPrimaryKey(['id']);

        $diff = new SchemaDiff([], [], [$table], [], [], [], [], []);

        $result = $this->generator->generate('App\\Migrations\\Version20260403000016', $this->tmpDir, $diff);

        $content = file_get_contents($result['path']);
        $this->assertStringContainsString('$table->date(\'eventDate\')', $content);
    }
}
