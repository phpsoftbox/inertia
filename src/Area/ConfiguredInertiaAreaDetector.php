<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Area;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function explode;
use function is_string;
use function max;
use function parse_url;
use function preg_replace;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function trim;

use const PHP_URL_HOST;

final class ConfiguredInertiaAreaDetector implements InertiaAreaDetectorInterface
{
    /**
     * @param array<string, InertiaAreaConfig> $areas
     */
    public function __construct(
        private readonly array $areas,
        private readonly ?string $defaultArea = null,
    ) {
        foreach ($this->areas as $name => $config) {
            if (!$config instanceof InertiaAreaConfig) {
                throw new InvalidArgumentException("Inertia area config must be an instance of InertiaAreaConfig: {$name}");
            }
        }

        if ($this->defaultArea !== null && !array_key_exists($this->defaultArea, $this->areas)) {
            throw new InvalidArgumentException("Default Inertia area is not configured: {$this->defaultArea}");
        }
    }

    public function detect(ServerRequestInterface $request): ?InertiaArea
    {
        $host = $this->normalizeHost($request->getUri()->getHost());
        $path = $this->normalizePath($request->getUri()->getPath());

        $matchedArea  = null;
        $matchedScore = 0;

        foreach ($this->areas as $name => $config) {
            $score = $this->matchScore($config, $host, $path);
            if ($score <= $matchedScore) {
                continue;
            }

            $matchedArea  = new InertiaArea((string) $name, $config);
            $matchedScore = $score;
        }

        if ($matchedArea !== null) {
            return $matchedArea;
        }

        if ($this->defaultArea !== null) {
            return new InertiaArea($this->defaultArea, $this->areas[$this->defaultArea]);
        }

        return null;
    }

    private function matchScore(InertiaAreaConfig $config, string $host, string $path): int
    {
        $score = 0;

        foreach ($config->hosts() as $configuredHost) {
            if ($host !== '' && $host === $this->normalizeHost($configuredHost)) {
                $score = 100_000;
                break;
            }
        }

        foreach ($config->paths() as $configuredPath) {
            $configuredPath = $this->normalizePath($configuredPath);
            if ($path === $configuredPath) {
                $score = max($score, 50_000 + strlen($configuredPath));
            }
        }

        foreach ($config->pathPrefixes() as $prefix) {
            $prefix = $this->normalizePath($prefix);
            if ($prefix === '/' || $path === $prefix || str_starts_with($path, $prefix . '/')) {
                $score = max($score, 10_000 + strlen($prefix));
            }
        }

        return $score;
    }

    private function normalizeHost(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parsed = parse_url($value, PHP_URL_HOST);
        if (is_string($parsed) && trim($parsed) !== '') {
            return strtolower(trim($parsed));
        }

        $normalized = strtolower(trim($value));
        if (str_contains($normalized, '/')) {
            $normalized = explode('/', $normalized, 2)[0] ?? '';
        }

        $withoutPort = preg_replace('/:\d+$/', '', $normalized);
        if (!is_string($withoutPort)) {
            return '';
        }

        return trim($withoutPort);
    }

    private function normalizePath(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '/';
        }

        if (!str_starts_with($value, '/')) {
            $value = '/' . $value;
        }

        if ($value === '/') {
            return '/';
        }

        return '/' . trim($value, '/');
    }
}
