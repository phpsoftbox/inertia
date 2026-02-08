<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Inertia\Page\Menu;
use PhpSoftBox\Inertia\Page\MenuItem;
use PhpSoftBox\Inertia\Page\MenuMatchMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Menu::class)]
#[CoversClass(MenuItem::class)]
#[CoversClass(MenuMatchMode::class)]
#[CoversMethod(Menu::class, 'add')]
#[CoversMethod(Menu::class, 'addDivider')]
#[CoversMethod(Menu::class, 'toArray')]
#[CoversMethod(MenuItem::class, 'withMatchMode')]
#[CoversMethod(MenuItem::class, 'withBadge')]
#[CoversMethod(MenuItem::class, 'setChildren')]
#[CoversMethod(MenuItem::class, 'disable')]
#[CoversMethod(MenuItem::class, 'divider')]
#[CoversMethod(MenuItem::class, 'toArray')]
final class MenuTest extends TestCase
{
    /**
     * Проверяем сборку меню с пунктами, вложенными пунктами, divider и disabled-состоянием.
     *
     * @see Menu::add()
     * @see Menu::addDivider()
     * @see Menu::toArray()
     * @see MenuItem::withMatchMode()
     * @see MenuItem::withBadge()
     * @see MenuItem::setChildren()
     * @see MenuItem::disable()
     */
    #[Test]
    public function testBuildsMenuArray(): void
    {
        $menu = new Menu();

        $menu->add(
            new MenuItem('Dashboard', '/', 'dashboard', 'dashboard')
                ->withMatchMode(MenuMatchMode::EQUALS)
                ->withBadge(0),
        );

        $menu->add(
            new MenuItem('Промо', null, 'promo', 'promo')
                ->setChildren([
                    new MenuItem('Промокоды', '/promo/promocodes', 'promocode', 'promocode'),
                ]),
        );

        $menu->addDivider();
        $menu->add(new MenuItem('Выход', null, 'logout', 'logout')->disable());

        $data = $menu->toArray();

        $this->assertSame('dashboard', $data[0]['id']);
        $this->assertSame('equals', $data[0]['match']);
        $this->assertSame('Промо', $data[1]['label']);
        $this->assertSame('Промокоды', $data[1]['children'][0]['label']);
        $this->assertTrue($data[3]['disabled']);
        $this->assertTrue($data[2]['divider']);
    }
}
