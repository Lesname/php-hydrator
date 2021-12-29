<?php
declare(strict_types=1);

namespace LessHydratorTest\Exception;

use LessHydrator\Exception\MissingValue;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LessHydrator\Exception\MissingValue
 */
final class MissingValueTest extends TestCase
{
    public function testConstructor(): void
    {
        $e = new MissingValue('foo');

        self::assertSame('foo', $e->name);
    }
}
