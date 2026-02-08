<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia;

final class InertiaPage
{
    /**
     * @param array<string, mixed> $props
     */
    public function __construct(
        private readonly string $component,
        private readonly array $props,
        private readonly string $url,
        private readonly ?string $version = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'component' => $this->component,
            'props'     => $this->props,
            'url'       => $this->url,
            'version'   => $this->version,
        ];
    }
}
