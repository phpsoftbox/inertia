<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\View;

use function htmlspecialchars;
use function json_encode;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Безопасная вставка Inertia page в HTML root-view.
 *
 * JSON кодируется с `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`: символы `<`, `>`, `&`,
 * `'`, `"` превращаются в `<` и т.п., поэтому строка `</script>` в props не закрывает тег, а значение
 * безопасно и внутри `<script type="application/json">`, и (после htmlspecialchars) в атрибуте.
 */
final class InertiaHtml
{
    /**
     * JSON page для вставки внутрь `<script type="application/json">` без дополнительного экранирования.
     *
     * @param array<string, mixed> $page
     */
    public static function pageJson(array $page): string
    {
        return json_encode(
            $page,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT,
        );
    }

    /**
     * JSON page, экранированный для значения HTML-атрибута (`data-page="..."`).
     *
     * @param array<string, mixed> $page
     */
    public static function pageAttribute(array $page): string
    {
        return self::escape(self::pageJson($page));
    }

    /**
     * Root-разметка Inertia: `<script data-page="{rootId}" type="application/json">` с page JSON
     * (формат Inertia 2+) и `<div id="{rootId}" data-page="...">` с SSR-разметкой (совместимость
     * с клиентами, читающими атрибут `data-page`).
     *
     * @param array<string, mixed> $page
     * @param string $ssrBody Доверенная HTML-разметка SSR-сервера; вставляется без экранирования.
     */
    public static function root(array $page, string $rootId = 'app', string $ssrBody = ''): string
    {
        $id   = self::escape($rootId);
        $json = self::pageJson($page);

        return '<script data-page="' . $id . '" type="application/json">' . $json . '</script>' . "\n"
            . '<div id="' . $id . '" data-page="' . self::escape($json) . '">' . $ssrBody . '</div>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
