<?php
declare(strict_types=1);

namespace LessHydrator\Exception;

/**
 * @psalm-immutable
 */
final class NoMatch extends AbstractException
{
    public function __construct()
    {
        parent::__construct('No match');
    }
}
