<?php
declare(strict_types=1);

namespace LessHydrator\Exception;

/**
 * @psalm-immutable
 */
final class MissingValue extends AbstractException
{
    public function __construct(public string $name)
    {
        parent::__construct("Missing '{$name}'");
    }
}
