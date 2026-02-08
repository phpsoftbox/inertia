<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Area;

use Psr\Http\Message\ServerRequestInterface;

interface AreaSharedDataProviderInterface
{
    public function area(): string;

    /**
     * @return array<string, mixed>
     */
    public function share(ServerRequestInterface $request): array;
}
