<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\StreamFactory;
use PhpSoftBox\Inertia\Inertia;
use PhpSoftBox\Inertia\InertiaConfig;
use PhpSoftBox\Inertia\InertiaPage;
use PhpSoftBox\Inertia\PayloadNormalizerInterface;
use PhpSoftBox\Inertia\View\PhpViewRenderer;
use PhpSoftBox\Inertia\View\ViewRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_array;
use function json_decode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Inertia::class)]
#[CoversClass(InertiaConfig::class)]
#[CoversClass(PhpViewRenderer::class)]
#[CoversMethod(Inertia::class, 'render')]
#[CoversMethod(PhpViewRenderer::class, 'render')]
final class InertiaTest extends TestCase
{
    /**
     * Проверяем JSON-ответ для X-Inertia request.
     *
     * @see Inertia::render()
     */
    #[Test]
    public function testRenderJsonForInertiaRequest(): void
    {
        $renderer = $this->createRenderer();
        $request  = new ServerRequest('GET', 'https://example.test/dashboard', [
            'X-Inertia' => 'true',
        ]);

        $inertia = new Inertia(
            new InertiaConfig(rootView: __FILE__),
            $renderer,
            new ResponseFactory(),
            new StreamFactory(),
            null,
            $request,
        );

        $response = $inertia->render('Dashboard', ['title' => 'Test']);

        $this->assertSame('true', $response->getHeaderLine('X-Inertia'));
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Dashboard', $payload['component']);
        $this->assertSame('Test', $payload['props']['title']);
    }

    /**
     * Проверяем HTML-ответ для первого посещения страницы.
     *
     * @see Inertia::render()
     * @see PhpViewRenderer::render()
     */
    #[Test]
    public function testRenderHtmlForFirstVisit(): void
    {
        $viewFile = tempnam(sys_get_temp_dir(), 'inertia-view-');
        file_put_contents($viewFile, '<div id="<?= $rootId ?>"><?= $page["component"] ?></div>');

        $renderer = new PhpViewRenderer($viewFile, 'app');

        $request = new ServerRequest('GET', 'https://example.test/');

        $inertia = new Inertia(
            new InertiaConfig(rootView: $viewFile),
            $renderer,
            new ResponseFactory(),
            new StreamFactory(),
            null,
            $request,
        );

        $response = $inertia->render('Home', []);

        $this->assertStringContainsString('Home', (string) $response->getBody());

        unlink($viewFile);
    }

    /**
     * Проверяем, что partial reload по only возвращает только запрошенные props.
     *
     * @see Inertia::render()
     */
    #[Test]
    public function testRenderPartialReloadOnlyProps(): void
    {
        $expensiveResolved = false;
        $request           = new ServerRequest('GET', 'https://example.test/dashboard', [
            'X-Inertia'                   => 'true',
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data'      => 'stats',
        ]);

        $inertia = new Inertia(
            new InertiaConfig(
                rootView: __FILE__,
                shared: [
                    'app' => [
                        'name' => 'PhpSoftBox',
                    ],
                ],
            ),
            $this->createRenderer(),
            new ResponseFactory(),
            new StreamFactory(),
            null,
            $request,
        );

        $response = $inertia->render('Dashboard', [
            'title' => static function () use (&$expensiveResolved): string {
                $expensiveResolved = true;

                return 'Dashboard';
            },
            'stats' => static fn (): array => [
                'users' => 10,
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['users' => 10], $payload['props']['stats']);
        $this->assertArrayNotHasKey('title', $payload['props']);
        $this->assertArrayNotHasKey('app', $payload['props']);
        $this->assertFalse($expensiveResolved);
    }

    /**
     * Проверяем, что partial reload по except приоритетнее only.
     *
     * @see Inertia::render()
     */
    #[Test]
    public function testRenderPartialReloadExceptProps(): void
    {
        $request = new ServerRequest('GET', 'https://example.test/dashboard', [
            'X-Inertia'                   => 'true',
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data'      => 'stats,meta',
            'X-Inertia-Partial-Except'    => 'meta',
        ]);

        $inertia = new Inertia(
            new InertiaConfig(rootView: __FILE__),
            $this->createRenderer(),
            new ResponseFactory(),
            new StreamFactory(),
            null,
            $request,
        );

        $response = $inertia->render('Dashboard', [
            'title' => 'Dashboard',
            'stats' => [
                'users' => 10,
            ],
            'meta' => [
                'total' => 100,
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Dashboard', $payload['props']['title']);
        $this->assertSame(['users' => 10], $payload['props']['stats']);
        $this->assertArrayNotHasKey('meta', $payload['props']);
    }

    /**
     * Проверяем нормализацию обычных, shared и вычисляемых props.
     */
    #[Test]
    public function testPayloadNormalizerReceivesResolvedSelectedProps(): void
    {
        $request = new ServerRequest('GET', 'https://example.test/dashboard', [
            'X-Inertia' => 'true',
        ]);

        $normalizer = new class () implements PayloadNormalizerInterface {
            public function normalize(mixed $value): mixed
            {
                return $this->walk($value);
            }

            private function walk(mixed $value): mixed
            {
                if ($value instanceof NormalizableValue) {
                    return ['normalized' => $value->value];
                }

                if (is_array($value)) {
                    foreach ($value as $key => $item) {
                        $value[$key] = $this->walk($item);
                    }
                }

                return $value;
            }
        };

        $inertia = new Inertia(
            new InertiaConfig(
                rootView: __FILE__,
                shared: ['shared' => new NormalizableValue('shared')],
            ),
            $this->createRenderer(),
            new ResponseFactory(),
            new StreamFactory(),
            null,
            $request,
            payloadNormalizer: $normalizer,
        );

        $response = $inertia->render('Dashboard', [
            'direct' => new NormalizableValue('direct'),
            'lazy'   => static fn (): NormalizableValue => new NormalizableValue('lazy'),
        ]);

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['normalized' => 'shared'], $payload['props']['shared']);
        self::assertSame(['normalized' => 'direct'], $payload['props']['direct']);
        self::assertSame(['normalized' => 'lazy'], $payload['props']['lazy']);
    }

    /**
     * Проверяем, что исключённые partial reload props не вычисляются и не нормализуются.
     */
    #[Test]
    public function testPartialReloadFiltersPropsBeforeNormalization(): void
    {
        $excludedResolved = false;
        $request          = new ServerRequest('GET', 'https://example.test/dashboard', [
            'X-Inertia'                   => 'true',
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data'      => 'included',
        ]);

        $normalizer = new class () implements PayloadNormalizerInterface {
            /** @var list<string> */
            public array $normalized = [];

            public function normalize(mixed $value): mixed
            {
                foreach ($value as $item) {
                    if ($item instanceof NormalizableValue) {
                        $this->normalized[] = $item->value;
                    }
                }

                return $value;
            }
        };

        $inertia = new Inertia(
            new InertiaConfig(rootView: __FILE__),
            $this->createRenderer(),
            new ResponseFactory(),
            new StreamFactory(),
            null,
            $request,
            payloadNormalizer: $normalizer,
        );

        $inertia->render('Dashboard', [
            'included' => new NormalizableValue('included'),
            'excluded' => static function () use (&$excludedResolved): NormalizableValue {
                $excludedResolved = true;

                return new NormalizableValue('excluded');
            },
        ]);

        self::assertFalse($excludedResolved);
        self::assertSame(['included'], $normalizer->normalized);
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

final readonly class NormalizableValue
{
    public function __construct(
        public string $value,
    ) {
    }
}
