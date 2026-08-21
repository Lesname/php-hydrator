<?php

declare(strict_types=1);

namespace LesHydratorTest\Stub;

use LesValueObject\Number\Int\AbstractIntValueObject;

/**
 * @psalm-immutable
 */
final class IntValueObjectStub extends AbstractIntValueObject
{
    public static function getMinimumValue(): int
    {
        return 1;
    }

    public static function getMaximumValue(): int
    {
        return 3;
    }
}
