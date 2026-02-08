<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia;

final class InertiaConfig
{
    /**
     * @param array<string, mixed> $shared
     */
    public function __construct(
        private readonly string $rootView,
        private readonly string $rootId = 'app',
        private readonly ?string $version = null,
        private readonly array $shared = [],
        private readonly bool $ssrEnabled = false,
    ) {
    }

    public function rootView(): string
    {
        return $this->rootView;
    }

    public function rootId(): string
    {
        return $this->rootId;
    }

    public function version(): ?string
    {
        return $this->version;
    }

    /**
     * @return array<string, mixed>
     */
    public function shared(): array
    {
        return $this->shared;
    }

    public function ssrEnabled(): bool
    {
        return $this->ssrEnabled;
    }
}
