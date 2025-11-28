<?php

declare(strict_types=1);

namespace LesHydratorTest\Exception;

use LesHydrator\Exception\MissingValue;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LesHydrator\Exception\MissingValue
 */
final class MissingValueTest extends TestCase
{
    public function testConstructor(): void
    {
        $e = new MissingValue('foo');

        self::assertSame('foo', $e->name);
    }
}
