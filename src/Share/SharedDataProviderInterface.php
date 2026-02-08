<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Share;

use Psr\Http\Message\ServerRequestInterface;

interface SharedDataProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function share(ServerRequestInterface $request): array;
}
