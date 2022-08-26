<?php
declare(strict_types=1);

namespace LessHydrator\Attribute;

use Attribute;

/**
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class DefaultValue
{
    public function __construct(public readonly mixed $default)
    {}
}
