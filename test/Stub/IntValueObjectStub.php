<?php
declare(strict_types=1);

namespace LesHydratorTest\Stub;

use LesHydrator\Attribute\TypeMatch;
use LesHydrator\Matcher\ExactMatcher;
use LesValueObject\Number\Int\AbstractIntValueObject;

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
