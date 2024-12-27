<?php
declare(strict_types=1);

namespace LessHydrator;

use Throwable;
use ReflectionClass;
use RuntimeException;
use ReflectionMethod;
use ReflectionException;
use ReflectionParameter;
use ReflectionNamedType;
use ReflectionUnionType;
use LessValueObject\ValueObject;
use LessHydrator\Exception\NoMatch;
use LessHydrator\Attribute\TypeMatch;
use LessHydrator\Exception\MissingValue;
use LessValueObject\Enum\EnumValueObject;
use LessHydrator\Exception\InvalidDataType;
use LessHydrator\Exception\ParameterFailure;
use LessValueObject\Number\NumberValueObject;
use LessValueObject\String\StringValueObject;
use LessValueObject\Composite\CompositeValueObject;
use LessValueObject\Collection\CollectionValueObject;
use LessValueObject\Composite\DynamicCompositeValueObject;

abstract class AbstractHydrator implements Hydrator
{
    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of ValueObject
     */
    public function hydrate(string $className, mixed $data): ValueObject
    {
        return match (true) {
            is_subclass_of($className, CollectionValueObject::class) => $this->hydrateCollection($className, $data),
            is_subclass_of($className, CompositeValueObject::class) => $this->hydrateComposite($className, $data),
            is_subclass_of($className, EnumValueObject::class) => $this->hydrateEnum($className, $data),
            is_subclass_of($className, NumberValueObject::class) => $this->hydrateNumber($className, $data),
            is_subclass_of($className, StringValueObject::class) => $this->hydrateString($className, $data),
            default => throw new RuntimeException("{$className} unknown vo sub class"),
        };
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of CollectionValueObject
     */
    protected function hydrateCollection(string $className, mixed $data): CollectionValueObject
    {
        $itemType = $className::getItemType();
        assert(is_array($data));

        return new $className(
            array_map(
                fn (mixed $itemValue) => $this->hydrateCollectionItem($itemType, $itemValue, $data),
                $data,
            ),
        );
    }

    /**
     * @param class-string<T>|array<class-string<T>> $itemType
     *
     * @return T
     *
     * @template T of ValueObject
     */
    abstract protected function hydrateCollectionItem(array | string $itemType, mixed $itemValue, mixed $data): ValueObject;

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of CompositeValueObject
     *
     * @throws InvalidDataType
     * @throws ReflectionException
     */
    protected function hydrateComposite(string $className, mixed $data): CompositeValueObject
    {
        if (!is_array($data)) {
            throw new InvalidDataType();
        }

        if ($className === DynamicCompositeValueObject::class) {
            $parameters = [$data];
        } else {
            $reflection = new ReflectionClass($className);
            $constructor = $reflection->getConstructor();

            assert($constructor instanceof ReflectionMethod && count($constructor->getParameters()) > 0);

            $parameters = array_map(
                function (ReflectionParameter $parameter) use ($data): mixed {
                    try {
                        return $this->hydrateCompositeParameter($parameter, $data);
                    } catch (Throwable $e) {
                        throw new ParameterFailure($parameter->getName(), $e);
                    }
                },
                $constructor->getParameters(),
            );
        }

        return new $className(...$parameters);
    }

    /**
     * @param array<mixed> $data
     *
     * @throws ReflectionException
     * @throws InvalidDataType
     * @throws MissingValue
     * @throws NoMatch
     */
    protected function hydrateCompositeParameter(ReflectionParameter $parameter, array $data): mixed
    {
        if (!array_key_exists($parameter->getName(), $data)) {
            if ($parameter->isDefaultValueAvailable()) {
                $data[$parameter->getName()] = $parameter->getDefaultValue();
            }
        }

        if (!isset($data[$parameter->getName()])) {
            if ($parameter->allowsNull()) {
                return null;
            }

            throw new MissingValue($parameter->getName());
        }

        $type = $parameter->getType();

        if ($type === null) {
            throw new RuntimeException('Type expected');
        }

        $value = $data[$parameter->getName()];

        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin()) {
                if ($type->getName() === 'bool') {
                    return $this->toBoolean($value);
                }

                throw new RuntimeException("Builtin '{$type->getName()}' not supported");
            }

            $typeName = $type->getName();
        } elseif ($type instanceof ReflectionUnionType) {
            $typeName = $this->matchType(
                (function () use ($type) {
                    foreach ($type->getTypes() as $subType) {
                        assert($subType instanceof ReflectionNamedType);

                        $subTypeName = $subType->getName();

                        if (class_exists($subTypeName)) {
                            if (!is_subclass_of($subTypeName, ValueObject::class)) {
                                throw new RuntimeException("'{$subTypeName}' is not an value object");
                            }

                            yield $subTypeName;
                        }
                    }
                })(),
                $value,
                $data,
            );
        } else {
            throw new RuntimeException("Unhandled reflection type");
        }

        if ($value instanceof $typeName) {
            return $value;
        }

        if (!class_exists($typeName)) {
            throw new RuntimeException("'{$typeName}' is not a class");
        }

        if (!is_subclass_of($typeName, ValueObject::class)) {
            throw new RuntimeException("'{$typeName}' is not a value object");
        }

        return $this->hydrate($typeName, $value);
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of EnumValueObject
     */
    abstract protected function hydrateEnum(string $className, mixed $data): EnumValueObject;

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of NumberValueObject
     */
    abstract protected function hydrateNumber(string $className, mixed $data): NumberValueObject;

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of StringValueObject
     */
    abstract protected function hydrateString(string $className, mixed $data): StringValueObject;

    /**
     * @param iterable<class-string<T>> $union
     *
     * @return class-string<T>
     *
     * @template T of ValueObject
     *
     * @throws NoMatch
     * @throws ReflectionException
     */
    protected function matchType(iterable $union, mixed $value, mixed $parentValue): string
    {
        foreach ($union as $item) {
            $refClass = new ReflectionClass($item);

            if ($value instanceof $item) {
                return $item;
            }

            foreach ($refClass->getAttributes(TypeMatch::class) as $attribute) {
                if ($attribute->newInstance()->matcher->matches($value, $parentValue)) {
                    return $item;
                }
            }
        }

        throw new NoMatch();
    }

    protected function toBoolean(mixed $value): bool
    {
        if (in_array($value, [0, 1], true)) {
            return (bool)$value;
        }

        if (is_string($value)) {
            if (in_array($value, ['true', 'false'], true)) {
                return $value === 'true';
            }

            if (in_array($value, ['0', '1'], true)) {
                return $value === '1';
            }
        }

        assert(is_bool($value));

        return $value;
    }
}
