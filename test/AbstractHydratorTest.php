<?php

declare(strict_types=1);

namespace LesHydratorTest;

use ReflectionClass;
use RuntimeException;
use LesValueObject\ValueObject;
use LesHydrator\AbstractHydrator;
use PHPUnit\Framework\TestCase;
use LesValueObject\Enum\CountryCode;
use LesValueObject\Composite\Paginate;
use LesValueObject\Enum\EnumValueObject;
use LesValueObject\String\StringValueObject;
use LesValueObject\Number\NumberValueObject;
use LesValueObject\Number\Int\Date\Timestamp;
use LesValueObject\Number\Int\Date\MilliTimestamp;
use LesValueObject\Composite\AbstractDiscriminatorCompositeValueObject;
use LesValueObject\Composite\Signature\AbstractSignatureCompositeValueObject;

use function PHPUnit\Framework\assertSame;

/**
 * @covers \LesHydrator\AbstractHydrator
 */
class AbstractHydratorTest extends TestCase
{
    public function testHydrateProxy(): void
    {
        $hydrator = new class () extends AbstractHydrator {
            protected function hydrateCollectionItem(array|string $itemType, mixed $itemValue, mixed $data): ValueObject
            {
                throw new RuntimeException();
            }

            protected function hydrateEnum(string $className, mixed $data): EnumValueObject
            {
                throw new RuntimeException();
            }

            protected function hydrateNumber(string $className, mixed $data): NumberValueObject
            {
                throw new RuntimeException();
            }

            protected function hydrateString(string $className, mixed $data): StringValueObject
            {
                throw new RuntimeException();
            }
        };

        $hydrated = $hydrator->hydrate(Paginate::class, ['perPage' => 100, 'page' => 1]);

        $refPerPage = new ReflectionClass($hydrated::class);
        self::assertTrue($refPerPage->isUninitializedLazyObject($hydrated));
    }

    public function testHydrateSignatureComposite(): void
    {
        $stub = new class ([]) extends AbstractSignatureCompositeValueObject {
            public static function getSignature(): string
            {
                return Timestamp::class;
            }
        };

        $hydrator = new class () extends AbstractHydrator {
            protected function hydrateCollectionItem(array|string $itemType, mixed $itemValue, mixed $data): ValueObject
            {
                throw new RuntimeException();
            }

            protected function hydrateEnum(string $className, mixed $data): EnumValueObject
            {
                throw new RuntimeException();
            }

            protected function hydrateNumber(string $className, mixed $data): NumberValueObject
            {
                return new $className($data);
            }

            protected function hydrateString(string $className, mixed $data): StringValueObject
            {
                throw new RuntimeException();
            }
        };

        $result = $hydrator->hydrate($stub::class, ['today' => 1]);

        self::assertInstanceOf($stub::class, $result);
        self::assertInstanceOf(Timestamp::class, $result->get('today'));
    }

    public function testHydrateDiscriminatorComposite(): void
    {
        $country = CountryCode::Netherlands;
        $time = new MilliTimestamp(1);

        $stub = new class ($country, $time) extends AbstractDiscriminatorCompositeValueObject {
            public function __construct(
                public readonly CountryCode $country,
                public readonly MilliTimestamp|Timestamp $time,
            ) {
            }

            public static function getDiscriminatingField(): string
            {
                return 'country';
            }

            public static function getDiscriminatingProperty(): string
            {
                return 'time';
            }

            public static function getDiscriminatingMapping(): array
            {
                return [
                    CountryCode::Netherlands->value => MilliTimestamp::class,
                ];
            }
        };

        $hydrator = new class () extends AbstractHydrator {
            protected function hydrateCollectionItem(array|string $itemType, mixed $itemValue, mixed $data): ValueObject
            {
                throw new RuntimeException();
            }

            protected function hydrateEnum(string $className, mixed $data): EnumValueObject
            {
                assertSame($className, CountryCode::class);

                return $className::from($data);
            }

            protected function hydrateNumber(string $className, mixed $data): NumberValueObject
            {
                assertSame($className, MilliTimestamp::class);

                return new $className($data);
            }

            protected function hydrateString(string $className, mixed $data): StringValueObject
            {
                throw new RuntimeException();
            }
        };

        $result = $hydrator->hydrate(
            $stub::class,
            [
                'country' => $country->value,
                'time' => $time->value,
            ],
        );

        self::assertInstanceOf($stub::class, $result);
        self::assertInstanceOf(MilliTimestamp::class, $result->time);
    }
}
