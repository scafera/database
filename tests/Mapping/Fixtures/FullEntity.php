<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Mapping\Fixtures;

use Scafera\Database\Mapping\Field;

final class FullEntity
{
    #[Field\Id]
    private ?int $id = null;

    public function __construct(
        #[Field\Varchar]
        private string $title,

        #[Field\VarcharShort]
        private string $code,

        #[Field\Text]
        private string $body,

        #[Field\Integer]
        private int $count,

        #[Field\IntegerBig]
        private int $bigCount,

        #[Field\IntegerBigPositive]
        private int $views,

        #[Field\Decimal]
        private string $price,

        #[Field\Boolean]
        private bool $active,

        #[Field\DateTime]
        private \DateTimeImmutable $createdAt,

        #[Field\Date]
        private \DateTimeImmutable $birthDate,

        #[Field\Time]
        private string $startTime,

        #[Field\Json]
        private array $metadata,

        #[Field\UnixTimestamp]
        private int $lastLogin,

        #[Field\Money]
        private string $balance,

        #[Field\Percentage]
        private string $taxRate,
    ) {
    }
}
