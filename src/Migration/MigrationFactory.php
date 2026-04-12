<?php

declare(strict_types=1);

namespace Scafera\Database\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory as DoctrineMigrationFactory;
use Psr\Log\LoggerInterface;
use Scafera\Database\Migration as ScaferaMigration;

/** @internal */
final class MigrationFactory implements DoctrineMigrationFactory
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        if (is_subclass_of($migrationClassName, ScaferaMigration::class)) {
            $scaferaMigration = new $migrationClassName();
            $adapter = new MigrationAdapter($this->connection, $this->logger);
            $adapter->setScaferaMigration($scaferaMigration);

            return $adapter;
        }

        return new $migrationClassName($this->connection, $this->logger);
    }
}
