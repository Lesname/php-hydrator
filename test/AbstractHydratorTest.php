<?php
declare(strict_types=1);

namespace LesHydratorTest;

use ReflectionClass;
use RuntimeException;
use LesValueObject\ValueObject;
use LesHydrator\AbstractHydrator;
use PHPUnit\Framework\TestCase;
use LesValueObject\Composite\Paginate;
use LesValueObject\Enum\EnumValueObject;
use LesValueObject\String\StringValueObject;
use LesValueObject\Number\NumberValueObject;

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
}
