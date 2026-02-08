<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Tests;

use PhpSoftBox\Inertia\Page\Tab;
use PhpSoftBox\Inertia\Page\Tabs;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TabsTest extends TestCase
{
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
