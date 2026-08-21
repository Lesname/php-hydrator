<?php

declare(strict_types=1);

namespace LesHydrator;

use Override;
use RuntimeException;
use LesValueObject\String\StringValueObject;
use LesValueObject\Enum\EnumValueObject;
use LesValueObject\Number\NumberValueObject;

final class ReflectionHydrator extends AbstractHydrator
{
    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of NumberValueObject
     *
     * @psalm-suppress MissingPureAnnotation
     *
     * @psalm-mutation-free
     */
    #[Override]
    protected function hydrateNumber(string $className, mixed $data): NumberValueObject
    {
        if (is_string($data) && preg_match('/^-?((?<float>\d*\.\d+)|(?<int>\d+))$/', $data, $matches)) {
            $data = isset($matches['int'])
                ? (int)$data
                : (float)$data;
        }

        return new $className($data);
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of StringValueObject
     *
     * @psalm-suppress MissingPureAnnotation
     *
     * @psalm-mutation-free
     */
    #[Override]
    protected function hydrateString(string $className, mixed $data): StringValueObject
    {
        if (is_int($data) || is_float($data)) {
            $data = (string)$data;
        }

        if (!is_string($data)) {
            throw new RuntimeException();
        }

        return new $className($data);
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of EnumValueObject
     *
     * @psalm-mutation-free
     */
    #[Override]
    protected function hydrateEnum(string $className, mixed $data): EnumValueObject
    {
        assert(is_string($data));

        return $className::from($data);
    }
}
