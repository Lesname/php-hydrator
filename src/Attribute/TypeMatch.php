<?php
declare(strict_types=1);

namespace LesHydrator\Attribute;

use Attribute;
use LesHydrator\Matcher\Matcher;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class TypeMatch
{
    public function __construct(public readonly Matcher $matcher)
    {}
}
