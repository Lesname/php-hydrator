<?php
declare(strict_types=1);

namespace LessHydratorTest\Exception;

use LessHydrator\Exception\NonValueObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LessHydrator\Exception\NonValueObject
 */
final class NonValueObjectTest extends TestCase
{
    public function testConstructor(): void
    {
        $e = new NonValueObject('biz');

        self::assertSame('biz', $e->classname);
    }
}
