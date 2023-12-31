<?php
declare(strict_types=1);

namespace LessHydrator\Matcher;

/**
 * @psalm-immutable
 */
interface Matcher
{
    public function matches(mixed $value, mixed $parentValue): bool;
}
