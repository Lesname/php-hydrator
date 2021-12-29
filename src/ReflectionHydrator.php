<?php
declare(strict_types=1);

namespace LessHydrator;

use LessHydrator\Exception\ConstructorRequired;
use LessHydrator\Exception\MissingValue;
use LessHydrator\Exception\NonValueObject;
use LessHydrator\Exception\RequireNamedType;
use LessValueObject\Collection\CollectionValueObject;
use LessValueObject\Enum\EnumValueObject;
use LessValueObject\Number\Int\IntValueObject;
use LessValueObject\Number\NumberValueObject;
use LessValueObject\ValueObject;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

final class ReflectionHydrator implements Hydrator
{
    /**
     * @param class-string<ValueObject> $className
     * @param array<mixed>|int|float|string $data
     *
     * @return ValueObject
     * @throws ReflectionException
     * @throws RequireNamedType
     * @throws ConstructorRequired
     * @throws MissingValue
     * @throws NonValueObject
     */
    public function hydrate(string $className, array|int|float|string $data): ValueObject
    {
        if (!is_subclass_of($className, ValueObject::class)) {
            throw new NonValueObject($className);
        }

        if (is_array($data)) {
            return $this->hydrateFromArray($className, $data);
        }

        return $this->hydrateFromScalar($className, $data);
    }

    /**
     * @param class-string<ValueObject> $className
     * @param array<mixed> $data
     *
     * @throws ReflectionException
     * @throws RequireNamedType
     * @throws ConstructorRequired
     * @throws MissingValue
     * @throws NonValueObject
     */
    private function hydrateFromArray(string $className, array $data): ValueObject
    {
        if (is_subclass_of($className, CollectionValueObject::class)) {
            $itemType = $className::getItemType();

            return new $className(
                array_map(
                    function ($item) use ($itemType) {
                        assert(
                            is_array($item) || is_int($item) || is_float($item) || is_string($item),
                            'Invalid data',
                        );

                        return $this->hydrate($itemType, $item);
                    },
                    $data,
                ),
            );
        }

        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if (!$constructor instanceof ReflectionMethod || count($constructor->getParameters()) === 0) {
            throw new ConstructorRequired($className);
        }

        return new $className(
            ...array_map(
                function (ReflectionParameter $item) use ($data): mixed {
                    if (!isset($data[$item->getName()])) {
                        if ($item->allowsNull()) {
                            return null;
                        }

                        throw new MissingValue($item->getName());
                    }

                    $type = $item->getType();

                    if (!$type instanceof ReflectionNamedType) {
                        throw new RequireNamedType();
                    }

                    $value = $data[$item->getName()];

                    if ($type->isBuiltin()) {
                        return $value;
                    }

                    $typeName = $type->getName();

                    if ($value instanceof $typeName) {
                        return $value;
                    }

                    assert(is_subclass_of($typeName, ValueObject::class), 'Require ValueObject as type');
                    assert(
                        is_array($value) || is_string($value) || is_int($value) || is_float($value),
                        'Invalid value for hydration',
                    );

                    return $this->hydrate($typeName, $value);
                },
                $constructor->getParameters(),
            ),
        );
    }

    /**
     * @param class-string<ValueObject> $className
     * @param int|float|string $data
     */
    private function hydrateFromScalar(string $className, int|float|string $data): ValueObject
    {
        if (is_subclass_of($className, NumberValueObject::class)) {
            if (is_subclass_of($className, IntValueObject::class)) {
                return new $className((int)$data);
            }

            return new $className((float)$data);
        }

        if (is_subclass_of($className, EnumValueObject::class) && is_string($data)) {
            return $className::from($data);
        }

        return new $className($data);
    }
}
