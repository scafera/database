<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Fixtures;

use Scafera\Database\Mapping\Field;

final class TestItem
{
    #[Field\Id]
    private ?int $id = null;

    public function __construct(
        #[Field\Varchar]
        private string $name,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
