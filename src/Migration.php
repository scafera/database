<?php

declare(strict_types=1);

namespace Scafera\Database;

use Scafera\Database\Schema\Schema;

abstract class Migration
{
    abstract public function up(Schema $schema): void;

    abstract public function down(Schema $schema): void;
}
