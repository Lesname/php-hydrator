<?php
declare(strict_types=1);

namespace LesHydrator;

use ReflectionClass;
use RuntimeException;
use Random\Randomizer;
use ReflectionException;
use ReflectionParameter;
use ReflectionNamedType;
use ReflectionUnionType;
use Random\RandomException;
use LesValueObject\ValueObject;
use LesHydrator\Exception\NoMatch;
use LesValueObject\Enum\EnumValueObject;
use LesValueObject\Attribute\DocExample;
use LesHydrator\Exception\InvalidDataType;
use LesValueObject\Number\NumberValueObject;
use LesValueObject\String\StringValueObject;
use LesValueObject\Composite\CompositeValueObject;
use LesValueObject\Collection\CollectionValueObject;
use LesValueObject\String\Format\StringFormatValueObject;

final class FakerHydrator extends AbstractHydrator
{
    private readonly Randomizer $randomizer;
    private readonly Hydrator $hydrator;

    public function __construct(
        ?Randomizer $randomizer = null,
        ?Hydrator $hydrator = null,
    ) {
        $this->randomizer = $randomizer ?? new Randomizer();
        $this->hydrator = $hydrator ?? new ReflectionHydrator();
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of CollectionValueObject<ValueObject>
     */
    protected function hydrateCollection(string $className, mixed $data): CollectionValueObject
    {
        if (!is_array($data) && $data === null) {
            $items = $this->randomizer->getInt($className::getMinimumSize(), $className::getMaximumSize());
            $data = array_fill(0, $items, null);
        }

        return parent::hydrateCollection($className, $data);
    }

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
            if ($itemValue !== null) {
                $itemType = $this->matchType($itemType, $itemValue, $data);
            } elseif (count($itemType) > 0) {
                $itemType = $this->pickArrayItem($itemType);
            } else {
                throw new RuntimeException();
            }

            assert(class_exists($itemType));
        }

        return $this->hydrate($itemType, $itemValue);
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of CompositeValueObject
     *
     * @throws InvalidDataType
     */
    protected function hydrateComposite(string $className, mixed $data): CompositeValueObject
    {
        return parent::hydrateComposite($className, $data ?? []);
    }

    /**
     * @param array<mixed> $data
     *
     * @throws Exception\MissingValue
     * @throws InvalidDataType
     * @throws NoMatch
     * @throws ReflectionException
     */
    protected function hydrateCompositeParameter(ReflectionParameter $parameter, array $data): mixed
    {
        if (!isset($data[$parameter->getName()])) {
            $parameterType = $parameter->getType();

            if ($parameterType === null) {
                throw new RuntimeException();
            }

            $generate = !array_key_exists($parameter->getName(), $data)
                ||
                (
                    $data[$parameter->getName()] === null
                    &&
                    !$parameterType->allowsNull()
                );

            if ($generate) {
                if (!$parameterType instanceof ReflectionNamedType) {
                    if ($parameterType instanceof ReflectionUnionType) {
                        $unionTypes = $parameterType->getTypes();
                        assert(count($unionTypes) > 0);

                        $parameterType = $this->pickArrayItem($unionTypes);
                    } else {
                        throw new RuntimeException();
                    }
                }

                if (!$parameterType instanceof ReflectionNamedType) {
                    throw new RuntimeException();
                }

                if ($parameterType->isBuiltin()) {
                    $data[$parameter->getName()] = match ($parameterType->getName()) {
                        'bool' => (bool)$this->randomizer->getInt(0, 1),
                        'null' => null,
                        default => throw new RuntimeException("Unsupported '{$parameterType->getName()}'"),
                    };
                } else {
                    $typeName = $parameterType->getName();

                    if (!class_exists($typeName)) {
                        throw new RuntimeException();
                    }

                    if (!is_subclass_of($typeName, ValueObject::class)) {
                        throw new RuntimeException();
                    }

                    $data[$parameter->getName()] = $this->hydrate($typeName, null);
                }
            }
        }

        return parent::hydrateCompositeParameter($parameter, $data);
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of EnumValueObject
     *
     * @throws InvalidDataType
     */
    protected function hydrateEnum(string $className, mixed $data): EnumValueObject
    {
        if (!is_string($data)) {
            if ($data === null) {
                $options = $className::cases();
                assert(count($options) > 0);

                $data = $this->pickArrayItem($options)->value;
            } else {
                throw new InvalidDataType();
            }
        }

        return $this->hydrator->hydrate($className, $data);
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of NumberValueObject
     *
     * @throws RandomException
     * @throws InvalidDataType
     *
     * @todo with php 8.3 random float is supported
     */
    protected function hydrateNumber(string $className, mixed $data): NumberValueObject
    {
        if (!is_int($data) && !is_float($data)) {
            if (is_null($data)) {
                $multipleOf = $className::getMultipleOf();

                // To prevent overflow split int & float
                if (is_int($multipleOf)) {
                    $maxRandom = (int)(($className::getMaximumValue() - $className::getMinimumValue()) / $className::getMultipleOf());

                    $selected = $maxRandom > 0
                        ? $this->randomizer->getInt(0, $maxRandom)
                        : 0;

                    $data = (int)$className::getMinimumValue() +  ($selected * $className::getMultipleOf());
                } else {
                    $minSteps = (int)ceil($className::getMinimumValue() / $className::getMultipleOf());
                    $maxSteps = (int)floor($className::getMaximumValue() / $className::getMultipleOf());
                    $randomSteps = $this->randomizer->getInt(0, (int)floor($maxSteps - $minSteps));

                    $data = ($minSteps + $randomSteps) * $className::getMultipleOf();
                }
            } else {
                throw new InvalidDataType();
            }
        }

        return $this->hydrator->hydrate($className, $data);
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @template T of StringValueObject
     *
     * @throws InvalidDataType
     * @throws ReflectionException
     */
    protected function hydrateString(string $className, mixed $data): StringValueObject
    {
        if (!is_string($data)) {
            if ($data === null) {
                if (is_subclass_of($className, StringFormatValueObject::class)) {
                    $class = new ReflectionClass($className);
                    $attributes = $class->getAttributes(DocExample::class);

                    if (count($attributes) === 0) {
                        throw new RuntimeException("Cannot generate value for '{$className}'");
                    }

                    $data = $this->pickArrayItem($attributes)->newInstance()->example;
                } else {
                    $length = $this->randomizer->getInt($className::getMinimumLength(), $className::getMaximumLength());

                    if ($length === 0) {
                        $data = '';
                    } else {
                        $bytes = (int)ceil($length / 2);

                        if ($bytes < 1) {
                            throw new RuntimeException();
                        }

                        $hex = bin2hex($this->randomizer->getBytes($bytes));
                        $data = substr($hex, 0, $length);
                    }
                }
            } else {
                throw new InvalidDataType();
            }
        }

        return $this->hydrator->hydrate($className, $data);
    }

    /**
     * @param non-empty-array<T> $array
     *
     * @return T
     *
     * @template T
     */
    private function pickArrayItem(array $array)
    {
        return $this->randomizer->shuffleArray($array)[0];
    }
}
