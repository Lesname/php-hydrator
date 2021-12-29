<?php
declare(strict_types=1);

namespace LessHydratorTest\Exception;

use LessHydrator\Exception\ConstructorRequired;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LessHydrator\Exception\ConstructorRequired
 */
final class ConstructorRequiredTest extends TestCase
{
    public function testConstructor(): void
    {
        $e = new ConstructorRequired('fiz');

        self::assertSame('fiz', $e->className);
    }
}
