<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia;

use Closure;
use PhpSoftBox\Inertia\Ssr\SsrRendererInterface;
use PhpSoftBox\Inertia\View\SsrAwareViewRendererInterface;
use PhpSoftBox\Inertia\View\ViewRendererInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

use function array_replace_recursive;
use function is_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class Inertia
{
    /**
     * @var array<string, mixed>
     */
    private array $shared;
    /**
     * @var array<int, callable(): array<string, mixed>>
     */
    private array $sharedProviders           = [];
    private ?ServerRequestInterface $request = null;

    public function __construct(
        private readonly InertiaConfig $config,
        private readonly ViewRendererInterface $renderer,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?SsrRendererInterface $ssrRenderer = null,
        ?ServerRequestInterface $request = null,
    ) {
        $this->shared  = $config->shared();
        $this->request = $request;
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $props
     */
    public function shareMany(array $props): void
    {
        $this->shared = array_replace_recursive($this->shared, $props);
    }

    /**
     * @param callable(): array<string, mixed> $provider
     */
    public function shareProvider(callable $provider): void
    {
        $this->sharedProviders[] = $provider;
    }

    /**
     * @param array<string, mixed> $props
     */
    public function render(string $component, array $props = []): ResponseInterface
    {
        $request = $this->requireRequest();

        $page = new InertiaPage(
            component: $component,
            props: $this->mergeProps($props),
            url: $this->resolveUrl($request),
            version: $this->config->version(),
        );

        if ($this->isInertiaRequest($request)) {
            $payload  = json_encode($page->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $response = $this->responseFactory->createResponse(200)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('X-Inertia', 'true');

            return $response->withBody($this->streamFactory->createStream($payload));
        }

        $ssr = null;
        if ($this->config->ssrEnabled() && $this->ssrRenderer !== null) {
            $ssr = $this->ssrRenderer->render($request, $page);
        }

        if ($ssr !== null && $this->renderer instanceof SsrAwareViewRendererInterface) {
            $payload = $this->renderer->renderWithSsr($page, $ssr);
        } else {
            $payload = $this->renderer->render($page);
        }
        $response = $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');

        return $response->withBody($this->streamFactory->createStream($payload));
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    private function mergeProps(array $props): array
    {
        $shared = $this->resolveProps($this->resolveShared());
        $props  = $this->resolveProps($props);

        return array_replace_recursive($shared, $props);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveShared(): array
    {
        $shared = $this->shared;

        foreach ($this->sharedProviders as $provider) {
            $provided = $provider();
            if ($provided !== []) {
                $shared = array_replace_recursive($shared, $provided);
            }
        }

        return $shared;
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    private function resolveProps(array $props): array
    {
        foreach ($props as $key => $value) {
            if ($value instanceof Closure) {
                $value = $value();
            }

            if (is_array($value)) {
                $value = $this->resolveProps($value);
            }

            $props[$key] = $value;
        }

        return $props;
    }

    private function isInertiaRequest(ServerRequestInterface $request): bool
    {
        return $request->getHeaderLine('X-Inertia') !== '';
    }

    private function resolveUrl(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $url = $uri->getPath();

        $query = $uri->getQuery();
        if ($query !== '') {
            $url .= '?' . $query;
        }

        return $url === '' ? '/' : $url;
    }

    private function requireRequest(): ServerRequestInterface
    {
        if ($this->request === null) {
            throw new RuntimeException('Inertia request is not set.');
        }

        return $this->request;
    }
}
