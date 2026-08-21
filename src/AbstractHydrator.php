<?php

declare(strict_types=1);

namespace LesHydrator;

use Override;
use Throwable;
use ReflectionClass;
use RuntimeException;
use ReflectionMethod;
use ReflectionException;
use ReflectionParameter;
use ReflectionNamedType;
use LesValueObject\ValueObject;
use LesHydrator\Exception\MissingValue;
use LesValueObject\Enum\EnumValueObject;
use LesHydrator\Exception\InvalidDataType;
use LesHydrator\Exception\FailedComposite;
use LesHydrator\Exception\ParameterFailure;
use LesValueObject\Number\NumberValueObject;
use LesValueObject\String\StringValueObject;
use LesValueObject\Composite\CompositeValueObject;
use LesValueObject\Collection\CollectionValueObject;
use LesValueObject\Composite\WrappedCompositeValueObject;
use LesValueObject\Composite\Signature\SignatureCompositeValueObject;
use LesValueObject\Composite\AbstractDiscriminatorCompositeValueObject;

abstract class AbstractHydrator implements Hydrator
{
    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of ValueObject
     *
     * @throws ReflectionException
     * @throws InvalidDataType
     *
     * @psalm-impure
     */
    #[Override]
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
     *
     * @psalm-impure
     */
    protected function hydrateCollection(string $className, mixed $data): CollectionValueObject
    {
        if (!is_array($data)) {
            throw new InvalidDataType();
        }

        return new $className(
            array_map(
                fn (mixed $itemValue) => $this->hydrate($className::getItemType(), $itemValue),
                $data,
            ),
        );
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of CompositeValueObject
     *
     * @throws InvalidDataType
     * @throws ReflectionException
     *
     * @psalm-impure
     */
    protected function hydrateComposite(string $className, mixed $data): CompositeValueObject
    {
        if (!is_array($data)) {
            throw new InvalidDataType();
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return (new ReflectionClass($className))
            ->newLazyProxy(
                function () use ($className, $data) {
                    try {
                        if (is_subclass_of($className, SignatureCompositeValueObject::class)) {
                            $signature = $className::getSignature();
                            $parameters = [
                                array_map(
                                    fn (mixed $itemValue) => $this->hydrate($signature, $itemValue),
                                    $data,
                                ),
                            ];
                        } elseif (is_subclass_of($className, WrappedCompositeValueObject::class)) {
                            $parameters = [$data];
                        } else {
                            $reflection = new ReflectionClass($className);
                            $constructor = $reflection->getConstructor();

                            if ($constructor instanceof ReflectionMethod) {
                                $parameters = [];

                                if (is_subclass_of($className, AbstractDiscriminatorCompositeValueObject::class)) {
                                    $discriminatorProperty = $className::getDiscriminatingProperty();

                                    foreach ($constructor->getParameters() as $parameter) {
                                        try {
                                            if ($parameter->getName() === $discriminatorProperty) {
                                                $discriminatorField = $className::getDiscriminatingField();
                                                $discriminatorMap = $className::getDiscriminatingMapping();

                                                if (!isset($data[$discriminatorField])) {
                                                    throw new RuntimeException("Missing discriminator field: {$discriminatorField}");
                                                }

                                                if (!is_string($data[$discriminatorField])) {
                                                    $fieldType = get_debug_type($data[$discriminatorField]);

                                                    throw new RuntimeException("Discriminator field must be a string, got: {$fieldType}");
                                                }

                                                if (!isset($discriminatorMap[$data[$discriminatorField]])) {
                                                    throw new RuntimeException("Unknown discriminator value: {$data[$discriminatorField]}");
                                                }

                                                $result = $discriminatorMap[$data[$discriminatorField]];

                                                $parameters[] = $this->hydrate($result, $data[$discriminatorProperty]);
                                            } else {
                                                $parameters[] = $this->hydrateCompositeParameter($parameter, $data);
                                            }
                                        } catch (Throwable $e) {
                                            throw new ParameterFailure($parameter->getName(), $e);
                                        }
                                    }
                                } else {
                                    foreach ($constructor->getParameters() as $parameter) {
                                        try {
                                            $parameters[] = $this->hydrateCompositeParameter($parameter, $data);
                                        } catch (Throwable $e) {
                                            throw new ParameterFailure($parameter->getName(), $e);
                                        }
                                    }
                                }
                            } else {
                                $parameters = [];
                            }
                        }

                        return new $className(...$parameters);
                    } catch (Throwable $e) {
                        throw new FailedComposite($className, $e);
                    }
                }
            );
    }

    /**
     * @param array<mixed> $data
     *
     * @throws ReflectionException
     * @throws InvalidDataType
     * @throws MissingValue
     *
     * @psalm-impure
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
     *
     * @psalm-impure
     */
    abstract protected function hydrateEnum(string $className, mixed $data): EnumValueObject;

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of NumberValueObject
     *
     * @psalm-impure
     */
    abstract protected function hydrateNumber(string $className, mixed $data): NumberValueObject;

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of StringValueObject
     *
     * @psalm-impure
     */
    abstract protected function hydrateString(string $className, mixed $data): StringValueObject;

    /**
     * @psalm-pure
     */
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

        if (!is_bool($value)) {
            throw new InvalidDataType();
        }

        return $value;
    }
}
