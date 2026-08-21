<?php

declare(strict_types=1);

namespace LesHydrator\Config;

use LesHydrator\Hydrator;
use LesHydrator\ReflectionHydrator;

/**
 * @psalm-immutable
 */
final class ConfigProvider
{
    /**
     * @return array<string, mixed>
     *
     * @psalm-pure
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                'aliases' => [
                    Hydrator::class => ReflectionHydrator::class,
                ],
                'invokables' => [
                    ReflectionHydrator::class => ReflectionHydrator::class,
                ],
            ],
        ];
    }
}
