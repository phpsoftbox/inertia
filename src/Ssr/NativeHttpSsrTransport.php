<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Ssr;

use RuntimeException;
use Throwable;

use function array_merge;
use function file_get_contents;
use function http_get_last_response_headers;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function stream_context_create;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * HTTP-транспорт SSR на stream-обёртке PHP.
 *
 * Недоступный сервер, HTTP-статус вне 2xx, пустой ответ или невалидный JSON приводят к
 * RuntimeException; решение о fail-open принимает HttpSsrRenderer (флаг failSilently).
 */
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
        if (!is_string($response)) {
            throw new RuntimeException('Inertia SSR server is unavailable: ' . $url);
        }

        $status = $this->responseStatus(http_get_last_response_headers() ?? []);
        if ($status !== null && ($status < 200 || $status >= 300)) {
            throw new RuntimeException('Inertia SSR server responded with HTTP ' . $status . ': ' . $url);
        }

        if ($response === '') {
            throw new RuntimeException('Inertia SSR server returned an empty response: ' . $url);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Inertia SSR server returned invalid JSON: ' . $url);
        }

        return $decoded;
    }

    /**
     * @param list<string> $headers
     */
    private function responseStatus(array $headers): ?int
    {
        // При редиректах заголовки содержат несколько status-строк: берём последнюю.
        $status = null;
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return $status;
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
