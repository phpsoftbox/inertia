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

use function json_decode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Inertia::class)]
#[CoversMethod(Inertia::class, 'render')]
final class InertiaPropsMergeTest extends TestCase
{
    /**
     * Проверим, что список из props целиком заменяет shared-список, а не сливается по индексам.
     *
     * @see Inertia::render()
     */
    #[Test]
    public function listFromPropsReplacesSharedList(): void
    {
        $inertia = $this->createInertia(['menu' => ['items' => ['a', 'b', 'c']]]);

        $props = $this->renderProps($inertia, ['menu' => ['items' => ['x']]]);

        self::assertSame(['items' => ['x']], $props['menu']);
    }

    /**
     * Проверим, что ассоциативные массивы props и shared-данных по-прежнему сливаются рекурсивно.
     *
     * @see Inertia::render()
     */
    #[Test]
    public function associativePropsAreMergedRecursively(): void
    {
        $inertia = $this->createInertia(['app' => ['name' => 'Demo', 'area' => 'web']]);

        $props = $this->renderProps($inertia, ['app' => ['area' => 'admin']]);

        self::assertSame(['name' => 'Demo', 'area' => 'admin'], $props['app']);
    }

    /**
     * @param array<string, mixed> $shared
     */
    private function createInertia(array $shared): Inertia
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
            request: new ServerRequest('GET', 'https://example.test/', ['X-Inertia' => 'true']),
        );
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    private function renderProps(Inertia $inertia, array $props): array
    {
        $response = $inertia->render('Dashboard', $props);
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $payload['props'];
    }
}
