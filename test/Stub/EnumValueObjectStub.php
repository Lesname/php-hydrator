<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace LessHydratorTest\Stub;

use LessValueObject\Enum\EnumValueObject;

/**
 * @psalm-immutable
 */
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
