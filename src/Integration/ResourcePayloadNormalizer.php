<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Integration;

use PhpSoftBox\Inertia\PayloadNormalizerInterface;
use PhpSoftBox\Resource\ResourceSerializerInterface;

/**
 * Адаптирует serializer компонента Resource к общей границе нормализации Inertia.
 *
 * Для использования требуется опциональный пакет phpsoftbox/resource.
 */
final readonly class ResourcePayloadNormalizer implements PayloadNormalizerInterface
{
    public function __construct(
        private ResourceSerializerInterface $serializer,
    ) {
    }

    public function normalize(mixed $value): mixed
    {
        return $this->serializer->serialize($value);
    }
}
