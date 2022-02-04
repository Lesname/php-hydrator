<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace LessHydratorTest;

use LessValueObject\Enum\EnumValueObject;

/**
 * @psalm-immutable
 */
enum EnumValueObjectStub: string implements EnumValueObject
{
    case Fiz = 'fiz';
    case Biz = 'biz';

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }
}
