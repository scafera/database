<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Scafera\Database\Mapping\Contract\FieldAttribute;
use Scafera\Database\Mapping\Field\Boolean;
use Scafera\Database\Mapping\Field\Column;
use Scafera\Database\Mapping\Field\Date;
use Scafera\Database\Mapping\Field\DateTime;
use Scafera\Database\Mapping\Field\Decimal;
use Scafera\Database\Mapping\Field\Id;
use Scafera\Database\Mapping\Field\Integer;
use Scafera\Database\Mapping\Field\IntegerBig;
use Scafera\Database\Mapping\Field\IntegerBigPositive;
use Scafera\Database\Mapping\Field\Json;
use Scafera\Database\Mapping\Field\Money;
use Scafera\Database\Mapping\Field\Percentage;
use Scafera\Database\Mapping\Field\Text;
use Scafera\Database\Mapping\Field\Time;
use Scafera\Database\Mapping\Field\UnixTimestamp;
use Scafera\Database\Mapping\Field\Uuid;
use Scafera\Database\Mapping\Field\Varchar;
use Scafera\Database\Mapping\Field\VarcharShort;

class AttributeTest extends TestCase
{
    public function testAllPresetsImplementFieldAttribute(): void
    {
        $presets = [
            new Id(),
            new Uuid(),
            new Varchar(),
            new VarcharShort(),
            new Text(),
            new Integer(),
            new IntegerBig(),
            new IntegerBigPositive(),
            new Decimal(),
            new Boolean(),
            new DateTime(),
            new Date(),
            new Time(),
            new Json(),
            new UnixTimestamp(),
            new Money(),
            new Percentage(),
        ];

        foreach ($presets as $preset) {
            $this->assertInstanceOf(FieldAttribute::class, $preset, $preset::class . ' must implement FieldAttribute');
        }
    }

    public function testColumnEscapeHatchImplementsFieldAttribute(): void
    {
        $col = new Column(type: 'string', options: ['length' => 15]);
        $this->assertInstanceOf(FieldAttribute::class, $col);
        $this->assertSame('string', $col->doctrineType());
        $this->assertSame(['length' => 15], $col->doctrineOptions());
    }

    public function testIdReturnsIntegerType(): void
    {
        $id = new Id();
        $this->assertSame('integer', $id->doctrineType());
        $this->assertSame([], $id->doctrineOptions());
    }

    public function testUuidReturnsGuidType(): void
    {
        $uuid = new Uuid();
        $this->assertSame('guid', $uuid->doctrineType());
    }

    public function testVarcharReturnsStringWith255(): void
    {
        $v = new Varchar();
        $this->assertSame('string', $v->doctrineType());
        $this->assertSame(['length' => 255], $v->doctrineOptions());
    }

    public function testVarcharShortReturnsStringWith50(): void
    {
        $v = new VarcharShort();
        $this->assertSame('string', $v->doctrineType());
        $this->assertSame(['length' => 50], $v->doctrineOptions());
    }

    public function testTextReturnsTextType(): void
    {
        $t = new Text();
        $this->assertSame('text', $t->doctrineType());
        $this->assertSame([], $t->doctrineOptions());
    }

    public function testIntegerReturnsIntegerType(): void
    {
        $i = new Integer();
        $this->assertSame('integer', $i->doctrineType());
    }

    public function testIntegerBigReturnsBigintType(): void
    {
        $i = new IntegerBig();
        $this->assertSame('bigint', $i->doctrineType());
    }

    public function testIntegerBigPositiveReturnsBigintUnsigned(): void
    {
        $i = new IntegerBigPositive();
        $this->assertSame('bigint', $i->doctrineType());
        $this->assertSame(['unsigned' => true], $i->doctrineOptions());
    }

    public function testDecimalReturnsDecimalWithPrecision(): void
    {
        $d = new Decimal();
        $this->assertSame('decimal', $d->doctrineType());
        $this->assertSame(['precision' => 10, 'scale' => 2], $d->doctrineOptions());
    }

    public function testBooleanReturnsBooleanType(): void
    {
        $b = new Boolean();
        $this->assertSame('boolean', $b->doctrineType());
    }

    public function testDateTimeReturnsDatetimeImmutable(): void
    {
        $dt = new DateTime();
        $this->assertSame('datetime_immutable', $dt->doctrineType());
    }

    public function testDateReturnsDateImmutable(): void
    {
        $d = new Date();
        $this->assertSame('date_immutable', $d->doctrineType());
    }

    public function testTimeReturnsTimeType(): void
    {
        $t = new Time();
        $this->assertSame('time', $t->doctrineType());
    }

    public function testJsonReturnsJsonType(): void
    {
        $j = new Json();
        $this->assertSame('json', $j->doctrineType());
    }

    public function testUnixTimestampReturnsIntegerType(): void
    {
        $u = new UnixTimestamp();
        $this->assertSame('integer', $u->doctrineType());
    }

    public function testMoneyReturnsDecimalWithScale4(): void
    {
        $m = new Money();
        $this->assertSame('decimal', $m->doctrineType());
        $this->assertSame(['precision' => 10, 'scale' => 4], $m->doctrineOptions());
    }

    public function testPercentageReturnsDecimalWithPrecision8(): void
    {
        $p = new Percentage();
        $this->assertSame('decimal', $p->doctrineType());
        $this->assertSame(['precision' => 8, 'scale' => 4], $p->doctrineOptions());
    }

    public function testAllPresetsArePropertyTargetAttributes(): void
    {
        $classes = [
            Id::class, Uuid::class, Varchar::class, VarcharShort::class, Text::class,
            Integer::class, IntegerBig::class, IntegerBigPositive::class, Decimal::class,
            Boolean::class, DateTime::class, Date::class, Time::class, Json::class,
            UnixTimestamp::class, Money::class, Percentage::class, Column::class,
        ];

        foreach ($classes as $class) {
            $ref = new \ReflectionClass($class);
            $attrs = $ref->getAttributes(\Attribute::class);
            $this->assertCount(1, $attrs, "$class must have #[Attribute]");

            $attr = $attrs[0]->newInstance();
            $this->assertTrue(
                ($attr->flags & \Attribute::TARGET_PROPERTY) !== 0,
                "$class must target properties",
            );
        }
    }
}
