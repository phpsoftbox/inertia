<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\StreamFactory;
use PhpSoftBox\Inertia\Inertia;
use PhpSoftBox\Inertia\InertiaConfig;
use PhpSoftBox\Inertia\View\PhpViewRenderer;
use PhpSoftBox\Inertia\View\ViewRendererInterface;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function json_decode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class InertiaTest extends TestCase
{
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

    private function createRenderer(): ViewRendererInterface
    {
        return new class () implements ViewRendererInterface {
            public function render(\PhpSoftBox\Inertia\InertiaPage $page): string
            {
                return 'html';
            }
        };
    }
}
