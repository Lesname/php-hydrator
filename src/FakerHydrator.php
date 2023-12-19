<?php
declare(strict_types=1);

namespace LessHydrator;

use ReflectionClass;
use RuntimeException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionNamedType;
use ReflectionException;
use Random\RandomException;
use LessValueObject\ValueObject;
use LessValueObject\Enum\EnumValueObject;
use LessValueObject\Attribute\DocExample;
use LessHydrator\Exception\InvalidDataType;
use LessValueObject\Number\NumberValueObject;
use LessValueObject\String\StringValueObject;
use LessValueObject\Composite\CompositeValueObject;
use LessValueObject\Collection\CollectionValueObject;
use LessValueObject\Composite\DynamicCompositeValueObject;
use LessValueObject\String\Format\StringFormatValueObject;

final class FakerHydrator implements Hydrator
{
    /**
     * @param class-string<T> $className
     *
     * @return ValueObject
     *
     * @template T of ValueObject
     *
     * @throws InvalidDataType
     * @throws RandomException
     * @throws ReflectionException
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
     * @param class-string<CollectionValueObject<T>> $className
     *
     * @return CollectionValueObject<T>
     *
     * @template T of ValueObject
     *
     * @throws ReflectionException
     * @throws RandomException
     * @throws InvalidDataType
     */
    private function hydrateCollection(string $className, mixed $data): CollectionValueObject
    {
        if (!is_array($data)) {
            if ($data === null) {
                $items = random_int($className::getMinimumSize(), $className::getMaximumSize());
                $data = array_fill(0, $items, null);
            } else {
                throw new InvalidDataType();
            }
        }

        $itemType = $className::getItemType();

        return new $className(
            array_map(
                fn ($item) => $this->hydrate($itemType, $item),
                $data,
            ),
        );
    }

    /**
     * @param class-string<CompositeValueObject> $className
     *
     * @throws InvalidDataType
     * @throws RandomException
     * @throws ReflectionException
     */
    private function hydrateComposite(string $className, mixed $data): CompositeValueObject
    {
        $data ??= [];

        if (!is_array($data)) {
            throw new InvalidDataType();
        }

        if ($className === DynamicCompositeValueObject::class) {
            return new DynamicCompositeValueObject($data);
        }

        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        assert($constructor instanceof ReflectionMethod && count($constructor->getParameters()) > 0);

        $parameters = array_map(
            function (ReflectionParameter $parameter) use ($data): mixed {
                if (!array_key_exists($parameter->getName(), $data)) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $data[$parameter->getName()] = $parameter->getDefaultValue();
                    }
                }

                $type = $parameter->getType();
                assert($type instanceof ReflectionNamedType);

                if ($type->getName() === 'bool') {
                    if (!array_key_exists($parameter->getName(), $data)) {
                        $options = [true, false];

                        if ($parameter->allowsNull()) {
                            $options[] = null;
                        }

                        $data[$parameter->getName()] = $options[array_rand($options)];
                    }

                    return $data[$parameter->getName()];
                }

                if (!is_subclass_of($type->getName(), ValueObject::class)) {
                    throw new RuntimeException();
                }

                if (!array_key_exists($parameter->getName(), $data)) {
                    // Only do null on 1/4 cases
                    if ($parameter->allowsNull() && random_int(0, 3) === 2) {
                        return null;
                    }

                    return $this->hydrate($type->getName(), null);
                }

                return $this->hydrate($type->getName(), $data[$parameter->getName()] ?? null);
            },
            $constructor->getParameters(),
        );

        return new $className(...$parameters);
    }

    /**
     * @param class-string<EnumValueObject> $className
     *
     * @throws InvalidDataType
     */
    private function hydrateEnum(string $className, mixed $data): EnumValueObject
    {
        if (!is_string($data)) {
            if ($data === null) {
                $options = $className::cases();
                $key = array_rand($options);

                $data = $options[$key]->getValue();
            } else {
                throw new InvalidDataType();
            }
        }

        return $className::from($data);
    }

    /**
     * @param class-string<NumberValueObject> $className
     *
     * @throws RandomException
     * @throws InvalidDataType
     *
     * @todo with php 8.3 random float is supported
     */
    private function hydrateNumber(string $className, mixed $data): NumberValueObject
    {
        if (!is_int($data) && !is_float($data)) {
            if (is_null($data)) {
                $multipleOf = $className::getMultipleOf();

                // To prevent overflow split int & float
                if (is_int($multipleOf)) {
                    $maxRandom = (int)(($className::getMaximumValue() - $className::getMinimumValue()) / $className::getMultipleOf());

                    $selected = $maxRandom > 0
                        ? random_int(0, $maxRandom)
                        : 0;

                    $data = (int)$className::getMinimumValue() +  ($selected * $className::getMultipleOf());
                } else {
                    $minSteps = (int)ceil($className::getMinimumValue() / $className::getMultipleOf());
                    $maxSteps = (int)floor($className::getMaximumValue() / $className::getMultipleOf());
                    $randomSteps = random_int(0, (int)floor($maxSteps - $minSteps));

                    $data = ($minSteps + $randomSteps) * $className::getMultipleOf();
                }
            } else {
                throw new InvalidDataType();
            }
        }

        return new $className($data);
    }

    /**
     * @param class-string<StringValueObject> $className
     *
     * @throws InvalidDataType
     * @throws RandomException
     * @throws ReflectionException
     */
    private function hydrateString(string $className, mixed $data): StringValueObject
    {
        if (!is_string($data)) {
            if ($data === null) {
                if (is_subclass_of($className, StringFormatValueObject::class)) {
                    $class = new ReflectionClass($className);
                    $attributes = $class->getAttributes(DocExample::class);

                    if (count($attributes) === 0) {
                        throw new RuntimeException("Cannot generate value for '{$className}'");
                    }

                    $attribute = $attributes[array_rand($attributes)]->newInstance();
                    assert(is_string($attribute->example));

                    $data = $attribute->example;
                } else {
                    $length = random_int($className::getMinimumLength(), $className::getMaximumLength());
                    $bytes = (int)ceil($length / 2);

                    if ($bytes < 1) {
                        throw new RuntimeException();
                    }

                    $data = substr(
                        bin2hex(random_bytes($bytes)),
                        0,
                        $length,
                    );
                }
            } else {
                throw new InvalidDataType();
            }
        }

        return new $className($data);
    }
}
