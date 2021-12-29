<?php
declare(strict_types=1);

namespace LessHydrator\Exception;

/**
 * @psalm-immutable
 */
final class NonValueObject extends AbstractException
{
    public function __construct(public string $classname)
    {
        parent::__construct("Expected ValueObject class name, got '{$classname}'");
    }
}
