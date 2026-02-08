<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Area;

use Psr\Http\Message\ServerRequestInterface;

use function array_replace_recursive;

final class AreaSharedDataProviderRegistry
{
    /**
     * @var list<AreaSharedDataProviderInterface>
     */
    private array $providers = [];

    /**
     * @param iterable<AreaSharedDataProviderInterface> $providers
     */
    public function __construct(iterable $providers = [])
    {
        foreach ($providers as $provider) {
            $this->providers[] = $provider;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function share(string $area, ServerRequestInterface $request): array
    {
        $shared = [];

        foreach ($this->providers as $provider) {
            if ($provider->area() !== $area) {
                continue;
            }

            $shared = array_replace_recursive($shared, $provider->share($request));
        }

        return $shared;
    }
}
