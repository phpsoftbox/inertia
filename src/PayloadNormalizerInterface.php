<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia;

interface PayloadNormalizerInterface
{
    public function normalize(mixed $value): mixed;
}
