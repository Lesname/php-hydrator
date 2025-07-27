<?php
declare(strict_types=1);

namespace LesHydrator\Exception;

use Throwable;

/**
 * @psalm-immutable
 */
final class FailedComposite extends AbstractException
{
    public function __construct(
        public readonly string $composite,
        ?Throwable $previous
    ) {
        parent::__construct(
            "Failed creating composite {$this->composite}",
            previous: $previous,
        );
    }
}
