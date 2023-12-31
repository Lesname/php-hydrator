<?php
declare(strict_types=1);

namespace LessHydratorTest\Stub;

use LessHydrator\Attribute\TypeMatch;
use LessHydrator\Matcher\ExactMatcher;
use LessValueObject\Number\Int\AbstractIntValueObject;

/**
 * @psalm-immutable
 */
#[TypeMatch(new ExactMatcher(1))]
#[TypeMatch(new ExactMatcher(2))]
#[TypeMatch(new ExactMatcher(3))]
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
