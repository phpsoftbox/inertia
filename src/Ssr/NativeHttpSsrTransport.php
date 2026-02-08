<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Ssr;

use RuntimeException;
use Throwable;

use function array_merge;
use function file_get_contents;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function stream_context_create;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class NativeHttpSsrTransport implements HttpSsrTransportInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>|null
     */
    public function postJson(string $url, array $payload, float $timeout, array $headers = []): ?array
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $exception) {
            throw new RuntimeException('Failed to encode Inertia SSR payload.', previous: $exception);
        }

        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ], $headers);

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => $this->headersToString($headers),
                'content'       => $body,
                'timeout'       => $timeout > 0.0 ? $timeout : 2.0,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if (!is_string($response) || $response === '') {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, string> $headers
     */
    private function headersToString(array $headers): string
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode("\r\n", $lines);
    }
}
