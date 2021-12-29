<?php
declare(strict_types=1);

namespace LessHydratorTest;

use LessValueObject\Enum\AbstractEnumValueObject;

final class EnumValueObjectStub extends AbstractEnumValueObject
{
    public static function cases(): array
    {
        return [
            'fiz',
            'biz',
        ];
    }
}
