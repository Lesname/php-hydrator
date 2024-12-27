<?php
declare(strict_types=1);

namespace LessHydrator\Matcher;

/**
 * @psalm-immutable
 */
final class ExactMatcher implements Matcher
{
    public function __construct(public readonly mixed $value)
    {}

    public function matches(mixed $value, mixed $parentValue): bool
    {
        return $value === $this->value;
    }
}
