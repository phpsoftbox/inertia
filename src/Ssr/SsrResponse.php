<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Ssr;

final class SsrResponse
{
    /**
     * @param string[] $head
     */
    public function __construct(
        private readonly array $head = [],
        private readonly string $body = '',
    ) {
    }

    /**
     * @return string[]
     */
    public function head(): array
    {
        return $this->head;
    }

    public function body(): string
    {
        return $this->body;
    }
}
