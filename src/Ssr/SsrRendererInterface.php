<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Ssr;

use PhpSoftBox\Inertia\InertiaPage;
use Psr\Http\Message\ServerRequestInterface;

interface SsrRendererInterface
{
    public function render(ServerRequestInterface $request, InertiaPage $page): ?SsrResponse;
}
