<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Middleware;

use PhpSoftBox\Inertia\InertiaConfig;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function stripos;

final class InertiaMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly InertiaConfig $config,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isVersionMismatch($request)) {
            return $this->locationResponse($request);
        }

        $response = $handler->handle($request);

        return $this->withVaryHeader($response);
    }

    private function isVersionMismatch(ServerRequestInterface $request): bool
    {
        if ($request->getMethod() !== 'GET') {
            return false;
        }

        if ($request->getHeaderLine('X-Inertia') === '') {
            return false;
        }

        $clientVersion = $request->getHeaderLine('X-Inertia-Version');
        $serverVersion = $this->config->version();

        if ($clientVersion === '' || $serverVersion === null) {
            return false;
        }

        return $clientVersion !== $serverVersion;
    }

    private function locationResponse(ServerRequestInterface $request): ResponseInterface
    {
        $location = (string) $request->getUri();

        return $this->responseFactory
            ->createResponse(409)
            ->withHeader('X-Inertia-Location', $location)
            ->withHeader('Location', $location);
    }

    private function withVaryHeader(ResponseInterface $response): ResponseInterface
    {
        $vary = $response->getHeaderLine('Vary');
        if ($vary === '') {
            return $response->withHeader('Vary', 'X-Inertia');
        }

        if (stripos($vary, 'X-Inertia') !== false) {
            return $response;
        }

        return $response->withHeader('Vary', $vary . ', X-Inertia');
    }
}
