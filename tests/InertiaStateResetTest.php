<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\StreamFactory;
use PhpSoftBox\Inertia\Inertia;
use PhpSoftBox\Inertia\InertiaConfig;
use PhpSoftBox\Inertia\InertiaPage;
use PhpSoftBox\Inertia\View\ViewRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function json_decode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Inertia::class)]
#[CoversMethod(Inertia::class, 'share')]
#[CoversMethod(Inertia::class, 'shareMany')]
#[CoversMethod(Inertia::class, 'shareProvider')]
#[CoversMethod(Inertia::class, 'setRequest')]
#[CoversMethod(Inertia::class, 'reset')]
#[CoversMethod(Inertia::class, 'render')]
final class InertiaStateResetTest extends TestCase
{
    /**
     * Проверим, что данные share() одного запроса не видны следующему запросу после setRequest().
     *
     * @see Inertia::share()
     * @see Inertia::setRequest()
     */
    #[Test]
    public function sharedValueDoesNotLeakToNextRequest(): void
    {
        $inertia = $this->createInertia();

        $inertia->setRequest($this->createRequest('/first'));
        $inertia->share('auth', ['user' => ['id' => 1]]);
        $first = $this->renderProps($inertia);

        $inertia->setRequest($this->createRequest('/second'));
        $second = $this->renderProps($inertia);

        self::assertSame(['user' => ['id' => 1]], $first['auth']);
        self::assertArrayNotHasKey('auth', $second);
    }

    /**
     * Проверим, что reset() очищает данные shareMany() и request-scoped провайдеры.
     *
     * @see Inertia::reset()
     * @see Inertia::shareMany()
     * @see Inertia::shareProvider()
     */
    #[Test]
    public function resetClearsRequestSharedData(): void
    {
        $inertia = $this->createInertia();

        $inertia->setRequest($this->createRequest('/first'));
        $inertia->shareMany(['auth' => ['user' => ['id' => 1]]]);
        $inertia->shareProvider(static fn (): array => ['scope' => 'request']);

        $inertia->reset();
        $inertia->setRequest($this->createRequest('/second'));
        $props = $this->renderProps($inertia);

        self::assertArrayNotHasKey('auth', $props);
        self::assertArrayNotHasKey('scope', $props);
    }

    /**
     * Проверим, что reset() сбрасывает текущий request: рендер без нового setRequest() невозможен.
     *
     * @see Inertia::reset()
     * @see Inertia::render()
     */
    #[Test]
    public function resetClearsCurrentRequest(): void
    {
        $inertia = $this->createInertia();
        $inertia->setRequest($this->createRequest('/first'));

        $inertia->reset();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Inertia request is not set.');

        $inertia->render('Dashboard');
    }

    /**
     * Проверим, что reset() сохраняет базовые shared-данные конфига и persistent-провайдеры.
     *
     * @see Inertia::reset()
     * @see Inertia::shareProvider()
     */
    #[Test]
    public function resetKeepsBaseSharedDataAndPersistentProviders(): void
    {
        $inertia = $this->createInertia(['app' => ['name' => 'Demo']]);
        $inertia->shareProvider(static fn (): array => ['locale' => 'ru'], persistent: true);

        $inertia->reset();
        $inertia->setRequest($this->createRequest('/second'));
        $props = $this->renderProps($inertia);

        self::assertSame(['name' => 'Demo'], $props['app']);
        self::assertSame('ru', $props['locale']);
    }

    /**
     * Проверим, что persistent-провайдер с тем же ключом заменяет предыдущий, а не накапливается.
     *
     * @see Inertia::shareProvider()
     */
    #[Test]
    public function persistentProviderWithSameKeyIsReplaced(): void
    {
        $inertia = $this->createInertia();
        $calls   = 0;

        // Имитируем регистрацию провайдера на каждом запросе worker'а.
        for ($i = 1; $i <= 3; $i++) {
            $inertia->shareProvider(static function () use (&$calls, $i): array {
                $calls++;

                return ['iteration' => $i];
            }, persistent: true, key: 'iteration');
        }

        $inertia->setRequest($this->createRequest('/'));
        $props = $this->renderProps($inertia);

        self::assertSame(3, $props['iteration']);
        self::assertSame(1, $calls);
    }

    /**
     * Проверим, что повторная регистрация того же callable как persistent-провайдера игнорируется.
     *
     * @see Inertia::shareProvider()
     */
    #[Test]
    public function persistentProviderIsDeduplicated(): void
    {
        $inertia  = $this->createInertia();
        $calls    = 0;
        $provider = static function () use (&$calls): array {
            $calls++;

            return ['locale' => 'ru'];
        };

        $inertia->shareProvider($provider, persistent: true);
        $inertia->shareProvider($provider, persistent: true);

        $inertia->setRequest($this->createRequest('/'));
        $this->renderProps($inertia);

        self::assertSame(1, $calls);
    }

    /**
     * @param array<string, mixed> $shared
     */
    private function createInertia(array $shared = []): Inertia
    {
        $renderer = new class () implements ViewRendererInterface {
            public function render(InertiaPage $inertiaPage): string
            {
                return 'html';
            }
        };

        return new Inertia(
            new InertiaConfig(rootView: __FILE__, shared: $shared),
            $renderer,
            new ResponseFactory(),
            new StreamFactory(),
        );
    }

    private function createRequest(string $path): ServerRequest
    {
        return new ServerRequest('GET', 'https://example.test' . $path, [
            'X-Inertia' => 'true',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function renderProps(Inertia $inertia): array
    {
        $response = $inertia->render('Dashboard');
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $payload['props'];
    }
}
