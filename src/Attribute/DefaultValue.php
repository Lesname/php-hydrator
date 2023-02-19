<?php
declare(strict_types=1);

namespace LessHydrator\Attribute;

use Attribute;

/**
 * @psalm-immutable
 *
 * @deprecated will be dropped
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class DefaultValue
{
    /**
     * @param string|int|bool|null|array<mixed>|float $default
     */
    public function __construct(public readonly string|int|bool|null|array|float $default)
    {}
}
