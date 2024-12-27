<?php
declare(strict_types=1);

namespace LessHydrator\Attribute;

use Attribute;
use LessHydrator\Matcher\Matcher;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class TypeMatch
{
    public function __construct(public readonly Matcher $matcher)
    {}
}
