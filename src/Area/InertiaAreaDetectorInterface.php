<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Area;

use Psr\Http\Message\ServerRequestInterface;

interface InertiaAreaDetectorInterface
{
    public function detect(ServerRequestInterface $request): ?InertiaArea;
}
