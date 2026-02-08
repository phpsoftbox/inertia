<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Area;

use function array_values;
use function is_string;
use function trim;

final class InertiaAreaConfig
{
    /** @var list<string> */
    private readonly array $hosts;
    /** @var list<string> */
    private readonly array $paths;
    /** @var list<string> */
    private readonly array $pathPrefixes;
    /**
     * @var array<string, mixed>
     */
    private readonly array $shared;

    /**
     * @param list<string> $hosts
     * @param list<string> $paths
     * @param list<string> $pathPrefixes
     * @param array<string, mixed> $shared
     */
    public function __construct(
        array $hosts = [],
        array $paths = [],
        array $pathPrefixes = [],
        private readonly ?bool $ssr = null,
        array $shared = [],
    ) {
        $this->hosts        = $this->normalizeList($hosts);
        $this->paths        = $this->normalizeList($paths);
        $this->pathPrefixes = $this->normalizeList($pathPrefixes);
        $this->shared       = $shared;
    }

    /**
     * @return list<string>
     */
    public function hosts(): array
    {
        return $this->hosts;
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * @return list<string>
     */
    public function pathPrefixes(): array
    {
        return $this->pathPrefixes;
    }

    public function ssr(): ?bool
    {
        return $this->ssr;
    }

    /**
     * @return array<string, mixed>
     */
    public function shared(): array
    {
        return $this->shared;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function normalizeList(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values($normalized);
    }
}
