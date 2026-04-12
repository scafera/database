<?php

declare(strict_types=1);

namespace Scafera\Database\Mapping;

use Scafera\Database\Mapping\Field\DateTime;

trait Auditable
{
    #[DateTime]
    private \DateTimeImmutable $createdAt;

    #[DateTime]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getCreatedAt(): \DateTimeImmutable
    {
        if (!isset($this->createdAt)) {
            throw new \LogicException(sprintf(
                '%s uses Auditable but did not initialize $this->createdAt. '
                . 'Add "$this->createdAt = new \\DateTimeImmutable();" to the constructor.',
                static::class,
            ));
        }

        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
