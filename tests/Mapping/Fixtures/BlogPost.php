<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Mapping\Fixtures;

use Scafera\Database\Mapping\Field;

final class BlogPost
{
    #[Field\Id]
    private ?int $id = null;

    public function __construct(
        #[Field\Varchar]
        private string $title,
    ) {
    }
}
