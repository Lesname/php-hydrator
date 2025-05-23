<?php
declare(strict_types=1);

namespace LesHydratorTest;

use Random\Engine;
use Random\Randomizer;
use LesHydrator\FakerHydrator;
use PHPUnit\Framework\TestCase;
use LesValueObject\Enum\ContentType;
use LesValueObject\String\PhoneNumber;
use LesValueObject\Composite\Paginate;
use LesHydratorTest\Stub\IntValueObjectStub;
use LesValueObject\Number\Int\Date\Timestamp;
use LesHydratorTest\Stub\EnumValueObjectStub;
use LesValueObject\String\Format\EmailAddress;
use LesValueObject\Number\AbstractNumberValueObject;
use LesValueObject\Number\Int\AbstractIntValueObject;
use LesValueObject\Number\Float\AbstractFloatValueObject;
use LesValueObject\Composite\AbstractCompositeValueObject;
use LesValueObject\Collection\AbstractCollectionValueObject;

/**
 * @covers \LesHydrator\FakerHydrator
 */
class FakerHydratorTest extends TestCase
{
    public function testInt(): void
    {
        $fakerHydrator = new FakerHydrator();

        $faked = $fakerHydrator->hydrate(Timestamp::class, null);
        $filled = $fakerHydrator->hydrate(Timestamp::class, 3);

        self::assertInstanceOf(Timestamp::class, $faked);
        self::assertInstanceOf(Timestamp::class, $filled);
        self::assertSame(3, $filled->value);
    }

    public function testIntWithMultiple(): void
    {
        $fakerHydrator = new FakerHydrator();

        $class = new class (2) extends AbstractIntValueObject {
            public static function getMinimumValue(): int
            {
                return 0;
            }

            public static function getMaximumValue(): int
            {
                return 4;
            }

            public static function getMultipleOf(): int
            {
                return 2;
            }
        };

        $faked = $fakerHydrator->hydrate($class::class, null);
        $filled = $fakerHydrator->hydrate($class::class, 2);

        self::assertInstanceOf($class::class, $faked);
        self::assertInstanceOf($class::class, $filled);
        self::assertSame(2, $filled->value);
    }

    public function testFloat(): void
    {
        $vo = new class (3.3) extends AbstractFloatValueObject {
            public static function getMinimumValue(): int|float
            {
                return 2;
            }

            public static function getMaximumValue(): int|float
            {
                return 7;
            }

            public static function getMultipleOf(): int|float
            {
                return .3;
            }
        };

        $fakerHydrator = new FakerHydrator();

        $hydrated = $fakerHydrator->hydrate($vo::class, null);

        self::assertInstanceOf($vo::class, $hydrated);
    }

    public function testString(): void
    {
        $fakerHydrator = new FakerHydrator();

        $hydrated = $fakerHydrator->hydrate(PhoneNumber::class, null);

        self::assertInstanceOf(PhoneNumber::class, $hydrated);
    }

    public function testStringFormat(): void
    {
        $fakerHydrator = new FakerHydrator();

        $hydrated = $fakerHydrator->hydrate(EmailAddress::class, null);

        self::assertInstanceOf(EmailAddress::class, $hydrated);
    }

    public function testEnum(): void
    {
        $fakerHydrator = new FakerHydrator();

        $hydrated = $fakerHydrator->hydrate(ContentType::class, null);

        self::assertInstanceOf(ContentType::class, $hydrated);
    }

    public function testCollection(): void
    {
        $collection = new class ([Timestamp::now()]) extends AbstractCollectionValueObject {
            public static function getMinimumSize(): int
            {
                return 1;
            }

            public static function getMaximumSize(): int
            {
                return 9;
            }

            public static function getItemType(): string
            {
                return Timestamp::class;
            }
        };

        $fakerHydrator = new FakerHydrator();

        $hydrated = $fakerHydrator->hydrate($collection::class, null);

        self::assertInstanceOf($collection::class, $hydrated);
    }

    public function testComposite(): void
    {
        $fakerHydrator = new FakerHydrator();

        $hydrated = $fakerHydrator->hydrate(Paginate::class, null);

        self::assertInstanceOf(Paginate::class, $hydrated);
    }

    public function testCompositeUnion(): void
    {
        $composite = new class (null) extends AbstractCompositeValueObject {
            public function __construct(public readonly EnumValueObjectStub | IntValueObjectStub | null $value)
            {}
        };

        $randomizer = function (int $int) {
            return new Randomizer(
                new class ($int) implements Engine {
                    public function __construct(public readonly int $int)
                    {}

                    public function generate(): string
                    {
                        return pack('L', $this->int);
                    }
                },
            );
        };

        $nullHydrated = (new FakerHydrator($randomizer(3)))->hydrate($composite::class, null);
        self::assertSame(null, $nullHydrated->value);

        $fizHydrated = (new FakerHydrator($randomizer(1)))->hydrate($composite::class, null);
        self::assertSame(EnumValueObjectStub::Fiz, $fizHydrated->value);

        $intHydrated = (new FakerHydrator($randomizer(2)))->hydrate($composite::class, null);
        self::assertEquals(new IntValueObjectStub(3), $intHydrated->value);
    }
}
