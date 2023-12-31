<?php
declare(strict_types=1);

namespace LessHydrator;

use LessHydrator\Exception\NoMatch;
use LessValueObject\String\StringValueObject;
use LessValueObject\Enum\EnumValueObject;
use LessValueObject\Number\NumberValueObject;
use LessValueObject\ValueObject;
use ReflectionException;

final class ReflectionHydrator extends AbstractHydrator
{
    /**
     * @param class-string<T>|array<class-string<T>> $itemType
     *
     * @return T
     *
     * @template T of ValueObject
     *
     * @throws NoMatch
     * @throws ReflectionException
     */
    protected function hydrateCollectionItem(array | string $itemType, mixed $itemValue, mixed $data): ValueObject
    {
        if (is_array($itemType)) {
            $itemType = $this->matchType($itemType, $itemValue, $data);
        }

        return $this->hydrate($itemType, $itemValue);
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of NumberValueObject
     */
    protected function hydrateNumber(string $className, mixed $data): NumberValueObject
    {
        $multipleOf = $className::getMultipleOf();

        if (is_float($multipleOf)) {
            if (is_string($data) && preg_match('/^-?(\d+|\d*\.\d+)$/', $data)) {
                $data = (float)$data;
            }

            assert(is_float($data));
        } else {
            if (is_string($data) && preg_match('/^-?\d+$/', $data)) {
                $data = (int)$data;
            }

            assert(is_int($data));
        }

        return new $className($data);
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of StringValueObject
     */
    protected function hydrateString(string $className, mixed $data): StringValueObject
    {
        if (is_int($data) || is_float($data)) {
            $data = (string)$data;
        }

        assert(is_string($data));

        return new $className($data);
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of EnumValueObject
     */
    protected function hydrateEnum(string $className, mixed $data): EnumValueObject
    {
        assert(is_string($data));

        return $className::from($data);
    }
}
