# scafera/database

Database persistence for the Scafera framework. Wraps Doctrine ORM/DBAL internally — userland code never imports Doctrine types outside of entities and repositories.

> **Provides:** Database persistence for Scafera — `EntityStore` (read/write), `Transaction` (savepoint-based nesting; unflushed writes throw at end-of-request), Scafera-owned mapping attributes under `Scafera\Database\Mapping\Field\*`, and a Doctrine-free Schema API for migrations. Doctrine ORM/DBAL is wrapped internally.
>
> **Depends on:** A Scafera host project with a `DATABASE_URL` env var (set in `config/config.yaml` under `env:` or as OS env), entities in `src/Entity/`, repositories in `src/Repository/`, migrations in `support/migrations/`, and seeders in `support/seeds/`.
>
> **Extension points:**
> - Contract — `SeederInterface` (auto-tagged `scafera.seeder`, collected by `db:seed`)
> - Migrations — extend `Scafera\Database\Migration`; `up()` / `down()` use the Schema API (`Schema::create` / `modify` / `drop`, chainable column modifiers, no Doctrine imports)
> - Mapping attributes — 18 field types under `Scafera\Database\Mapping\Field\*` (`Id`, `Varchar`, `Text`, `Integer`, `Decimal`, `Money`, `DateTime`, `Json`, `Uuid`, … plus `Column` as an escape hatch for custom Doctrine types); `#[Table]` for table-name override; `Auditable` trait for `createdAt` / `updatedAt`
> - Controlled zones — `src/Entity/` forbids Doctrine imports (Scafera mapping only); `src/Repository/` allows Doctrine QueryBuilder / DQL / DBAL
> - Config — override via `doctrine:` in `config/config.yaml` (note: engine name leaks; future `database:` key planned)
>
> **Not responsible for:** Doctrine imports outside `src/Entity/` and `src/Repository/` (blocked by `DoctrineBoundaryPass`) · lifecycle callbacks on entities (detected and rejected) · table-name pluralization (singular snake_case per ADR-050) · engine abstraction in config (`doctrine:` key leaks engine name; mapping to `database:` planned) · test fixtures beyond seeders.

This is a **capability package**. It adds optional persistence to a Scafera project. It does not define folder structure or architectural rules — those belong to architecture packages.

## Installation

```bash
composer require scafera/database
```

The bundle is auto-discovered via Scafera's `symfony-bundle` type detection. No manual registration needed.

## Requirements

- PHP 8.4+
- `scafera/kernel` ^1.0
- A `DATABASE_URL` environment variable (set in `config/config.yaml` under `env:` or as an OS env var)

## Runtime API

### EntityStore

The primary persistence interface. Inject it in repositories.

```php
use Scafera\Database\EntityStore;

final class OrderRepository
{
    public function __construct(
        private readonly EntityStore $entityStore,
    ) {}

    public function find(int $id): ?Order
    {
        return $this->entityStore->find(Order::class, $id);
    }

    public function save(Order $order): void
    {
        $this->entityStore->persist($order);
    }

    public function remove(Order $order): void
    {
        $this->entityStore->remove($order);
    }
}
```

### Transaction

Wraps writes in an explicit transaction. All `persist()` and `remove()` calls must be committed through `Transaction::run()` — unflushed writes are detected and throw at the end of the request/command.

```php
use Scafera\Database\EntityStore;
use Scafera\Database\Transaction;

final class OrderService
{
    public function __construct(
        private readonly EntityStore $entityStore,
        private readonly Transaction $tx,
    ) {}

    public function placeOrder(array $data): Order
    {
        return $this->tx->run(function () use ($data): Order {
            $order = new Order();
            $order->setTotal($data['total']);
            $this->entityStore->persist($order);

            return $order;
        });
    }
}
```

Nested `Transaction::run()` calls are safe — inner calls use database savepoints. Only the outermost level flushes and commits. If an inner call throws and the outer catches it, the inner changes are rolled back via savepoint while the outer transaction can still commit.

### Entities

Entities use Scafera mapping attributes from `Scafera\Database\Mapping\Field`. These map to Doctrine types internally but keep your entities free of Doctrine imports.

```php
use Scafera\Database\Mapping\Field;

final class Order
{
    #[Field\Id]
    private ?int $id = null;

    #[Field\Decimal(precision: 10, scale: 2)]
    private string $total;

    public function __construct(
        #[Field\Varchar]
        private string $customerName,
    ) {}
}
```

Available field attributes: `Id`, `Varchar`, `VarcharShort`, `Text`, `Integer`, `IntegerBig`, `IntegerBigPositive`, `Boolean`, `Decimal`, `Money`, `Percentage`, `Date`, `DateTime`, `Time`, `UnixTimestamp`, `Json`, `Uuid`, `Column` (escape hatch for custom Doctrine types).

For types not covered by the built-in attributes, use `Column` as an escape hatch:

```php
#[Field\Column(type: 'string', options: ['length' => 15])]
private string $isoCode;
```

Use the `Auditable` trait for `createdAt`/`updatedAt` timestamp fields. You must initialize `$this->createdAt = new \DateTimeImmutable()` in your entity constructor — the `AuditableInitValidator` will warn if you forget.

#### Table Names

Table names are derived as singular snake_case from the class name: `Order` → `order`, `BlogPost` → `blog_post`. No pluralization is applied (see ADR-050). To override the default, use `#[Table]`:

```php
use Scafera\Database\Mapping\Field;
use Scafera\Database\Mapping\Table;

#[Table(name: 'categories')]
final class Category
{
    #[Field\Id]
    private ?int $id = null;
}
```

### Repositories

Repositories are the only zone where direct Doctrine usage is allowed. The `DoctrineBoundaryPass` enforces this at build time.

| Zone | Doctrine Imports | Notes |
|------|-----------------|-------|
| `src/Entity/` | Forbidden | Use `Scafera\Database\Mapping\Field` attributes. Lifecycle callbacks detected and rejected. |
| `src/Repository/` | Allowed | Controlled leakage — QueryBuilder, DQL, DBAL all permitted. |
| Everywhere else | Forbidden | Except `Doctrine\Common\Collections` (data structure, not behavioral). |

## Migrations

Migration files live in `support/migrations/` and use the Scafera Schema API — zero Doctrine imports.

### Schema API

```php
use Scafera\Database\Migration;
use Scafera\Database\Schema\Schema;
use Scafera\Database\Schema\Table;

final class Version20260403080718 extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('page', function (Table $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('slug', 255);
            $table->text('content');
            $table->boolean('published');
            $table->timestamp('createdAt');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('page');
    }
}
```

### Column Types (v1)

| Method | Doctrine Type | Arguments |
|--------|--------------|-----------|
| `id()` | `integer` (auto-increment PK) | — |
| `string($name, $length)` | `string` | length (default 255) |
| `text($name)` | `text` | — |
| `integer($name)` | `integer` | — |
| `bigInteger($name)` | `bigint` | — |
| `smallInteger($name)` | `smallint` | — |
| `boolean($name)` | `boolean` | — |
| `timestamp($name)` | `datetime_immutable` | — |
| `date($name)` | `date_immutable` | — |
| `decimal($name, $precision, $scale)` | `decimal` | precision (default 8), scale (default 2) |
| `json($name)` | `json` | — |

### Column Modifiers

Column methods return a `ColumnBuilder`. Chain modifiers on it:

```php
$table->string('bio')->nullable();
$table->boolean('active')->default(true);
$table->string('notes')->nullable()->default('');
```

### Schema Operations

```php
// Create a table
$schema->create('users', function (Table $table) { ... });

// Drop a table (destructive)
$schema->drop('users');

// Modify an existing table
$schema->modify('users', function (Table $table) {
    $table->string('email', 255);       // add column
    $table->dropColumn('legacy_field');  // drop column (destructive)
});
```

### Planned Schema Enhancements

The following operations are planned as future additions to the Schema API:

- Indexes (regular, unique, composite)
- Foreign key constraints

### Destructive Detection

Operations are classified as safe or destructive by type:

| Operation | Destructive |
|-----------|-------------|
| `CreateTable` | No |
| `AddColumn` | No |
| `DropTable` | Yes |
| `DropColumn` | Yes |

`db:migrate` checks pending migrations before execution:
- **Development** (`APP_ENV=dev`): warns about destructive operations, proceeds
- **Production** (`APP_ENV=prod`): blocks execution unless `--force` is passed

## CLI Commands

All commands are available via `vendor/bin/scafera`:

```bash
# Generate a migration from entity changes
vendor/bin/scafera db:migrate:diff

# Create a blank migration for manual editing
vendor/bin/scafera db:migrate:create

# Generate a migration to drop a specific table
vendor/bin/scafera db:migrate:drop orders

# Run pending migrations
vendor/bin/scafera db:migrate

# Show migration status
vendor/bin/scafera db:migrate:status

# Rollback the last migration
vendor/bin/scafera db:migrate:rollback

# Drop all tables and re-run all migrations (requires --force)
vendor/bin/scafera db:reset --force

# Run seeders from support/seeds/
vendor/bin/scafera db:seed

# List all database tables with column/row counts
vendor/bin/scafera db:schema:list

# Show column definitions for a table
vendor/bin/scafera db:schema:show orders

# Show mismatches between entities and database
vendor/bin/scafera db:schema:diff
```

### Seeding

Seeders live in `support/seeds/` and are auto-discovered via the `scafera.seeder` tag:

```php
namespace App\Seed;

use Scafera\Database\EntityStore;
use Scafera\Database\SeederInterface;
use Scafera\Database\Transaction;

final class PageSeed implements SeederInterface
{
    public function __construct(
        private readonly EntityStore $entityStore,
        private readonly Transaction $transaction,
    ) {}

    public function run(): void
    {
        $this->transaction->run(function (): void {
            $page = new \App\Entity\Page();
            $page->setTitle('Welcome');
            $page->setSlug('welcome');
            $page->setContent('Hello world.');
            $page->setPublished(true);
            $this->entityStore->persist($page);
        });
    }
}
```

## Configuration

The bundle configures Doctrine defaults automatically:

- **DBAL**: reads `DATABASE_URL` from env
- **ORM**: maps `App\Entity` from `src/Entity/` with attribute mapping
- **Migrations**: stores migration files in `support/migrations/` under the `App\Migrations` namespace

To override Doctrine config, add a `doctrine:` section to `config/config.yaml`. Note: this leaks the engine name — a `database:` config key mapping is planned (see Roadmap).

## License

MIT