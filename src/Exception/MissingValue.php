<?php

declare(strict_types=1);

namespace LesHydrator\Exception;

/**
 * @psalm-immutable
 */
final class MissingValue extends AbstractException
{
    public function __construct(public readonly string $name)
    {
        parent::__construct("Missing '{$name}'");
    }
}
