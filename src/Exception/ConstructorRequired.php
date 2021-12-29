<?php
declare(strict_types=1);

namespace LessHydrator\Exception;

/**
 * @psalm-immutable
 */
final class ConstructorRequired extends AbstractException
{
    public function __construct(public string $className)
    {
        parent::__construct("'{$className}' requires constructor");
    }
}
