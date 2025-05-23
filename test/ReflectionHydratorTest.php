<?php
declare(strict_types=1);

namespace LesHydratorTest;

use stdClass;
use Throwable;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use LesHydratorTest\Stub\FooStub;
use LesHydratorTest\Stub\BarStub;
use LesHydrator\ReflectionHydrator;
use LesValueObject\Number\Int\Positive;
use LesHydrator\Exception\ParameterFailure;
use LesValueObject\Number\Int\Paginate\Page;
use LesValueObject\String\Format\SearchTerm;
use LesHydratorTest\Stub\IntValueObjectStub;
use LesHydratorTest\Stub\EnumValueObjectStub;
use LesValueObject\Number\Int\Paginate\PerPage;
use LesValueObject\Collection\CollectionValueObject;
use LesValueObject\Number\Int\AbstractIntValueObject;
use LesValueObject\Number\Float\AbstractFloatValueObject;
use LesValueObject\Composite\AbstractCompositeValueObject;
use LesValueObject\Collection\AbstractCollectionValueObject;

/**
 * @covers \LesHydrator\ReflectionHydrator
 */
final class ReflectionHydratorTest extends TestCase
{
    public function testFloat(): void
    {
        $class = new class (2) extends AbstractFloatValueObject {
            public static function getMultipleOf(): float|int
            {
                return .01;
            }

            public static function getMinimumValue(): float|int
            {
                return 1;
            }

            public static function getMaximumValue(): float|int
            {
                return 4;
            }
        };

        $hydrator = new ReflectionHydrator();
        $result = $hydrator->hydrate($class::class, '3.12');

        self::assertSame(3.12, $result->value);
    }

    public function testInt(): void
    {
        $class = new class (2) extends AbstractIntValueObject {
            public static function getMinimumValue(): int
            {
                return 1;
            }

            public static function getMaximumValue(): int
            {
                return 4;
            }
        };

        $hydrator = new ReflectionHydrator();
        $result = $hydrator->hydrate($class::class, '3');

        self::assertSame(3, $result->value);
    }

    public function testEnum(): void
    {
        $hydrator = new ReflectionHydrator();
        $result = $hydrator->hydrate(EnumValueObjectStub::class, 'biz');

        self::assertSame('biz', $result->value);
    }

    public function testCollection(): void
    {
        $class = new class ([]) extends AbstractCollectionValueObject {
            public static function getMinimumSize(): int
            {
                return 0;
            }

            public static function getMaximumSize(): int
            {
                return 3;
            }

            public static function getItemType(): string
            {
                return EnumValueObjectStub::class;
            }
        };

        $hydrator = new ReflectionHydrator();
        $collection = $hydrator->hydrate($class::class, ['fiz', 'biz']);

        self::assertInstanceOf(CollectionValueObject::class, $collection);

        foreach ($collection as $i => $value) {
            match ($i) {
                0 => self::assertEquals(EnumValueObjectStub::Fiz, $value),
                1 => self::assertEquals(EnumValueObjectStub::Biz, $value),
                default => throw new RuntimeException(),
            };
        }
    }

    public function testCollectionUnion(): void
    {
        $class = new class ([]) extends AbstractCollectionValueObject {
            public static function getMinimumSize(): int
            {
                return 0;
            }

            public static function getMaximumSize(): int
            {
                return 3;
            }

            public static function getItemType(): array
            {
                return [
                    EnumValueObjectStub::class,
                    IntValueObjectStub::class,
                ];
            }
        };

        $hydrator = new ReflectionHydrator();
        $collection = $hydrator->hydrate($class::class, [1, 'biz']);

        self::assertInstanceOf(CollectionValueObject::class, $collection);

        foreach ($collection as $i => $value) {
            match ($i) {
                0 => self::assertEquals(new IntValueObjectStub(1), $value),
                1 => self::assertEquals(EnumValueObjectStub::Biz, $value),
                default => throw new RuntimeException(),
            };
        }
    }

    public function testComposite(): void
    {
        $perPage = new PerPage(13);
        $page = new Page(3);
        $term = new SearchTerm('fiz');

        $paginate = new class ($term, null, $perPage, $page, true) extends AbstractCompositeValueObject {
            public function __construct(
                public SearchTerm $term,
                public ?Positive $int,
                public PerPage $perPage,
                public Page $page,
                public bool $biz = false,
            ) {}
        };

        $hydrator = new ReflectionHydrator();
        $hydrated = $hydrator->hydrate(
            $paginate::class,
            [
                'term' => 123,
                'perPage' => $perPage,
                'page' => 3,
            ],
        );

        self::assertInstanceOf($paginate::class, $hydrated);

        self::assertEquals(new SearchTerm('123'), $hydrated->term);
        self::assertSame($perPage, $hydrated->perPage);
        self::assertSame(3, $hydrated->page->value);
        self::assertNull($hydrated->int);
        self::assertFalse($hydrated->biz);
    }

    public function testCompositeUnion(): void
    {
        $composite = new class (null) extends AbstractCompositeValueObject {
            public function __construct(public readonly EnumValueObjectStub | IntValueObjectStub | null $value)
            {}
        };

        $hydrator = new ReflectionHydrator();
        $enum = new $composite(EnumValueObjectStub::Fiz);
        $int = new $composite(new IntValueObjectStub(2));

        self::assertEquals(
            $hydrator->hydrate($composite::class, ['value' => 'fiz'])->value,
            $enum->value,
        );
        self::assertEquals(
            $hydrator->hydrate($composite::class, ['value' => 2])->value,
            $int->value,
        );
        self::assertEquals(
            $hydrator->hydrate($composite::class, ['value' => null])->value,
            $composite->value,
        );
    }

    public function testCompositeIntersect(): void
    {
        $this->expectException(Throwable::class);

        $fiz = new class implements FooStub, BarStub {
        };

        $composite = new class ($fiz) extends AbstractCompositeValueObject {
            public function __construct(public readonly FooStub & BarStub $value)
            {}
        };

        $hydrator = new ReflectionHydrator();
        $hydrated = $hydrator->hydrate($composite::class, ['value' => 1]);
        $hydrated->value;
    }

    public function testCompositeUnionNoTypeMatch(): void
    {
        $this->expectException(ParameterFailure::class);

        $composite = new class (null) extends AbstractCompositeValueObject {
            public function __construct(public readonly EnumValueObjectStub | IntValueObjectStub | null $value)
            {}
        };

        $hydrator = new ReflectionHydrator();

        $hydrated = $hydrator->hydrate($composite::class, ['value' => 'bar']);
        $hydrated->value;
    }

    public function testNonValueObject(): void
    {
        $this->expectException(Throwable::class);

        $hydrator = new ReflectionHydrator();
        $hydrator->hydrate(stdClass::class, []);
    }

    public function testMissing(): void
    {
        $this->expectException(ParameterFailure::class);

        $paginate = new class (1) extends AbstractCompositeValueObject {
            public function __construct(public int $foo)
            {}
        };

        $hydrator = new ReflectionHydrator();
        $hydrated = $hydrator->hydrate($paginate::class, []);
        $hydrated->value;
    }

    public function testCompositeDefaultValue(): void
    {
        $class = new class (true) extends AbstractCompositeValueObject {
            public function __construct(public readonly ?bool $foo = false)
            {}
        };

        $hydrator = new ReflectionHydrator();
        $result = $hydrator->hydrate($class::class, []);

        self::assertInstanceOf($class::class, $result);
        self::assertFalse($result->foo);
    }

    public function testBoolCast(): void
    {
        $class = new class (true) extends AbstractCompositeValueObject {
            public function __construct(public readonly bool $fiz)
            {}
        };

        $hydrator = new ReflectionHydrator();

        self::assertTrue($hydrator->hydrate($class::class, ['fiz' => 1])->fiz);
        self::assertFalse($hydrator->hydrate($class::class, ['fiz' => 0])->fiz);

        self::assertTrue($hydrator->hydrate($class::class, ['fiz' => '1'])->fiz);
        self::assertFalse($hydrator->hydrate($class::class, ['fiz' => '0'])->fiz);

        self::assertTrue($hydrator->hydrate($class::class, ['fiz' => 'true'])->fiz);
        self::assertFalse($hydrator->hydrate($class::class, ['fiz' => 'false'])->fiz);
    }
}
