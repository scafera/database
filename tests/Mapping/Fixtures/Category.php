<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Mapping\Fixtures;

use Scafera\Database\Mapping\Field;
use Scafera\Database\Mapping\Table;

#[Table(name: 'categories')]
final class Category
{
    #[Field\Id]
    private ?int $id = null;

    public function __construct(
        #[Field\Varchar]
        private string $name,
    ) {
    }
}
