<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace LessHydratorTest\Stub;

use LessHydrator\Attribute\TypeMatch;
use LessHydrator\Matcher\ExactMatcher;
use LessValueObject\Enum\EnumValueObject;

/**
 * @psalm-immutable
 */
#[TypeMatch(new ExactMatcher('fiz'))]
#[TypeMatch(new ExactMatcher('biz'))]
enum EnumValueObjectStub: string implements EnumValueObject
{
    case Fiz = 'fiz';
    case Biz = 'biz';

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
