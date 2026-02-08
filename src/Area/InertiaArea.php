<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Area;

final readonly class InertiaArea
{
    public function __construct(
        private string $name,
        private InertiaAreaConfig $config,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function config(): InertiaAreaConfig
    {
        return $this->config;
    }
}
