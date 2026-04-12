<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Mapping\Fixtures;

use Scafera\Database\Mapping\Auditable;
use Scafera\Database\Mapping\Field;

final class AuditableEntity
{
    use Auditable;

    #[Field\Id]
    private ?int $id = null;

    public function __construct(
        #[Field\Varchar]
        private string $name,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }
}
