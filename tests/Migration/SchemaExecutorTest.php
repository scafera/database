<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Migration;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Scafera\Database\Migration\SchemaExecutor;
use Scafera\Database\Schema\AddColumn;
use Scafera\Database\Schema\ColumnType;
use Scafera\Database\Schema\CreateTable;
use Scafera\Database\Schema\DropColumn;
use Scafera\Database\Schema\DropTable;
use Scafera\Database\Schema\Schema;

class SchemaExecutorTest extends TestCase
{
    private SchemaExecutor $executor;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->executor = new SchemaExecutor($connection);
    }

    public function testCreateTableProducesSql(): void
    {
        $schema = new Schema();
        $schema->create('users', function ($table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 255);
        });

        $sql = $this->executor->toSql($schema);

        $this->assertNotEmpty($sql);
        $combined = implode(' ', $sql);
        $this->assertStringContainsStringIgnoringCase('CREATE TABLE', $combined);
        $this->assertStringContainsString('users', $combined);
        $this->assertStringContainsString('name', $combined);
        $this->assertStringContainsString('email', $combined);
    }

    public function testCreateTableWithIdSetsPrimaryKey(): void
    {
        $schema = new Schema();
        $schema->create('items', function ($table) {
            $table->id();
            $table->string('title');
        });

        $sql = $this->executor->toSql($schema);
        $combined = implode(' ', $sql);

        $this->assertStringContainsStringIgnoringCase('PRIMARY KEY', $combined);
        $this->assertStringContainsStringIgnoringCase('AUTOINCREMENT', $combined);
    }

    public function testCreateTableWithoutIdHasNoPrimaryKey(): void
    {
        $schema = new Schema();
        $schema->create('settings', function ($table) {
            $table->string('key', 100);
            $table->text('value');
        });

        $sql = $this->executor->toSql($schema);
        $combined = implode(' ', $sql);

        $this->assertStringNotContainsStringIgnoringCase('PRIMARY KEY', $combined);
    }

    public function testDropTableProducesSql(): void
    {
        $schema = new Schema();
        $schema->drop('legacy');

        $sql = $this->executor->toSql($schema);

        $this->assertCount(1, $sql);
        $this->assertStringContainsStringIgnoringCase('DROP TABLE', $sql[0]);
        $this->assertStringContainsString('legacy', $sql[0]);
    }

    public function testAddColumnProducesSql(): void
    {
        $schema = new Schema();
        $schema->modify('users', function ($table) {
            $table->string('email', 255);
        });

        $sql = $this->executor->toSql($schema);

        $this->assertNotEmpty($sql);
        $combined = implode(' ', $sql);
        $this->assertStringContainsStringIgnoringCase('ALTER TABLE', $combined);
        $this->assertStringContainsString('email', $combined);
    }

    public function testDropColumnProducesSql(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $executor = new SchemaExecutor($connection);

        // Create the table first so introspection works
        $createSchema = new Schema();
        $createSchema->create('users', function ($table) {
            $table->id();
            $table->string('name', 100);
            $table->string('legacy_field', 255);
        });

        foreach ($executor->toSql($createSchema) as $statement) {
            $connection->executeStatement($statement);
        }

        // Now drop a column
        $modifySchema = new Schema();
        $modifySchema->modify('users', function ($table) {
            $table->dropColumn('legacy_field');
        });

        $sql = $executor->toSql($modifySchema);

        $this->assertNotEmpty($sql);
    }

    public function testNullableColumn(): void
    {
        $schema = new Schema();
        $schema->create('posts', function ($table) {
            $table->id();
            $table->text('bio')->nullable();
        });

        $sql = $this->executor->toSql($schema);
        $combined = implode(' ', $sql);

        $this->assertStringContainsString('bio', $combined);
        $this->assertStringNotContainsStringIgnoringCase('bio VARCHAR', $combined);
    }

    public function testColumnWithDefault(): void
    {
        $schema = new Schema();
        $schema->create('posts', function ($table) {
            $table->id();
            $table->boolean('published')->default(false);
        });

        $sql = $this->executor->toSql($schema);
        $combined = implode(' ', $sql);

        $this->assertStringContainsString('published', $combined);
        $this->assertStringContainsStringIgnoringCase('DEFAULT', $combined);
    }

    public function testDecimalColumn(): void
    {
        $schema = new Schema();
        $schema->create('products', function ($table) {
            $table->id();
            $table->decimal('price', 10, 2);
        });

        $sql = $this->executor->toSql($schema);
        $combined = implode(' ', $sql);

        $this->assertStringContainsString('price', $combined);
        $this->assertStringContainsStringIgnoringCase('NUMERIC', $combined);
    }

    public function testAllColumnTypes(): void
    {
        $schema = new Schema();
        $schema->create('everything', function ($table) {
            $table->id();
            $table->string('a_string', 100);
            $table->text('a_text');
            $table->integer('an_int');
            $table->bigInteger('a_bigint');
            $table->smallInteger('a_smallint');
            $table->boolean('a_bool');
            $table->timestamp('a_timestamp');
            $table->date('a_date');
            $table->decimal('a_decimal', 10, 4);
            $table->json('a_json');
        });

        $sql = $this->executor->toSql($schema);
        $combined = implode(' ', $sql);

        $this->assertStringContainsString('a_string', $combined);
        $this->assertStringContainsString('a_text', $combined);
        $this->assertStringContainsString('an_int', $combined);
        $this->assertStringContainsString('a_bigint', $combined);
        $this->assertStringContainsString('a_smallint', $combined);
        $this->assertStringContainsString('a_bool', $combined);
        $this->assertStringContainsString('a_timestamp', $combined);
        $this->assertStringContainsString('a_date', $combined);
        $this->assertStringContainsString('a_decimal', $combined);
        $this->assertStringContainsString('a_json', $combined);
    }

    public function testMixedOperationsInSequence(): void
    {
        $schema = new Schema();
        $schema->create('users', function ($table) {
            $table->id();
            $table->string('name');
        });
        $schema->modify('users', function ($table) {
            $table->string('email');
        });
        $schema->drop('legacy');

        $sql = $this->executor->toSql($schema);

        $this->assertGreaterThanOrEqual(3, count($sql));

        $combined = implode(' ', $sql);
        $this->assertStringContainsStringIgnoringCase('CREATE TABLE', $combined);
        $this->assertStringContainsStringIgnoringCase('ALTER TABLE', $combined);
        $this->assertStringContainsStringIgnoringCase('DROP TABLE', $combined);
    }

    public function testEmptySchemaProducesNoSql(): void
    {
        $schema = new Schema();

        $sql = $this->executor->toSql($schema);

        $this->assertSame([], $sql);
    }

    public function testCreateTableExecutesAgainstDatabase(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $executor = new SchemaExecutor($connection);

        $schema = new Schema();
        $schema->create('pages', function ($table) {
            $table->id();
            $table->string('title', 255);
            $table->text('content');
            $table->boolean('published');
            $table->timestamp('createdAt');
        });

        $sql = $executor->toSql($schema);

        foreach ($sql as $statement) {
            $connection->executeStatement($statement);
        }

        // Verify table exists by inserting a row
        $connection->executeStatement(
            "INSERT INTO pages (title, content, published, createdAt) VALUES ('Test', 'Hello', 1, '2026-01-01 00:00:00')",
        );

        $rows = $connection->fetchAllAssociative('SELECT * FROM pages');
        $this->assertCount(1, $rows);
        $this->assertSame('Test', $rows[0]['title']);
    }

    public function testModifyTableExecutesAgainstDatabase(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $executor = new SchemaExecutor($connection);

        // First create the table
        $createSchema = new Schema();
        $createSchema->create('users', function ($table) {
            $table->id();
            $table->string('name', 100);
        });

        foreach ($executor->toSql($createSchema) as $statement) {
            $connection->executeStatement($statement);
        }

        // Then add a column
        $modifySchema = new Schema();
        $modifySchema->modify('users', function ($table) {
            $table->string('email', 255)->nullable();
        });

        foreach ($executor->toSql($modifySchema) as $statement) {
            $connection->executeStatement($statement);
        }

        // Verify new column works
        $connection->executeStatement(
            "INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')",
        );

        $rows = $connection->fetchAllAssociative('SELECT * FROM users');
        $this->assertCount(1, $rows);
        $this->assertSame('alice@example.com', $rows[0]['email']);
    }

    public function testDropColumnExecutesAgainstDatabase(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $executor = new SchemaExecutor($connection);

        // Create table with extra column
        $createSchema = new Schema();
        $createSchema->create('articles', function ($table) {
            $table->id();
            $table->string('title', 255);
            $table->string('legacy_field', 100);
        });

        foreach ($executor->toSql($createSchema) as $statement) {
            $connection->executeStatement($statement);
        }

        // Insert data before dropping
        $connection->executeStatement(
            "INSERT INTO articles (title, legacy_field) VALUES ('Test', 'old')",
        );

        // Drop the column
        $modifySchema = new Schema();
        $modifySchema->modify('articles', function ($table) {
            $table->dropColumn('legacy_field');
        });

        foreach ($executor->toSql($modifySchema) as $statement) {
            $connection->executeStatement($statement);
        }

        // Verify column is gone and data is preserved
        $rows = $connection->fetchAllAssociative('SELECT * FROM articles');
        $this->assertCount(1, $rows);
        $this->assertSame('Test', $rows[0]['title']);
        $this->assertArrayNotHasKey('legacy_field', $rows[0]);
    }
}
