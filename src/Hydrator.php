<?php
declare(strict_types=1);

namespace LessHydrator;

use LessValueObject\ValueObject;

interface Hydrator
{
    /**
     * @param class-string<T> $className
     * @param array<mixed>|int|float|string $data
     *
     * @template T of \LessValueObject\ValueObject
     *
     * @return T
     */
    public function hydrate(string $className, array|int|float|string $data): ValueObject;
}
