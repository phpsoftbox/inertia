<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Ssr;

interface HttpSsrTransportInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>|null
     */
    public function postJson(string $url, array $payload, float $timeout, array $headers = []): ?array;
}
