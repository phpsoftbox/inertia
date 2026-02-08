<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Ssr;

use PhpSoftBox\Inertia\InertiaPage;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function array_values;
use function is_array;
use function is_string;
use function trim;

final class HttpSsrRenderer implements SsrRendererInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $url,
        private readonly float $timeout = 2.0,
        private readonly bool $failSilently = true,
        private readonly array $headers = [],
        private readonly ?HttpSsrTransportInterface $transport = null,
    ) {
    }

    public function render(ServerRequestInterface $request, InertiaPage $page): ?SsrResponse
    {
        $url = trim($this->url);
        if ($url === '') {
            return null;
        }

        try {
            $response = ($this->transport ?? new NativeHttpSsrTransport())->postJson(
                $url,
                $page->toArray(),
                $this->timeout,
                $this->headers,
            );
        } catch (Throwable $exception) {
            if ($this->failSilently) {
                return null;
            }

            throw $exception;
        }

        if (!is_array($response)) {
            return null;
        }

        $head = [];
        if (is_array($response['head'] ?? null)) {
            foreach ($response['head'] as $item) {
                if (is_string($item)) {
                    $head[] = $item;
                }
            }
        }

        $body = is_string($response['body'] ?? null) ? (string) $response['body'] : '';
        if ($head === [] && $body === '') {
            return null;
        }

        return new SsrResponse(array_values($head), $body);
    }
}
