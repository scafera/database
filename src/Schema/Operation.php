<?php

declare(strict_types=1);

namespace Scafera\Database\Schema;

interface Operation
{
    public function isDestructive(): bool;
}
