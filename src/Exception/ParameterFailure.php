<?php
declare(strict_types=1);

namespace LessHydrator\Exception;

use Throwable;

/**
 * @psalm-immutable
 */
final class ParameterFailure extends AbstractException
{
    public function __construct(string $name, Throwable $failure)
    {
        parent::__construct("Parameter {$name} failed: {$failure->getMessage()}", previous: $failure);
    }
}
