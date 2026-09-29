<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Inertia\InertiaPage;
use PhpSoftBox\Inertia\Ssr\HttpSsrRenderer;
use PhpSoftBox\Inertia\Ssr\HttpSsrTransportInterface;
use PhpSoftBox\Inertia\Ssr\NativeHttpSsrTransport;
use PhpSoftBox\Inertia\Ssr\SsrResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(HttpSsrRenderer::class)]
#[CoversClass(SsrResponse::class)]
#[CoversClass(NativeHttpSsrTransport::class)]
#[CoversMethod(HttpSsrRenderer::class, 'render')]
#[CoversMethod(SsrResponse::class, 'head')]
#[CoversMethod(SsrResponse::class, 'body')]
#[CoversMethod(NativeHttpSsrTransport::class, 'postJson')]
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

    /**
     * Проверим, что в строгом режиме ошибка transport пробрасывается.
     *
     * @see HttpSsrRenderer::render()
     */
    #[Test]
    public function strictModeRethrowsTransportException(): void
    {
        $transport = new class () implements HttpSsrTransportInterface {
            public function postJson(string $url, array $payload, float $timeout, array $headers = []): ?array
            {
                throw new RuntimeException('SSR server is unavailable.');
            }
        };

        $renderer = new HttpSsrRenderer(
            url: 'http://node:13714/render',
            failSilently: false,
            transport: $transport,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSR server is unavailable.');

        $renderer->render(new ServerRequest('GET', 'https://example.test/'), new InertiaPage('Home', [], '/'));
    }

    /**
     * Проверим, что в строгом режиме пустой ответ transport (null) приводит к исключению.
     *
     * @see HttpSsrRenderer::render()
     */
    #[Test]
    public function strictModeThrowsWhenTransportReturnsNull(): void
    {
        $transport = new class () implements HttpSsrTransportInterface {
            public function postJson(string $url, array $payload, float $timeout, array $headers = []): ?array
            {
                return null;
            }
        };

        $renderer = new HttpSsrRenderer(
            url: 'http://node:13714/render',
            failSilently: false,
            transport: $transport,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Inertia SSR server returned no response');

        $renderer->render(new ServerRequest('GET', 'https://example.test/'), new InertiaPage('Home', [], '/'));
    }

    /**
     * Проверим, что в строгом режиме недоступный SSR-сервер (встроенный transport) приводит к исключению.
     *
     * @see HttpSsrRenderer::render()
     * @see NativeHttpSsrTransport::postJson()
     */
    #[Test]
    public function strictModeThrowsWhenServerIsUnavailable(): void
    {
        // Порт 1 на localhost закрыт: соединение отклоняется сразу.
        $renderer = new HttpSsrRenderer(
            url: 'http://127.0.0.1:1/render',
            timeout: 1.0,
            failSilently: false,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Inertia SSR server is unavailable');

        $renderer->render(new ServerRequest('GET', 'https://example.test/'), new InertiaPage('Home', [], '/'));
    }

    /**
     * Проверим, что в fail-open режиме недоступный SSR-сервер даёт null.
     *
     * @see HttpSsrRenderer::render()
     * @see NativeHttpSsrTransport::postJson()
     */
    #[Test]
    public function failOpenModeReturnsNullWhenServerIsUnavailable(): void
    {
        $renderer = new HttpSsrRenderer(
            url: 'http://127.0.0.1:1/render',
            timeout: 1.0,
        );

        $response = $renderer->render(new ServerRequest('GET', 'https://example.test/'), new InertiaPage('Home', [], '/'));

        self::assertNull($response);
    }
}
