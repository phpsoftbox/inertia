<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Inertia\InertiaPage;
use PhpSoftBox\Inertia\Ssr\HttpSsrRenderer;
use PhpSoftBox\Inertia\Ssr\HttpSsrTransportInterface;
use PhpSoftBox\Inertia\Ssr\SsrResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(HttpSsrRenderer::class)]
#[CoversClass(SsrResponse::class)]
#[CoversMethod(HttpSsrRenderer::class, 'render')]
#[CoversMethod(SsrResponse::class, 'head')]
#[CoversMethod(SsrResponse::class, 'body')]
final class HttpSsrRendererTest extends TestCase
{
    /**
     * Проверяем, что HTTP SSR renderer отправляет Inertia page и возвращает SsrResponse.
     *
     * @see HttpSsrRenderer::render()
     * @see SsrResponse::head()
     * @see SsrResponse::body()
     */
    #[Test]
    public function testRenderReturnsSsrResponse(): void
    {
        $transport = new class () implements HttpSsrTransportInterface {
            /** @var array<string, mixed>|null */
            public ?array $payload = null;

            public function postJson(string $url, array $payload, float $timeout, array $headers = []): ?array
            {
                $this->payload = $payload;

                return [
                    'head' => ['<title>Dashboard</title>', 123],
                    'body' => '<div>SSR</div>',
                ];
            }
        };

        $renderer = new HttpSsrRenderer(
            url: 'http://node:13714/render',
            transport: $transport,
        );

        $response = $renderer->render(
            new ServerRequest('GET', 'https://example.test/dashboard'),
            new InertiaPage('Dashboard', ['title' => 'Dashboard'], '/dashboard'),
        );

        $this->assertInstanceOf(SsrResponse::class, $response);
        $this->assertSame(['<title>Dashboard</title>'], $response->head());
        $this->assertSame('<div>SSR</div>', $response->body());
        $this->assertSame('Dashboard', $transport->payload['component'] ?? null);
    }

    /**
     * Проверяем fail-open режим renderer-а при ошибке SSR transport.
     *
     * @see HttpSsrRenderer::render()
     */
    #[Test]
    public function testRenderReturnsNullWhenTransportFailsSilently(): void
    {
        $transport = new class () implements HttpSsrTransportInterface {
            public function postJson(string $url, array $payload, float $timeout, array $headers = []): ?array
            {
                throw new RuntimeException('SSR server is unavailable.');
            }
        };

        $renderer = new HttpSsrRenderer(
            url: 'http://node:13714/render',
            failSilently: true,
            transport: $transport,
        );

        $response = $renderer->render(
            new ServerRequest('GET', 'https://example.test/'),
            new InertiaPage('Home', [], '/'),
        );

        $this->assertNull($response);
    }
}
