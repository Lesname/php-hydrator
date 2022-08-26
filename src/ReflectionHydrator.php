<?php
declare(strict_types=1);

namespace LessHydrator;

use BackedEnum;
use LessHydrator\Attribute\DefaultValue;
use LessHydrator\Exception\MissingValue;
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
use RuntimeException;

final class ReflectionHydrator implements Hydrator
{
    /**
     * @param class-string<T> $className
     * @param array<mixed>|int|float|string $data
     *
     * @template T of \LessValueObject\ValueObject
     *
     * @return T
     *
     * @throws ReflectionException
     * @throws MissingValue
     */
    public function hydrate(string $className, array|int|float|string $data): ValueObject
    {
        $hydrated = is_array($data)
            ? $this->hydrateFromArray($className, $data)
            : $this->hydrateFromScalar($className, $data);

        assert($hydrated instanceof $className);

        return $hydrated;
    }

    /**
     * @param class-string<ValueObject> $className
     * @param array<mixed> $data
     *
     * @return ValueObject
     *
     * @throws MissingValue
     *
     * @throws ReflectionException
     */
    private function hydrateFromArray(string $className, array $data): ValueObject
    {
        if (is_subclass_of($className, CollectionValueObject::class)) {
            /** @var class-string<CollectionValueObject<ValueObject>> $className */
            $collection = $this->hydrateCollection($className, $data);
            assert($collection instanceof $className);

            return $collection;
        }

        return $this->hydrateComposite($className, $data);
    }

    /**
     * @param class-string<CollectionValueObject<ValueObject>> $className
     * @param array<mixed> $data
     *
     * @return ValueObject
     *
     * @throws MissingValue
     * @throws ReflectionException
     */
    private function hydrateCollection(string $className, array $data): ValueObject
    {
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

    /**
     * @param class-string<T> $className
     * @param array<mixed> $data
     *
     * @return T
     * @throws MissingValue
     *
     * @template T of \LessValueObject\ValueObject
     *
     * @throws ReflectionException
     */
    private function hydrateComposite(string $className, array $data): ValueObject
    {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        assert($constructor instanceof ReflectionMethod && count($constructor->getParameters()) > 0);

        return new $className(
            ...array_map(
                function (ReflectionParameter $item) use ($data): mixed {
                    if (!array_key_exists($item->getName(), $data)) {
                        foreach ($item->getAttributes(DefaultValue::class) as $attribute) {
                            $attribute = $attribute->newInstance();

                            return $attribute->default;
                        }

                        if ($item->allowsNull()) {
                            return null;
                        }

                        throw new MissingValue($item->getName());
                    } elseif ($data[$item->getName()] === null) {
                        if ($item->allowsNull()) {
                            return null;
                        }

                        throw new RuntimeException('Null not allowed');
                    }

                    $type = $item->getType();
                    assert($type instanceof ReflectionNamedType);

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
     *
     * @return ValueObject
     */
    private function hydrateFromScalar(string $className, int|float|string $data): ValueObject
    {
        if (is_subclass_of($className, NumberValueObject::class)) {
            $data = is_subclass_of($className, IntValueObject::class)
                ? (int)$data
                : (float)$data;
        }

        if (
            is_subclass_of($className, EnumValueObject::class)
            && is_subclass_of($className, BackedEnum::class)
        ) {
            /** @var class-string<EnumValueObject&BackedEnum> $className */
            assert(is_string($data));

            return $this->hydrateEnum($className, $data);
        }

        return new $className($data);
    }

    /**
     * @param class-string<EnumValueObject&BackedEnum> $className
     * @param string $data
     *
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     */
    private function hydrateEnum(string $className, string $data): ValueObject
    {
        return $className::from($data);
    }
}
