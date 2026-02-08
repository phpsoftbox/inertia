<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia;

use InvalidArgumentException;
use PhpSoftBox\Inertia\Area\InertiaAreaConfig;

use function array_key_exists;
use function is_string;
use function trim;

final class InertiaConfig
{
    /**
     * @var array<string, InertiaAreaConfig>
     */
    private readonly array $areas;
    private readonly ?string $defaultArea;

    /**
     * @param array<string, mixed> $shared
     * @param array<string, InertiaAreaConfig|string> $areas
     */
    public function __construct(
        private readonly string $rootView,
        private readonly string $rootId = 'app',
        private readonly ?string $version = null,
        private readonly array $shared = [],
        private readonly bool $ssrEnabled = false,
        ?string $defaultArea = null,
        array $areas = [],
    ) {
        $this->defaultArea = $this->resolveDefaultArea($areas, $defaultArea);
        $this->areas       = $this->normalizeAreas($areas);

        if ($this->defaultArea !== null && !array_key_exists($this->defaultArea, $this->areas)) {
            throw new InvalidArgumentException("Default Inertia area is not configured: {$this->defaultArea}");
        }
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

    public function defaultArea(): ?string
    {
        return $this->defaultArea;
    }

    /**
     * @return array<string, InertiaAreaConfig>
     */
    public function areas(): array
    {
        return $this->areas;
    }

    /**
     * @param array<string, InertiaAreaConfig|string> $areas
     */
    private function resolveDefaultArea(array $areas, ?string $defaultArea): ?string
    {
        if ($defaultArea !== null) {
            $defaultArea = trim($defaultArea);

            return $defaultArea !== '' ? $defaultArea : null;
        }

        $configuredDefault = $areas['default'] ?? null;
        if (!is_string($configuredDefault)) {
            return null;
        }

        $configuredDefault = trim($configuredDefault);

        return $configuredDefault !== '' ? $configuredDefault : null;
    }

    /**
     * @param array<string, InertiaAreaConfig|string> $areas
     * @return array<string, InertiaAreaConfig>
     */
    private function normalizeAreas(array $areas): array
    {
        $normalized = [];

        foreach ($areas as $name => $area) {
            if ($name === 'default') {
                continue;
            }

            if (!$area instanceof InertiaAreaConfig) {
                throw new InvalidArgumentException("Inertia area config must be an instance of InertiaAreaConfig: {$name}");
            }

            $name = trim((string) $name);
            if ($name === '') {
                throw new InvalidArgumentException('Inertia area name must not be empty.');
            }

            $normalized[$name] = $area;
        }

        return $normalized;
    }
}
