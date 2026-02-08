<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\View;

use PhpSoftBox\Inertia\InertiaPage;
use PhpSoftBox\Inertia\Ssr\SsrResponse;

interface SsrAwareViewRendererInterface extends ViewRendererInterface
{
    public function renderWithSsr(InertiaPage $inertiaPage, SsrResponse $ssr): string;
}
