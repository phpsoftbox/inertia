<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Inertia\Page\Breadcrumbs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Breadcrumbs::class)]
#[CoversMethod(Breadcrumbs::class, 'add')]
#[CoversMethod(Breadcrumbs::class, 'all')]
#[CoversMethod(Breadcrumbs::class, 'set')]
#[CoversMethod(Breadcrumbs::class, 'clear')]
#[CoversMethod(Breadcrumbs::class, 'isEmpty')]
final class BreadcrumbsTest extends TestCase
{
    /**
     * Проверяем добавление элементов breadcrumbs и экспорт полного списка.
     *
     * @see Breadcrumbs::add()
     * @see Breadcrumbs::all()
     */
    #[Test]
    public function testAddsItems(): void
    {
        $breadcrumbs = new Breadcrumbs();

        $breadcrumbs
            ->add('Главная', '/')
            ->add('Пользователи', '/users')
            ->add('Профиль', null, true);

        $items = $breadcrumbs->all();

        $this->assertCount(3, $items);
        $this->assertSame('Главная', $items[0]['label']);
        $this->assertSame('/', $items[0]['href']);
        $this->assertFalse($items[0]['active']);
        $this->assertTrue($items[2]['active']);
    }

    /**
     * Проверяем, что breadcrumbs не добавляет элементы с пустым label.
     *
     * @see Breadcrumbs::add()
     * @see Breadcrumbs::all()
     */
    #[Test]
    public function testIgnoresEmptyLabels(): void
    {
        $breadcrumbs = new Breadcrumbs();

        $breadcrumbs
            ->add(' ')
            ->add('', '/skip')
            ->add('Ок', '/ok');

        $items = $breadcrumbs->all();
        $this->assertCount(1, $items);
        $this->assertSame('Ок', $items[0]['label']);
    }

    /**
     * Проверяем замену списка breadcrumbs с нормализацией входных элементов.
     *
     * @see Breadcrumbs::set()
     * @see Breadcrumbs::all()
     */
    #[Test]
    public function testSetReplacesAndNormalizes(): void
    {
        $breadcrumbs = new Breadcrumbs();

        $breadcrumbs->set([
            ['label' => ' Главная ', 'href' => '/', 'active' => false],
            ['label' => 'Профиль', 'href' => null, 'active' => true],
            ['label' => '', 'href' => '/skip'],
            'invalid',
        ]);

        $items = $breadcrumbs->all();
        $this->assertCount(2, $items);
        $this->assertSame('Главная', $items[0]['label']);
        $this->assertSame('Профиль', $items[1]['label']);
        $this->assertTrue($items[1]['active']);
    }

    /**
     * Проверяем очистку breadcrumbs и признак пустого списка.
     *
     * @see Breadcrumbs::clear()
     * @see Breadcrumbs::isEmpty()
     * @see Breadcrumbs::all()
     */
    #[Test]
    public function testClear(): void
    {
        $breadcrumbs = new Breadcrumbs();

        $breadcrumbs->add('Главная', '/');
        $this->assertFalse($breadcrumbs->isEmpty());

        $breadcrumbs->clear();
        $this->assertTrue($breadcrumbs->isEmpty());
        $this->assertSame([], $breadcrumbs->all());
    }
}
