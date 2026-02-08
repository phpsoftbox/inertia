<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Page;

use function array_map;

final class Menu
{
    /**
     * @var list<MenuItem>
     */
    private array $items = [];

    public function add(MenuItem $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    public function addDivider(): self
    {
        $this->items[] = MenuItem::divider();

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (MenuItem $item): array => $item->toArray(), $this->items);
    }
}
