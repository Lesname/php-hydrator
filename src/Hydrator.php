<?php
declare(strict_types=1);

namespace LessHydrator;

use LessValueObject\ValueObject;

interface Hydrator
{
    /**
     * @param class-string<T> $className
     *
     * @template T of \LessValueObject\ValueObject
     *
     * @return T
     */
    public function hydrate(string $className, mixed $data): ValueObject;
}
