<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Middleware;

use PhpSoftBox\Inertia\Inertia;
use PhpSoftBox\Inertia\Share\SharedDataProviderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class InertiaShareMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Inertia $inertia,
        private readonly SharedDataProviderInterface $provider,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->inertia->setRequest($request);
        $this->inertia->shareProvider(fn (): array => $this->provider->share($request));

        return $handler->handle($request);
    }
}
