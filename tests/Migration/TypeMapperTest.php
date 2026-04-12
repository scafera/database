<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Migration;

use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Scafera\Database\Migration\TypeMapper;
use Scafera\Database\Migration\UnsupportedOperationException;
use Scafera\Database\Schema\ColumnType;

class TypeMapperTest extends TestCase
{
    private TypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TypeMapper();
    }

    #[DataProvider('doctrineToScaferaProvider')]
    public function testFromDoctrine(string $doctrineTypeName, ColumnType $expected): void
    {
        $type = Type::getType($doctrineTypeName);
        $this->assertSame($expected, $this->mapper->fromDoctrine($type));
    }

    public static function doctrineToScaferaProvider(): iterable
    {
        yield 'string' => ['string', ColumnType::String];
        yield 'text' => ['text', ColumnType::Text];
        yield 'integer' => ['integer', ColumnType::Integer];
        yield 'bigint' => ['bigint', ColumnType::BigInteger];
        yield 'smallint' => ['smallint', ColumnType::SmallInteger];
        yield 'boolean' => ['boolean', ColumnType::Boolean];
        yield 'datetime_immutable' => ['datetime_immutable', ColumnType::Timestamp];
        yield 'datetime' => ['datetime', ColumnType::Timestamp];
        yield 'date_immutable' => ['date_immutable', ColumnType::Date];
        yield 'date' => ['date', ColumnType::Date];
        yield 'decimal' => ['decimal', ColumnType::Decimal];
        yield 'json' => ['json', ColumnType::Json];
    }

    public function testFromDoctrineThrowsForUnsupportedType(): void
    {
        $type = Type::getType('binary');

        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessage('binary');

        $this->mapper->fromDoctrine($type);
    }

    #[DataProvider('methodNameProvider')]
    public function testToMethodName(ColumnType $type, string $expected): void
    {
        $this->assertSame($expected, $this->mapper->toMethodName($type));
    }

    public static function methodNameProvider(): iterable
    {
        yield [ColumnType::String, 'string'];
        yield [ColumnType::Text, 'text'];
        yield [ColumnType::Integer, 'integer'];
        yield [ColumnType::BigInteger, 'bigInteger'];
        yield [ColumnType::SmallInteger, 'smallInteger'];
        yield [ColumnType::Boolean, 'boolean'];
        yield [ColumnType::Timestamp, 'timestamp'];
        yield [ColumnType::Date, 'date'];
        yield [ColumnType::Decimal, 'decimal'];
        yield [ColumnType::Json, 'json'];
    }

    public function testToDoctrineTypeName(): void
    {
        $this->assertSame('string', $this->mapper->toDoctrineTypeName(ColumnType::String));
        $this->assertSame('datetime_immutable', $this->mapper->toDoctrineTypeName(ColumnType::Timestamp));
        $this->assertSame('date_immutable', $this->mapper->toDoctrineTypeName(ColumnType::Date));
    }
}
