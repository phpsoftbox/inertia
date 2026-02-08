<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Inertia\Page\PageMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageMeta::class)]
#[CoversMethod(PageMeta::class, 'setTitle')]
#[CoversMethod(PageMeta::class, 'setDescription')]
#[CoversMethod(PageMeta::class, 'setKeywords')]
#[CoversMethod(PageMeta::class, 'clear')]
#[CoversMethod(PageMeta::class, 'toArray')]
#[CoversMethod(PageMeta::class, 'isEmpty')]
final class PageMetaTest extends TestCase
{
    /**
     * Проверяем установку title, description, keywords и экспорт meta.
     *
     * @see PageMeta::setTitle()
     * @see PageMeta::setDescription()
     * @see PageMeta::setKeywords()
     * @see PageMeta::toArray()
     * @see PageMeta::isEmpty()
     */
    #[Test]
    public function testSetsAndExportsMeta(): void
    {
        $meta = new PageMeta();

        $meta
            ->setTitle(' Dashboard ')
            ->setDescription('  Admin panel ')
            ->setKeywords([' admin ', 'panel', '', '  ']);

        $this->assertFalse($meta->isEmpty());

        $data = $meta->toArray();
        $this->assertSame('Dashboard', $data['title']);
        $this->assertSame('Admin panel', $data['description']);
        $this->assertSame(['admin', 'panel'], $data['keywords']);
    }

    /**
     * Проверяем разбор keywords из строки и очистку meta.
     *
     * @see PageMeta::setKeywords()
     * @see PageMeta::clear()
     * @see PageMeta::isEmpty()
     * @see PageMeta::toArray()
     */
    #[Test]
    public function testSupportsKeywordStringAndReset(): void
    {
        $meta = new PageMeta();

        $meta->setKeywords('one, two,, three');

        $data = $meta->toArray();
        $this->assertSame(['one', 'two', 'three'], $data['keywords']);

        $meta->clear();
        $this->assertTrue($meta->isEmpty());
        $this->assertSame([], $meta->toArray());
    }

    /**
     * Проверяем, что пустые title и description не попадают в meta.
     *
     * @see PageMeta::setTitle()
     * @see PageMeta::setDescription()
     * @see PageMeta::isEmpty()
     * @see PageMeta::toArray()
     */
    #[Test]
    public function testIgnoresEmptyTitleAndDescription(): void
    {
        $meta = new PageMeta();

        $meta->setTitle('  ')->setDescription('');

        $this->assertTrue($meta->isEmpty());
        $this->assertSame([], $meta->toArray());
    }
}
