<?php
declare(strict_types=1);

namespace LessHydrator\Matcher;

/**
 * @psalm-immutable
 */
final class FieldMatcher implements Matcher
{
    public function __construct(
        public readonly string $field,
        public readonly string $value,
    ) {}

    public function matches(mixed $value, mixed $parentValue): bool
    {
        return is_array($value)
            &&
            array_key_exists($this->field, $value)
            &&
            $value[$this->field] === $this->value;
    }
}
