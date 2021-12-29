<?php
declare(strict_types=1);

namespace LessHydrator;

use LessValueObject\ValueObject;

interface Hydrator
{
    /**
     * @param class-string<ValueObject> $className
     * @param array<mixed>|int|float|string $data
     */
    public function hydrate(string $className, array|int|float|string $data): ValueObject;
}
