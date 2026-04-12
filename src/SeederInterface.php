<?php

declare(strict_types=1);

namespace Scafera\Database;

interface SeederInterface
{
    public function run(): void;
}
