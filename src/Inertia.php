<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia;

use Closure;
use PhpSoftBox\Inertia\Area\AreaSharedDataProviderRegistry;
use PhpSoftBox\Inertia\Area\ConfiguredInertiaAreaDetector;
use PhpSoftBox\Inertia\Area\InertiaArea;
use PhpSoftBox\Inertia\Area\InertiaAreaDetectorInterface;
use PhpSoftBox\Inertia\Ssr\SsrRendererInterface;
use PhpSoftBox\Inertia\View\SsrAwareViewRendererInterface;
use PhpSoftBox\Inertia\View\ViewRendererInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

use function array_diff_key;
use function array_intersect_key;
use function array_is_list;
use function explode;
use function is_array;
use function json_encode;
use function trim;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class Inertia
{
    /**
     * Базовые shared-данные уровня приложения (из InertiaConfig), не меняются между запросами.
     *
     * @var array<string, mixed>
     */
    private readonly array $baseShared;
    /**
     * Shared-данные текущего запроса (share()/shareMany()), сбрасываются в setRequest()/reset().
     *
     * @var array<string, mixed>
     */
    private array $shared = [];
    /**
     * Persistent-провайдеры уровня приложения: ключ провайдера => провайдер.
     *
     * @var array<string|int, callable(): array<string, mixed>>
     */
    private array $sharedProviders = [];
    /**
     * Провайдеры текущего запроса, сбрасываются в setRequest()/reset().
     *
     * @var array<string|int, callable(): array<string, mixed>>
     */
    private array $requestSharedProviders    = [];
    private ?ServerRequestInterface $request = null;
    private readonly ?InertiaAreaDetectorInterface $areaDetector;
    private readonly AreaSharedDataProviderRegistry $areaSharedDataProviders;

    public function __construct(
        private readonly InertiaConfig $config,
        private readonly ViewRendererInterface $renderer,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?SsrRendererInterface $ssrRenderer = null,
        ?ServerRequestInterface $request = null,
        ?InertiaAreaDetectorInterface $areaDetector = null,
        ?AreaSharedDataProviderRegistry $areaSharedDataProviders = null,
        private readonly ?PayloadNormalizerInterface $payloadNormalizer = null,
    ) {
        $this->baseShared              = $config->shared();
        $this->request                 = $request;
        $this->areaDetector            = $areaDetector ?? $this->createConfiguredAreaDetector($config);
        $this->areaSharedDataProviders = $areaSharedDataProviders ?? new AreaSharedDataProviderRegistry();
    }

    /**
     * Добавляет shared-prop текущего запроса. Значение сбрасывается в setRequest() и reset(),
     * поэтому не попадает в следующий запрос worker'а. Данные уровня приложения задавайте
     * через `InertiaConfig::$shared` или persistent-провайдер.
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Добавляет несколько shared-props текущего запроса (сбрасываются в setRequest() и reset()).
     *
     * @param array<string, mixed> $props
     */
    public function shareMany(array $props): void
    {
        $this->shared = self::mergeProps($this->shared, $props);
    }

    /**
     * Регистрирует ленивый провайдер shared-данных.
     *
     * По умолчанию провайдер относится к текущему запросу и сбрасывается в setRequest()/reset().
     * Persistent-провайдер (`persistent: true`) живёт весь срок жизни сервиса — регистрируйте его
     * только при сборке приложения. Повторная регистрация того же callable игнорируется, а провайдер
     * с тем же `$key` заменяет ранее зарегистрированный — список провайдеров не растёт от запроса к запросу.
     *
     * @param callable(): array<string, mixed> $provider
     */
    public function shareProvider(callable $provider, bool $persistent = false, ?string $key = null): void
    {
        if ($persistent) {
            $this->sharedProviders = self::addProvider($this->sharedProviders, $provider, $key);

            return;
        }

        $this->requestSharedProviders = self::addProvider($this->requestSharedProviders, $provider, $key);
    }

    /**
     * @param array<string, mixed> $props
     */
    public function render(string $component, array $props = []): ResponseInterface
    {
        $request = $this->requireRequest();
        $area    = $this->areaDetector?->detect($request);

        $page = new InertiaPage(
            component: $component,
            props: $this->buildProps($props, $request, $area, $component),
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
        if ($this->ssrEnabled($area) && $this->ssrRenderer !== null) {
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

    /**
     * Начинает новый запрос: сбрасывает данные предыдущего запроса (см. reset()) и запоминает текущий.
     */
    public function setRequest(ServerRequestInterface $request): void
    {
        $this->reset();
        $this->request = $request;
    }

    /**
     * Сбрасывает состояние запроса: текущий request, shared-данные из share()/shareMany() и
     * request-scoped провайдеры. Базовые shared-данные из конфига и persistent-провайдеры сохраняются.
     *
     * Вызывается на границе запроса в долгоживущих worker'ах (например, через ServicesResetter
     * с маппингом `Inertia::class => 'reset'`).
     */
    public function reset(): void
    {
        $this->request                = null;
        $this->shared                 = [];
        $this->requestSharedProviders = [];
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    private function buildProps(array $props, ServerRequestInterface $request, ?InertiaArea $area, string $component): array
    {
        $shared = $this->resolveShared($request, $area);
        $props  = self::mergeProps($shared, $props);
        $props  = $this->filterPartialProps($component, $props, $request);
        $props  = $this->resolveProps($props);

        if ($this->payloadNormalizer !== null) {
            $props = $this->payloadNormalizer->normalize($props);
        }

        return $props;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveShared(ServerRequestInterface $request, ?InertiaArea $area): array
    {
        // Данные share()/shareMany() накладываются на базовые данные конфига, провайдеры применяются позже.
        $shared = self::mergeProps($this->baseShared, $this->shared);

        if ($area !== null && $area->config()->shared() !== []) {
            $shared = self::mergeProps($shared, $area->config()->shared());
        }

        foreach ($this->sharedProviders as $provider) {
            $provided = $provider();
            if ($provided !== []) {
                $shared = self::mergeProps($shared, $provided);
            }
        }

        foreach ($this->requestSharedProviders as $provider) {
            $provided = $provider();
            if ($provided !== []) {
                $shared = self::mergeProps($shared, $provided);
            }
        }

        if ($area !== null) {
            $provided = $this->areaSharedDataProviders->share($area->name(), $request);
            if ($provided !== []) {
                $shared = self::mergeProps($shared, $provided);
            }
        }

        return $shared;
    }

    /**
     * Рекурсивно сливает props: ассоциативные массивы объединяются по ключам, а списки
     * (и скаляры) из `$override` целиком заменяют значение из `$base` — без слияния по индексам.
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $override
     * @return array<array-key, mixed>
     */
    private static function mergeProps(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $current = $base[$key] ?? null;

            if (
                is_array($value)
                && is_array($current)
                && !array_is_list($value)
                && !array_is_list($current)
            ) {
                $base[$key] = self::mergeProps($current, $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param array<string|int, callable(): array<string, mixed>> $providers
     * @param callable(): array<string, mixed> $provider
     * @return array<string|int, callable(): array<string, mixed>>
     */
    private static function addProvider(array $providers, callable $provider, ?string $key): array
    {
        if ($key !== null) {
            // Префикс исключает смешение с автоматическими числовыми ключами.
            $providers['key:' . $key] = $provider;

            return $providers;
        }

        foreach ($providers as $registered) {
            if ($registered === $provider) {
                return $providers;
            }
        }

        $providers[] = $provider;

        return $providers;
    }

    private function ssrEnabled(?InertiaArea $area): bool
    {
        return $area?->config()->ssr() ?? $this->config->ssrEnabled();
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    private function filterPartialProps(string $component, array $props, ServerRequestInterface $request): array
    {
        if (!$this->isPartialReload($component, $request)) {
            return $props;
        }

        $except = $this->headerKeys($request->getHeaderLine('X-Inertia-Partial-Except'));
        if ($except !== []) {
            return array_diff_key($props, $except);
        }

        $only = $this->headerKeys($request->getHeaderLine('X-Inertia-Partial-Data'));
        if ($only === []) {
            return $props;
        }

        return array_intersect_key($props, $only);
    }

    private function isPartialReload(string $component, ServerRequestInterface $request): bool
    {
        if ($request->getHeaderLine('X-Inertia') === '') {
            return false;
        }

        return $request->getHeaderLine('X-Inertia-Partial-Component') === $component;
    }

    /**
     * @return array<string, bool>
     */
    private function headerKeys(string $header): array
    {
        $keys = [];

        foreach (explode(',', $header) as $key) {
            $key = trim($key);
            if ($key !== '') {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    private function createConfiguredAreaDetector(InertiaConfig $config): ?InertiaAreaDetectorInterface
    {
        if ($config->areas() === []) {
            return null;
        }

        return new ConfiguredInertiaAreaDetector($config->areas(), $config->defaultArea());
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
