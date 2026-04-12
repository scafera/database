<?php

declare(strict_types=1);

namespace Scafera\Database\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema as DoctrineSchema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;
use Scafera\Database\Migration as ScaferaMigration;
use Scafera\Database\Schema\Schema;

/** @internal */
final class MigrationAdapter extends AbstractMigration
{
    private ScaferaMigration $scaferaMigration;
    private SchemaExecutor $executor;

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        parent::__construct($connection, $logger);
        $this->executor = new SchemaExecutor($connection);
    }

    public function setScaferaMigration(ScaferaMigration $migration): void
    {
        $this->scaferaMigration = $migration;
    }

    public function up(DoctrineSchema $doctrineSchema): void
    {
        $schema = new Schema();
        $this->scaferaMigration->up($schema);

        foreach ($this->executor->toSql($schema) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(DoctrineSchema $doctrineSchema): void
    {
        $schema = new Schema();
        $this->scaferaMigration->down($schema);

        foreach ($this->executor->toSql($schema) as $sql) {
            $this->addSql($sql);
        }
    }
}
