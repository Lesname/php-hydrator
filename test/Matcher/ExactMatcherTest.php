<?php
declare(strict_types=1);

namespace LessHydratorTest\Matcher;

use LessHydrator\Matcher\ExactMatcher;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LessHydrator\Matcher\ExactMatcher
 */
class ExactMatcherTest extends TestCase
{
    public function testMatches(): void
    {
        $matcher = new ExactMatcher('foo');

        self::assertTrue($matcher->matches('foo', 'foo'));
        self::assertFalse($matcher->matches('bar', 'bar'));
    }
}
