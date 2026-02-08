<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Page;

use function is_string;
use function preg_split;
use function trim;

final class PageMeta
{
    private ?string $title       = null;
    private ?string $description = null;
    /**
     * @var string[]
     */
    private array $keywords = [];

    public function setTitle(?string $title): self
    {
        $title       = is_string($title) ? trim($title) : null;
        $this->title = $title !== '' ? $title : null;

        return $this;
    }

    public function setDescription(?string $description): self
    {
        $description       = is_string($description) ? trim($description) : null;
        $this->description = $description !== '' ? $description : null;

        return $this;
    }

    /**
     * @param string[]|string|null $keywords
     */
    public function setKeywords(array|string|null $keywords): self
    {
        if ($keywords === null) {
            $this->keywords = [];

            return $this;
        }

        if (is_string($keywords)) {
            $keywords = preg_split('/,/', $keywords) ?: [];
        }

        $normalized = [];
        foreach ($keywords as $keyword) {
            if (!is_string($keyword)) {
                continue;
            }
            $keyword = trim($keyword);
            if ($keyword === '') {
                continue;
            }
            $normalized[] = $keyword;
        }

        $this->keywords = $normalized;

        return $this;
    }

    public function clear(): void
    {
        $this->title       = null;
        $this->description = null;
        $this->keywords    = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->keywords !== []) {
            $data['keywords'] = $this->keywords;
        }

        return $data;
    }

    public function isEmpty(): bool
    {
        return $this->title === null && $this->description === null && $this->keywords === [];
    }
}
