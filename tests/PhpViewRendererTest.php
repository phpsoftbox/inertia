<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Inertia\InertiaConfig;
use PhpSoftBox\Inertia\InertiaPage;
use PhpSoftBox\Inertia\View\PhpViewRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(PhpViewRenderer::class)]
#[CoversMethod(PhpViewRenderer::class, 'fromConfig')]
#[CoversMethod(PhpViewRenderer::class, 'render')]
final class PhpViewRendererTest extends TestCase
{
    /**
     * Проверим, что fromConfig() использует rootView и rootId из InertiaConfig.
     *
     * @see PhpViewRenderer::fromConfig()
     * @see PhpViewRenderer::render()
     */
    #[Test]
    public function fromConfigUsesRootViewAndRootId(): void
    {
        $viewFile = tempnam(sys_get_temp_dir(), 'inertia-root-');
        file_put_contents($viewFile, '<?= $rootId ?>:<?= $page["component"] ?>:<?= $brand ?>');

        $renderer = PhpViewRenderer::fromConfig(
            new InertiaConfig(rootView: $viewFile, rootId: 'spa'),
            ['brand' => 'Demo'],
        );

        $html = $renderer->render(new InertiaPage('Home', [], '/'));

        unlink($viewFile);

        self::assertSame('spa:Home:Demo', $html);
    }
}
