<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Page;

final readonly class Tab
{
    public function __construct(
        public string $label,
        public string $key,
        public ?string $href = null,
        public bool $disabled = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label'    => $this->label,
            'key'      => $this->key,
            'href'     => $this->href,
            'disabled' => $this->disabled,
        ];
    }
}
