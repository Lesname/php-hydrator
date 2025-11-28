<?php

declare(strict_types=1);

namespace LesHydratorTest\Matcher;

use LesHydrator\Matcher\ExactMatcher;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LesHydrator\Matcher\ExactMatcher
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
