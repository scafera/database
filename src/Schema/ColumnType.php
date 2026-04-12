<?php

declare(strict_types=1);

namespace Scafera\Database\Schema;

enum ColumnType: string
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case BigInteger = 'bigint';
    case SmallInteger = 'smallint';
    case Boolean = 'boolean';
    case Timestamp = 'datetime_immutable';
    case Date = 'date_immutable';
    case Decimal = 'decimal';
    case Json = 'json';
}
