<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Mapping\Fixtures;

use Scafera\Database\Mapping\Field;

final class EscapeHatchEntity
{
    #[Field\Id]
    private ?int $id = null;

    public function __construct(
        #[Field\Column(type: 'string', options: ['length' => 15])]
        private string $isoCode,
    ) {
    }
}
