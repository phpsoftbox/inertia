<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Inertia\Page\Tab;
use PhpSoftBox\Inertia\Page\Tabs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tabs::class)]
#[CoversClass(Tab::class)]
#[CoversMethod(Tabs::class, 'add')]
#[CoversMethod(Tabs::class, 'set')]
#[CoversMethod(Tabs::class, 'replace')]
#[CoversMethod(Tabs::class, 'clear')]
#[CoversMethod(Tabs::class, 'isEmpty')]
#[CoversMethod(Tabs::class, 'toArray')]
#[CoversMethod(Tab::class, 'toArray')]
final class TabsTest extends TestCase
{
    /**
     * Проверяем добавление вкладок и экспорт их в массив.
     *
     * @see Tabs::add()
     * @see Tabs::toArray()
     */
    #[Test]
    public function testAddsItems(): void
    {
        $tabs = new Tabs('profile');

        $tabs
            ->add('Профиль', 'profile', '/profile')
            ->add('Пароль', 'password', '/profile/password');

        $data = $tabs->toArray();

        $this->assertSame('profile', $data['activeKey']);
        $this->assertCount(2, $data['tabs']);
        $this->assertSame('Профиль', $data['tabs'][0]['label']);
        $this->assertSame('/profile/password', $data['tabs'][1]['href']);
    }

    /**
     * Проверяем, что вкладки с пустым label или key не добавляются.
     *
     * @see Tabs::add()
     * @see Tabs::toArray()
     */
    #[Test]
    public function testIgnoresEmptyLabelOrKey(): void
    {
        $tabs = new Tabs();

        $tabs
            ->add(' ', 'profile')
            ->add('Профиль', '')
            ->add('Ок', 'ok', '/ok');

        $data = $tabs->toArray();

        $this->assertCount(1, $data['tabs']);
        $this->assertSame('Ок', $data['tabs'][0]['label']);
    }

    /**
     * Проверяем замену набора вкладок с нормализацией входных элементов.
     *
     * @see Tabs::set()
     * @see Tabs::toArray()
     */
    #[Test]
    public function testSetReplacesAndNormalizes(): void
    {
        $tabs = new Tabs();

        $tabs->set([
            ['label' => ' Профиль ', 'key' => 'profile', 'href' => '/profile'],
            ['label' => 'Пароль', 'key' => 'password', 'href' => null, 'disabled' => true],
            ['label' => '', 'key' => 'skip'],
            'invalid',
        ], 'profile');

        $data = $tabs->toArray();

        $this->assertSame('profile', $data['activeKey']);
        $this->assertCount(2, $data['tabs']);
        $this->assertSame('Профиль', $data['tabs'][0]['label']);
        $this->assertTrue($data['tabs'][1]['disabled']);
    }

    /**
     * Проверяем замену текущих вкладок другим объектом Tabs.
     *
     * @see Tabs::replace()
     * @see Tabs::toArray()
     */
    #[Test]
    public function testReplace(): void
    {
        $tabs = new Tabs('profile', [
            new Tab('Профиль', 'profile', '/profile'),
        ]);

        $other = new Tabs('password', [
            new Tab('Пароль', 'password', '/profile/password'),
        ]);

        $tabs->replace($other);
        $data = $tabs->toArray();

        $this->assertSame('password', $data['activeKey']);
        $this->assertSame('Пароль', $data['tabs'][0]['label']);
    }

    /**
     * Проверяем очистку вкладок и активного ключа.
     *
     * @see Tabs::clear()
     * @see Tabs::isEmpty()
     * @see Tabs::toArray()
     */
    #[Test]
    public function testClear(): void
    {
        $tabs = new Tabs('profile');

        $tabs->add('Профиль', 'profile');

        $this->assertFalse($tabs->isEmpty());

        $tabs->clear();

        $this->assertTrue($tabs->isEmpty());
        $this->assertSame(['activeKey' => null, 'tabs' => []], $tabs->toArray());
    }
}
