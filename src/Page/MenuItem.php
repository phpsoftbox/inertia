<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Page;

use function array_filter;
use function array_map;

final class MenuItem
{
    /**
     * @var list<MenuItem>
     */
    private array $children           = [];
    private ?int $badge               = null;
    private ?MenuMatchMode $matchMode = null;
    private bool $divider             = false;
    private bool $disabled            = false;

    public function __construct(
        private readonly string $label,
        private readonly ?string $href = null,
        private readonly ?string $icon = null,
        private readonly ?string $id = null,
    ) {
    }

    public static function divider(): self
    {
        $item = new self('');

        $item->divider = true;

        return $item;
    }

    public function withBadge(?int $badge): self
    {
        $this->badge = $badge;

        return $this;
    }

    public function withMatchMode(MenuMatchMode $mode): self
    {
        $this->matchMode = $mode;

        return $this;
    }

    public function disable(bool $disabled = true): self
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * @param list<MenuItem> $children
     */
    public function setChildren(array $children): self
    {
        $this->children = $children;

        return $this;
    }

    public function addChild(MenuItem $child): self
    {
        $this->children[] = $child;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->divider) {
            return ['divider' => true];
        }

        $data = [
            'id'       => $this->id,
            'label'    => $this->label,
            'href'     => $this->href,
            'icon'     => $this->icon,
            'badge'    => $this->badge,
            'match'    => $this->matchMode?->value,
            'disabled' => $this->disabled ?: null,
            'children' => $this->children === []
                ? null
                : array_map(static fn (MenuItem $item): array => $item->toArray(), $this->children),
        ];

        return array_filter($data, static fn (mixed $value): bool => $value !== null);
    }
}
