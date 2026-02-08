<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Inertia\Page\PageMeta;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageMetaTest extends TestCase
{
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

    #[Test]
    public function testIgnoresEmptyTitleAndDescription(): void
    {
        $meta = new PageMeta();

        $meta->setTitle('  ')->setDescription('');

        $this->assertTrue($meta->isEmpty());
        $this->assertSame([], $meta->toArray());
    }
}
