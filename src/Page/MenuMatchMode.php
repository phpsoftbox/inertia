<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Page;

enum MenuMatchMode: string
{
    case EQUALS      = 'equals';
    case STARTS_WITH = 'startsWith';
}
