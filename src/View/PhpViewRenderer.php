<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\View;

use PhpSoftBox\Inertia\InertiaPage;
use PhpSoftBox\Inertia\Ssr\SsrResponse;
use PhpSoftBox\View\PhpViewRenderer as BaseViewRenderer;
use RuntimeException;

use function is_file;

final readonly class PhpViewRenderer implements SsrAwareViewRendererInterface
{
    /**
     * @param array<string, mixed> $sharedData
     */
    public function __construct(
        private string $viewPath,
        private string $rootId = 'app',
        private array $sharedData = [],
    ) {
    }

    public function render(InertiaPage $inertiaPage): string
    {
        if (!is_file($this->viewPath)) {
            throw new RuntimeException('Inertia view file not found: ' . $this->viewPath);
        }

        $renderer = new BaseViewRenderer(sharedData: $this->sharedData);

        return $renderer->render($this->viewPath, [
            'page'   => $inertiaPage->toArray(),
            'rootId' => $this->rootId,
        ]);
    }

    public function renderWithSsr(InertiaPage $inertiaPage, SsrResponse $ssr): string
    {
        if (!is_file($this->viewPath)) {
            throw new RuntimeException('Inertia view file not found: ' . $this->viewPath);
        }

        $renderer = new BaseViewRenderer(sharedData: $this->sharedData);

        return $renderer->render($this->viewPath, [
            'page'   => $inertiaPage->toArray(),
            'rootId' => $this->rootId,
            'ssr'    => [
                'head' => $ssr->head(),
                'body' => $ssr->body(),
            ],
        ]);
    }
}
