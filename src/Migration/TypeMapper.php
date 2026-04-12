<?php

declare(strict_types=1);

namespace Scafera\Database\Migration;

use Doctrine\DBAL\Types\Type;
use Scafera\Database\Schema\ColumnType;

/** @internal */
final class TypeMapper
{
    private const DOCTRINE_TO_SCAFERA = [
        'string' => ColumnType::String,
        'text' => ColumnType::Text,
        'integer' => ColumnType::Integer,
        'bigint' => ColumnType::BigInteger,
        'smallint' => ColumnType::SmallInteger,
        'boolean' => ColumnType::Boolean,
        'datetime_immutable' => ColumnType::Timestamp,
        'datetime' => ColumnType::Timestamp,
        'date_immutable' => ColumnType::Date,
        'date' => ColumnType::Date,
        'decimal' => ColumnType::Decimal,
        'json' => ColumnType::Json,
    ];

    private const SCAFERA_METHOD_NAMES = [
        ColumnType::String->value => 'string',
        ColumnType::Text->value => 'text',
        ColumnType::Integer->value => 'integer',
        ColumnType::BigInteger->value => 'bigInteger',
        ColumnType::SmallInteger->value => 'smallInteger',
        ColumnType::Boolean->value => 'boolean',
        ColumnType::Timestamp->value => 'timestamp',
        ColumnType::Date->value => 'date',
        ColumnType::Decimal->value => 'decimal',
        ColumnType::Json->value => 'json',
    ];

    public function fromDoctrine(Type $type): ColumnType
    {
        $typeName = Type::lookupName($type);

        if (!isset(self::DOCTRINE_TO_SCAFERA[$typeName])) {
            throw new UnsupportedOperationException(
                sprintf(
                    'Doctrine type "%s" is not supported by the Scafera Schema API v1. '
                    . 'Use `db:migrate:create` to write a manual migration.',
                    $typeName,
                ),
            );
        }

        return self::DOCTRINE_TO_SCAFERA[$typeName];
    }

    public function toDoctrineTypeName(ColumnType $type): string
    {
        return $type->value;
    }

    public function toMethodName(ColumnType $type): string
    {
        return self::SCAFERA_METHOD_NAMES[$type->value];
    }
}
