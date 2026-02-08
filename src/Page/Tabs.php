<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Page;

use function is_array;
use function is_string;
use function trim;

final class Tabs
{
    /**
     * @var list<Tab>
     */
    private array $tabs        = [];
    private ?string $activeKey = null;

    /**
     * @param list<Tab> $tabs
     */
    public function __construct(?string $activeKey = null, array $tabs = [])
    {
        $this->activeKey = $activeKey !== null && trim($activeKey) !== '' ? trim($activeKey) : null;
        $this->addTabs($tabs);
    }

    public function setActiveKey(?string $activeKey): self
    {
        $this->activeKey = $activeKey !== null && trim($activeKey) !== '' ? trim($activeKey) : null;

        return $this;
    }

    public function add(string $label, string $key, ?string $href = null, bool $disabled = false): self
    {
        $label = trim($label);
        $key   = trim($key);

        if ($label === '' || $key === '') {
            return $this;
        }

        $this->tabs[] = new Tab($label, $key, $href, $disabled);

        return $this;
    }

    public function addTab(Tab $tab): self
    {
        $this->tabs[] = $tab;

        return $this;
    }

    /**
     * @param array<int, Tab|array<string, mixed>> $tabs
     */
    public function addTabs(array $tabs): self
    {
        foreach ($tabs as $tab) {
            if ($tab instanceof Tab) {
                $this->addTab($tab);
                continue;
            }

            if (!is_array($tab)) {
                continue;
            }

            $label = $tab['label'] ?? null;
            $key   = $tab['key'] ?? null;
            if (!is_string($label) || !is_string($key)) {
                continue;
            }

            $href     = is_string($tab['href'] ?? null) ? (string) $tab['href'] : null;
            $disabled = (bool) ($tab['disabled'] ?? false);

            $this->add($label, $key, $href, $disabled);
        }

        return $this;
    }

    public function replace(Tabs $tabs): self
    {
        $this->tabs      = $tabs->tabs;
        $this->activeKey = $tabs->activeKey;

        return $this;
    }

    /**
     * @param array<int, Tab|array<string, mixed>> $tabs
     */
    public function set(array $tabs, ?string $activeKey = null): self
    {
        $this->tabs = [];
        $this->addTabs($tabs);

        if ($activeKey !== null) {
            $this->setActiveKey($activeKey);
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $tabs = [];
        foreach ($this->tabs as $tab) {
            $tabs[] = $tab->toArray();
        }

        return [
            'activeKey' => $this->activeKey,
            'tabs'      => $tabs,
        ];
    }

    public function clear(): void
    {
        $this->tabs      = [];
        $this->activeKey = null;
    }

    public function isEmpty(): bool
    {
        return $this->tabs === [];
    }
}
