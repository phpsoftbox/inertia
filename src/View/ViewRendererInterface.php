<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\View;

use PhpSoftBox\Inertia\InertiaPage;

interface ViewRendererInterface
{
    public function render(InertiaPage $inertiaPage): string;
}
