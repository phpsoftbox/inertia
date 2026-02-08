<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\StreamFactory;
use PhpSoftBox\Inertia\Area\AreaSharedDataProviderInterface;
use PhpSoftBox\Inertia\Area\AreaSharedDataProviderRegistry;
use PhpSoftBox\Inertia\Area\ConfiguredInertiaAreaDetector;
use PhpSoftBox\Inertia\Area\InertiaAreaConfig;
use PhpSoftBox\Inertia\Inertia;
use PhpSoftBox\Inertia\InertiaConfig;
use PhpSoftBox\Inertia\InertiaPage;
use PhpSoftBox\Inertia\Ssr\SsrRendererInterface;
use PhpSoftBox\Inertia\Ssr\SsrResponse;
use PhpSoftBox\Inertia\View\SsrAwareViewRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Inertia::class)]
#[CoversClass(InertiaConfig::class)]
#[CoversClass(InertiaAreaConfig::class)]
#[CoversClass(ConfiguredInertiaAreaDetector::class)]
#[CoversClass(AreaSharedDataProviderRegistry::class)]
#[CoversMethod(Inertia::class, 'render')]
#[CoversMethod(InertiaConfig::class, 'areas')]
#[CoversMethod(InertiaConfig::class, 'defaultArea')]
#[CoversMethod(ConfiguredInertiaAreaDetector::class, 'detect')]
#[CoversMethod(AreaSharedDataProviderRegistry::class, 'share')]
final class InertiaAreaTest extends TestCase
{
    /**
     * Проверяем, что detector выбирает область по нормализованному host.
     *
     * @see ConfiguredInertiaAreaDetector::detect()
     */
    #[Test]
    public function testAreaDetectorUsesHost(): void
    {
        $detector = new ConfiguredInertiaAreaDetector([
            'web'   => new InertiaAreaConfig(pathPrefixes: ['/']),
            'admin' => new InertiaAreaConfig(hosts: ['https://admin.example.test:8443']),
        ], 'web');

        $area = $detector->detect(new ServerRequest('GET', 'https://admin.example.test/dashboard'));

        $this->assertSame('admin', $area?->name());
    }

    /**
     * Проверяем, что более длинный path prefix приоритетнее общего "/".
     *
     * @see ConfiguredInertiaAreaDetector::detect()
     */
    #[Test]
    public function testAreaDetectorUsesLongestPathPrefix(): void
    {
        $detector = new ConfiguredInertiaAreaDetector([
            'web'   => new InertiaAreaConfig(pathPrefixes: ['/']),
            'admin' => new InertiaAreaConfig(pathPrefixes: ['/admin']),
        ], 'web');

        $area = $detector->detect(new ServerRequest('GET', 'https://example.test/admin/users'));

        $this->assertSame('admin', $area?->name());
    }

    /**
     * Проверяем fallback на default area, если host/path не подошли ни одной области.
     *
     * @see ConfiguredInertiaAreaDetector::detect()
     */
    #[Test]
    public function testAreaDetectorFallsBackToDefaultArea(): void
    {
        $detector = new ConfiguredInertiaAreaDetector([
            'web'   => new InertiaAreaConfig(hosts: ['example.test']),
            'admin' => new InertiaAreaConfig(pathPrefixes: ['/admin']),
        ], 'web');

        $area = $detector->detect(new ServerRequest('GET', 'https://unknown.test/profile'));

        $this->assertSame('web', $area?->name());
    }

    /**
     * Проверяем, что area shared-data и area provider попадают в props.
     *
     * @see AreaSharedDataProviderRegistry::share()
     * @see Inertia::render()
     */
    #[Test]
    public function testAreaSharedDataIsMergedIntoProps(): void
    {
        $inertia = new Inertia(
            new InertiaConfig(
                rootView: __FILE__,
                shared: [
                    'app' => [
                        'name' => 'PhpSoftBox',
                    ],
                ],
                areas: [
                    'default' => 'web',
                    'web'     => new InertiaAreaConfig(pathPrefixes: ['/']),
                    'admin'   => new InertiaAreaConfig(
                        pathPrefixes: ['/admin'],
                        shared: [
                            'app' => [
                                'area' => 'admin',
                            ],
                        ],
                    ),
                ],
            ),
            $this->createRenderer(),
            new ResponseFactory(),
            new StreamFactory(),
            areaSharedDataProviders: new AreaSharedDataProviderRegistry([
                new class () implements AreaSharedDataProviderInterface {
                    public function area(): string
                    {
                        return 'admin';
                    }

                    public function share(ServerRequestInterface $request): array
                    {
                        return [
                            'admin' => [
                                'enabled' => true,
                            ],
                            'app' => [
                                'label' => 'Admin',
                            ],
                        ];
                    }
                },
            ]),
        );

        $inertia->setRequest(new ServerRequest('GET', 'https://example.test/admin', [
            'X-Inertia' => 'true',
        ]));

        $response = $inertia->render('Admin/Dashboard');
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('PhpSoftBox', $payload['props']['app']['name']);
        $this->assertSame('admin', $payload['props']['app']['area']);
        $this->assertSame('Admin', $payload['props']['app']['label']);
        $this->assertTrue($payload['props']['admin']['enabled']);
    }

    /**
     * Проверяем, что area ssr переопределяет базовую SSR-настройку.
     *
     * @see Inertia::render()
     */
    #[Test]
    public function testAreaSsrOverridesBaseConfig(): void
    {
        $ssrRenderer = new class () implements SsrRendererInterface {
            public int $calls = 0;

            public function render(ServerRequestInterface $request, InertiaPage $page): ?SsrResponse
            {
                $this->calls++;

                return new SsrResponse(body: 'ssr-html');
            }
        };

        $inertia = new Inertia(
            new InertiaConfig(
                rootView: __FILE__,
                ssrEnabled: true,
                areas: [
                    'default' => 'web',
                    'web'     => new InertiaAreaConfig(pathPrefixes: ['/'], ssr: true),
                    'admin'   => new InertiaAreaConfig(pathPrefixes: ['/admin'], ssr: false),
                ],
            ),
            $this->createRenderer(),
            new ResponseFactory(),
            new StreamFactory(),
            $ssrRenderer,
        );

        $inertia->setRequest(new ServerRequest('GET', 'https://example.test/admin'));
        $adminResponse = $inertia->render('Admin/Dashboard');

        $this->assertSame(0, $ssrRenderer->calls);
        $this->assertSame('html', (string) $adminResponse->getBody());

        $inertia->setRequest(new ServerRequest('GET', 'https://example.test/'));
        $webResponse = $inertia->render('Home');

        $this->assertSame(1, $ssrRenderer->calls);
        $this->assertSame('ssr-html', (string) $webResponse->getBody());
    }

    private function createRenderer(): SsrAwareViewRendererInterface
    {
        return new class () implements SsrAwareViewRendererInterface {
            public function render(InertiaPage $inertiaPage): string
            {
                return 'html';
            }

            public function renderWithSsr(InertiaPage $inertiaPage, SsrResponse $ssr): string
            {
                return $ssr->body();
            }
        };
    }
}
