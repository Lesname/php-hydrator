<?php

declare(strict_types=1);

namespace LesHydrator\Matcher;

use Override;

/**
 * @psalm-immutable
 */
final class ExactMatcher implements Matcher
{
    public function __construct(public readonly mixed $value)
    {}

    #[Override]
    public function matches(mixed $value, mixed $parentValue): bool
    {
        return $value === $this->value;
    }
}
