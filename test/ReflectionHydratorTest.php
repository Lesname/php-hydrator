<?php
declare(strict_types=1);

namespace LessHydratorTest;

use LessHydrator\Attribute\DefaultValue;
use LessHydrator\Exception\MissingValue;
use LessHydrator\ReflectionHydrator;
use LessValueObject\Collection\AbstractCollectionValueObject;
use LessValueObject\Collection\CollectionValueObject;
use LessValueObject\Composite\AbstractCompositeValueObject;
use LessValueObject\Number\AbstractNumberValueObject;
use LessValueObject\Number\Int\AbstractIntValueObject;
use LessValueObject\Number\Int\Paginate\Page;
use LessValueObject\Number\Int\Paginate\PerPage;
use LessValueObject\Number\Int\Positive;
use LessValueObject\String\Format\SearchTerm;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * @covers \LessHydrator\ReflectionHydrator
 */
final class ReflectionHydratorTest extends TestCase
{
    public function testNumberVo(): void
    {
        $class = new class (2) extends AbstractNumberValueObject {
            public static function getPrecision(): int
            {
                return 2;
            }

            public static function getMinValue(): float|int
            {
                return 1;
            }

            public static function getMaxValue(): float|int
            {
                return 4;
            }
        };

        $hydrator = new ReflectionHydrator();
        $result = $hydrator->hydrate($class::class, '3.12');

        self::assertSame(3.12, $result->getValue());
    }

    public function testIntVo(): void
    {
        $class = new class (2) extends AbstractIntValueObject {
            public static function getMinValue(): int
            {
                return 1;
            }

            public static function getMaxValue(): int
            {
                return 4;
            }
        };

        $hydrator = new ReflectionHydrator();
        $result = $hydrator->hydrate($class::class, '3');

        self::assertSame(3, $result->getValue());
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
            public static function getMinlength(): int
            {
                return 0;
            }

            public static function getMaxLength(): int
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
                #[DefaultValue(false)]
                public bool $biz,
            ) {}
        };

        $hydrator = new ReflectionHydrator();
        $hydrated = $hydrator->hydrate(
            $paginate::class,
            [
                'term' => 'foo',
                'perPage' => $perPage,
                'page' => 3,
            ],
        );

        self::assertInstanceOf($paginate::class, $hydrated);

        self::assertEquals(new SearchTerm('foo'), $hydrated->term);
        self::assertSame($perPage, $hydrated->perPage);
        self::assertSame(3, $hydrated->page->getValue());
        self::assertNull($hydrated->int);
        self::assertFalse($hydrated->biz);
    }

    public function testNonValueObject(): void
    {
        $this->expectException(Throwable::class);

        $hydrator = new ReflectionHydrator();
        $hydrator->hydrate(stdClass::class, []);
    }

    public function testCompositeEmpyConstructor(): void
    {
        $this->expectException(Throwable::class);

        $paginate = new class () extends AbstractCompositeValueObject {
            public function __construct()
            {}
        };

        $hydrator = new ReflectionHydrator();
        $hydrator->hydrate($paginate::class, []);
    }

    public function testMissing(): void
    {
        $this->expectException(MissingValue::class);

        $paginate = new class (1) extends AbstractCompositeValueObject {
            public function __construct(public int $foo)
            {}
        };

        $hydrator = new ReflectionHydrator();
        $hydrator->hydrate($paginate::class, []);
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
}
