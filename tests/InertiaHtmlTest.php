<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Inertia\View\InertiaHtml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function substr_count;

use const JSON_THROW_ON_ERROR;

#[CoversClass(InertiaHtml::class)]
#[CoversMethod(InertiaHtml::class, 'pageJson')]
#[CoversMethod(InertiaHtml::class, 'pageAttribute')]
#[CoversMethod(InertiaHtml::class, 'root')]
final class InertiaHtmlTest extends TestCase
{
    /**
     * Проверим, что pageJson() экранирует HTML-символы и остаётся валидным JSON с исходными данными.
     *
     * @see InertiaHtml::pageJson()
     */
    #[Test]
    public function pageJsonEscapesHtmlCharacters(): void
    {
        $page = ['props' => ['title' => '</script><b>"x" & \'y\'</b>']];

        $json = InertiaHtml::pageJson($page);

        self::assertStringNotContainsString('<', $json);
        self::assertStringNotContainsString('&', $json);
        self::assertStringNotContainsString("'", $json);
        self::assertSame($page, json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Проверим, что pageAttribute() не содержит кавычек, закрывающих HTML-атрибут.
     *
     * @see InertiaHtml::pageAttribute()
     */
    #[Test]
    public function pageAttributeEscapesQuotes(): void
    {
        $attribute = InertiaHtml::pageAttribute(['component' => 'Home']);

        self::assertStringNotContainsString('"', $attribute);
        self::assertStringContainsString('&quot;component&quot;', $attribute);
    }

    /**
     * Проверим, что root() выводит JSON-script и root-элемент с id и SSR-разметкой без лишних </script>.
     *
     * @see InertiaHtml::root()
     */
    #[Test]
    public function rootRendersScriptAndElement(): void
    {
        $html = InertiaHtml::root(['props' => ['x' => '</script>']], 'app"id', '<main>SSR</main>');

        self::assertStringStartsWith('<script data-page="app&quot;id" type="application/json">', $html);
        self::assertSame(1, substr_count($html, '</script>'));
        self::assertStringContainsString('<div id="app&quot;id" data-page="', $html);
        self::assertStringEndsWith('"><main>SSR</main></div>', $html);
    }
}
