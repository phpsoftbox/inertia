<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Page;

use function is_array;
use function is_string;
use function trim;

final class Breadcrumbs
{
    /**
     * @var array<int, array{label:string, href?:string|null, active?:bool}>
     */
    private array $items = [];

    public function add(string $label, ?string $href = null, bool $active = false): self
    {
        $label = trim($label);
        if ($label === '') {
            return $this;
        }

        $this->items[] = [
            'label'  => $label,
            'href'   => $href,
            'active' => $active,
        ];

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function set(array $items): self
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = $item['label'] ?? null;
            if (!is_string($label) || trim($label) === '') {
                continue;
            }

            $normalized[] = [
                'label'  => trim($label),
                'href'   => is_string($item['href'] ?? null) ? (string) $item['href'] : null,
                'active' => (bool) ($item['active'] ?? false),
            ];
        }

        $this->items = $normalized;

        return $this;
    }

    /**
     * @return array<int, array{label:string, href?:string|null, active?:bool}>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function clear(): void
    {
        $this->items = [];
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
