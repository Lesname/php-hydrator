<?php

declare(strict_types=1);

namespace LesHydrator\Matcher;

/**
 * @psalm-immutable
 *
 * @deprecated moved to Discriminator Composite
 */
interface Matcher
{
    public function matches(mixed $value, mixed $parentValue): bool;
}
