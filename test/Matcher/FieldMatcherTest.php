<?php
declare(strict_types=1);

namespace LesHydratorTest\Matcher;

use LesHydrator\Matcher\FieldMatcher;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LesHydrator\Matcher\FieldMatcher
 */
class FieldMatcherTest extends TestCase
{
    public function testMatch(): void
    {
        $data = ['foo' => 'bar'];

        $matcher = new FieldMatcher('foo', 'bar');

        self::assertTrue($matcher->matches($data, $data));
    }

    public function testNotMatch(): void
    {
        $data = ['foo' => 'biz'];

        $matcher = new FieldMatcher('foo', 'bar');

        self::assertFalse($matcher->matches($data, $data));
    }
}
