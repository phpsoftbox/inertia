# Inertia

Минимальный серверный адаптер Inertia.js для PhpSoftBox.

## Возможности

- JSON‑ответы по `X-Inertia`.
- HTML‑ответы с root‑view для первого захода.
- Middleware для проверки версии ассетов и заголовка `Vary: X-Inertia`.

## Пример

```php
$response = $inertia->render('Home', [
    'title' => 'Inertia App',
]);
```

## Метаданные страницы

Можно задавать `title`, `description`, `keywords` через сервис `PageMeta`.
Данные попадут в `props.meta` и в HTML‑теги на первом рендере.

```php
use PhpSoftBox\Inertia\Page\PageMeta;

$meta->setTitle('Панель управления')
    ->setDescription('Админ‑панель проекта')
    ->setKeywords(['admin', 'dashboard']);

return $inertia->render('Dashboard');
```

## Breadcrumbs

```php
use PhpSoftBox\Inertia\Page\Breadcrumbs;

$breadcrumbs
    ->add('Главная', '/')
    ->add('Пользователи', '/users')
    ->add('Профиль', null, true);

return $inertia->render('Users/Show');
```

## Menu

```php
use PhpSoftBox\Inertia\Page\Menu;
use PhpSoftBox\Inertia\Page\MenuItem;
use PhpSoftBox\Inertia\Page\MenuMatchMode;

$menu = new Menu();
$menu->add(
    (new MenuItem('Dashboard', '/', 'dashboard', 'dashboard'))
        ->withMatchMode(MenuMatchMode::EQUALS),
);

$menu->add(
    (new MenuItem('Промо', null, 'promo', 'promo'))
        ->setChildren([
            new MenuItem('Промокоды', '/promo/promocodes', 'promocode', 'promocode'),
        ]),
);
```

## Tabs

```php
use PhpSoftBox\Inertia\Page\Tabs;

$tabs
    ->setActiveKey('profile')
    ->add('Профиль', 'profile', '/profile')
    ->add('Пароль', 'password', '/profile/password');

return $inertia->render('Profile/Show');
```

## SSR (заготовка)

SSR опционален и выключен по умолчанию. Для интеграции нужен свой
рендерер, реализующий `SsrRendererInterface`.

```php
use PhpSoftBox\Inertia\Ssr\SsrRendererInterface;

final class MySsrRenderer implements SsrRendererInterface
{
    public function render(ServerRequestInterface $request, InertiaPage $page): ?SsrResponse
    {
        // Вернуть head/body, либо null если SSR не нужен для этого запроса.
    }
}
```

В конфиге Inertia:

```php
return [
    'ssr' => [
        'enabled' => env('INERTIA_SSR', false),
    ],
];
```

Если SSR включён и доступен, view получает переменную `$ssr`:
`['head' => string[], 'body' => string]`. Дефолтный view в AppBackend
уже умеет вставлять эти данные.

### Разделение SSR по хосту/пути

Решение остаётся за вашим `SsrRendererInterface` — можно включать SSR
только для публичного сайта и выключать для админки/кабинета.

```php
public function render(ServerRequestInterface $request, InertiaPage $page): ?SsrResponse
{
    $host = $request->getUri()->getHost();
    $path = $request->getUri()->getPath();

    if ($host === 'admin.example.local' || str_starts_with($path, '/cabinet')) {
        return null; // SSR не нужен.
    }

    return $this->renderFromNode($page);
}
```
