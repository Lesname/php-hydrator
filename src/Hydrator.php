<?php

declare(strict_types=1);

namespace LesHydrator;

use LesValueObject\ValueObject;

/**
 * @psalm-mutable
 */
interface Hydrator
{
    /**
     * @param class-string<T> $className
     *
     * @template T of \LesValueObject\ValueObject
     *
     * @return T
     *
     * @psalm-impure
     */
    public function hydrate(string $className, mixed $data): ValueObject;
}
