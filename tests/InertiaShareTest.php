<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\StreamFactory;
use PhpSoftBox\Inertia\Inertia;
use PhpSoftBox\Inertia\InertiaConfig;
use PhpSoftBox\Inertia\InertiaPage;
use PhpSoftBox\Inertia\Middleware\InertiaShareMiddleware;
use PhpSoftBox\Inertia\Page\Tab;
use PhpSoftBox\Inertia\Page\Tabs;
use PhpSoftBox\Inertia\Share\InertiaBaseDataProvider;
use PhpSoftBox\Inertia\Share\SharedDataProviderInterface;
use PhpSoftBox\Inertia\View\ViewRendererInterface;
use PhpSoftBox\Session\ArraySessionStore;
use PhpSoftBox\Session\Session;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(InertiaBaseDataProvider::class)]
#[CoversClass(InertiaShareMiddleware::class)]
#[CoversClass(Inertia::class)]
#[CoversClass(Tabs::class)]
#[CoversMethod(InertiaBaseDataProvider::class, 'share')]
#[CoversMethod(InertiaShareMiddleware::class, 'process')]
#[CoversMethod(Inertia::class, 'render')]
#[CoversMethod(Inertia::class, 'setRequest')]
#[CoversMethod(Inertia::class, 'shareProvider')]
#[CoversMethod(Tabs::class, 'toArray')]
final class InertiaShareTest extends TestCase
{
    /**
     * Проверяет, что базовый провайдер возвращает данные пользователя.
     *
     * @see InertiaBaseDataProvider::share()
     */
    #[Test]
    public function testBaseProviderSharesUser(): void
    {
        $provider = new InertiaBaseDataProvider();
        $request  = new ServerRequest('GET', 'https://example.test/')
            ->withAttribute('user', ['id' => 10]);

        $shared = $provider->share($request);

        $this->assertSame(['id' => 10], $shared['auth']['user']);
    }

    /**
     * Проверяет, что middleware добавляет общие пропсы Inertia.
     *
     * @see InertiaShareMiddleware::process()
     * @see Inertia::render()
     */
    #[Test]
    public function testShareMiddlewareAddsProps(): void
    {
        $inertia = new Inertia(
            new InertiaConfig(rootView: __FILE__),
            $this->createRenderer(),
            new ResponseFactory(),
            new StreamFactory(),
        );

        $provider = new class () implements SharedDataProviderInterface {
            public function share(ServerRequestInterface $request): array
            {
                return [
                    'app' => [
                        'area' => 'admin',
                    ],
                ];
            }
        };

        $middleware = new InertiaShareMiddleware($inertia, $provider);

        $handler = new class ($inertia) implements RequestHandlerInterface {
            public function __construct(
                private readonly Inertia $inertia,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->inertia->render('Dashboard', [
                    'title' => 'Panel',
                ]);
            }
        };

        $request = new ServerRequest('GET', 'https://example.test/admin', [
            'X-Inertia' => 'true',
        ]);

        $response = $middleware->process($request, $handler);
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Panel', $payload['props']['title']);
        $this->assertSame('admin', $payload['props']['app']['area']);
    }

    /**
     * Проверяет, что ленивые провайдеры shared-данных вычисляются при рендере.
     *
     * @see Inertia::shareProvider()
     * @see Inertia::setRequest()
     * @see Inertia::render()
     */
    #[Test]
    public function testShareProviderResolvesOnRender(): void
    {
        $inertia = new Inertia(
            new InertiaConfig(rootView: __FILE__),
            $this->createRenderer(),
            new ResponseFactory(),
            new StreamFactory(),
        );

        $value = 'initial';
        $inertia->shareProvider(static function () use (&$value): array {
            return ['meta' => ['value' => $value]];
        });
        $value = 'final';

        $request = new ServerRequest('GET', 'https://example.test/admin', [
            'X-Inertia' => 'true',
        ]);

        $inertia->setRequest($request);

        $response = $inertia->render('Dashboard', [
            'title' => 'Panel',
        ]);

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Panel', $payload['props']['title']);
        $this->assertSame('final', $payload['props']['meta']['value']);
    }

    /**
     * Проверяет, что request-scoped provider не протекает в следующий запрос.
     *
     * @see Inertia::shareProvider()
     * @see Inertia::setRequest()
     * @see Inertia::render()
     */
    #[Test]
    public function testRequestScopedProviderDoesNotLeakBetweenRequests(): void
    {
        $inertia = new Inertia(
            new InertiaConfig(rootView: __FILE__),
            $this->createRenderer(),
            new ResponseFactory(),
            new StreamFactory(),
        );

        $requestA = new ServerRequest('GET', 'https://example.test/first', [
            'X-Inertia' => 'true',
        ]);
        $requestB = new ServerRequest('GET', 'https://example.test/second', [
            'X-Inertia' => 'true',
        ]);

        $inertia->setRequest($requestA);
        $inertia->shareProvider(static fn (): array => ['meta' => ['scope' => 'request-a']], persistent: false);

        $responseA = $inertia->render('Dashboard');
        $payloadA  = json_decode((string) $responseA->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('request-a', $payloadA['props']['meta']['scope'] ?? null);

        $inertia->setRequest($requestB);

        $responseB = $inertia->render('Dashboard');
        $payloadB  = json_decode((string) $responseB->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('meta', $payloadB['props']);
    }

    /**
     * Проверяет передачу ошибок и flash-сообщений через базовый провайдер.
     *
     * @see InertiaBaseDataProvider::share()
     */
    #[Test]
    public function testBaseProviderSharesErrorsAndFlash(): void
    {
        $session = new Session(new ArraySessionStore());

        $session->start();
        $session->flash('errors', ['login' => 'Поле login не должно быть пустым.']);
        $session->flash('success', 'Готово');
        $session->flash('info', 'Инфо');

        $provider = new InertiaBaseDataProvider($session);
        $request  = new ServerRequest('GET', 'https://example.test/');

        $shared = $provider->share($request);

        $this->assertSame(['login' => 'Поле login не должно быть пустым.'], $shared['errors']);
        $this->assertSame(['success' => 'Готово', 'info' => 'Инфо'], $shared['flash']);
    }

    /**
     * Проверяет, что базовый провайдер отдаёт табы страницы.
     *
     * @see InertiaBaseDataProvider::share()
     * @see Tabs::toArray()
     */
    #[Test]
    public function testBaseProviderSharesTabs(): void
    {
        $tabs = new Tabs('profile', [
            new Tab('Профиль', 'profile', '/profile'),
            new Tab('Пароль', 'password', '/profile/password'),
        ]);

        $provider = new InertiaBaseDataProvider(null, null, null, $tabs);
        $request  = new ServerRequest('GET', 'https://example.test/profile');

        $shared = $provider->share($request);

        $this->assertSame('profile', $shared['tabs']['activeKey']);
        $this->assertSame('Профиль', $shared['tabs']['tabs'][0]['label']);
        $this->assertSame('/profile/password', $shared['tabs']['tabs'][1]['href']);
    }

    private function createRenderer(): ViewRendererInterface
    {
        return new class () implements ViewRendererInterface {
            public function render(InertiaPage $page): string
            {
                return 'html';
            }
        };
    }
}
