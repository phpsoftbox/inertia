# Inertia

Минимальный серверный адаптер Inertia.js для PhpSoftBox.

## Возможности

- JSON‑ответы по `X-Inertia`.
- HTML‑ответы с root‑view для первого захода.
- Middleware для проверки версии ассетов и заголовка `Vary: X-Inertia`.
- Partial reload по `X-Inertia-Partial-*` headers.

## Пример

```php
$response = $inertia->render('Home', [
    'title' => 'Inertia App',
]);
```

## Shared-данные и состояние между запросами

`Inertia` — долгоживущий сервис: в worker'е (RoadRunner, Swoole и т.п.) один экземпляр обслуживает
много запросов. Поэтому shared-данные разделены на два уровня:

| Уровень | Как задаётся | Время жизни |
|---|---|---|
| Базовый (приложение) | `InertiaConfig(shared: [...])`, `shareProvider(..., persistent: true)` | весь срок жизни сервиса |
| Запрос | `share()`, `shareMany()`, `shareProvider(...)` (по умолчанию `persistent: false`) | до `setRequest()` / `reset()` |

- `setRequest($request)` начинает новый запрос: сбрасывает данные предыдущего запроса и запоминает текущий.
  Его вызывает `InertiaShareMiddleware`, поэтому `share()` нужно вызывать **после** этого middleware
  (в action или последующих middleware) — данные, добавленные раньше, будут сброшены.
- `reset()` — явный сброс состояния запроса (request, `share()`/`shareMany()`, request-scoped провайдеры)
  для границы запроса в worker'е. Базовые данные конфига и persistent-провайдеры сохраняются.
  В приложении подключается через `ServicesResetter` из `phpsoftbox/container`:

  ```php
  new ServicesResetter($container, [
      Inertia::class => 'reset',
  ]);
  ```

- `shareProvider(callable $provider, bool $persistent = false, ?string $key = null)`:
  провайдер по умолчанию относится к текущему запросу. Persistent-провайдеры регистрируйте только
  при сборке приложения; повторная регистрация того же callable игнорируется, а провайдер с тем же
  `$key` заменяет предыдущий — список провайдеров не растёт от запроса к запросу.

```php
// При сборке приложения (container factory):
$inertia->shareProvider(fn (): array => ['locale' => $translator->locale()], persistent: true, key: 'locale');

// В action / middleware после InertiaShareMiddleware:
$inertia->share('auth', ['user' => $user]);
```

Порядок наложения shared-данных (последующий уровень переопределяет предыдущий): `InertiaConfig::$shared`
→ `share()`/`shareMany()` → `shared` area → persistent-провайдеры → request-провайдеры → area-провайдеры
→ props из `render()`.

> До 1.0 `share()` жил до конца работы процесса (например, `share('auth', $user)` был виден следующему
> пользователю в worker'е), а `shareProvider()` по умолчанию был persistent и накапливался при регистрации
> на каждом запросе.

## Слияние props

Shared-данные и props сливаются рекурсивно только для ассоциативных массивов. Списки (`array_is_list`)
и скаляры из более приоритетного источника **заменяют** значение целиком, без слияния по индексам:

```php
// shared: ['menu' => ['items' => ['a', 'b', 'c']], 'app' => ['name' => 'Demo', 'area' => 'web']]
$inertia->render('Page', ['menu' => ['items' => ['x']], 'app' => ['area' => 'admin']]);
// props: ['menu' => ['items' => ['x']], 'app' => ['name' => 'Demo', 'area' => 'admin']]
```

## Root view

HTML первого захода рендерит `View\PhpViewRenderer` по шаблону `InertiaConfig::rootView()` с id
корневого элемента `InertiaConfig::rootId()`:

```php
use PhpSoftBox\Inertia\View\PhpViewRenderer;

$renderer = PhpViewRenderer::fromConfig($inertiaConfig, ['vite' => $vite]);
```

В шаблон передаются `$page` (массив page), `$rootId`, `$ssr` (только при SSR) и shared-данные renderer-а.
Для безопасной вставки page в HTML используйте `View\InertiaHtml` — JSON кодируется с
`JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`, поэтому `</script>` в props не ломает разметку:

```php
<?php use PhpSoftBox\Inertia\View\InertiaHtml; ?>
<body>
<?= InertiaHtml::root($page, $rootId, $ssr['body'] ?? '') ?>
</body>
```

- `InertiaHtml::root($page, $rootId, $ssrBody)` — `<script data-page="{rootId}" type="application/json">` и
  `<div id="{rootId}" data-page="...">` с SSR-разметкой (SSR body вставляется как доверенный HTML);
- `InertiaHtml::pageJson($page)` — JSON для вставки внутрь `<script type="application/json">`;
- `InertiaHtml::pageAttribute($page)` — JSON, экранированный для атрибута `data-page="..."`.

Не вставляйте `json_encode($page)` без этих флагов: строка `</script>` в любом prop закроет тег (XSS).

## Partial reload

Компонент поддерживает partial reload на уровне top-level props.
Фильтрация включается только для Inertia request, если
`X-Inertia-Partial-Component` совпадает с компонентом, который рендерится.

Поддерживаемые headers:

- `X-Inertia-Partial-Data` — comma-separated список props, которые нужно вернуть;
- `X-Inertia-Partial-Except` — comma-separated список props, которые нужно исключить.

Если клиент отправил оба headers, `X-Inertia-Partial-Except` имеет приоритет.
Для `X-Inertia-Partial-Data` page props фильтруются до вычисления closures,
поэтому незапрошенные тяжелые props не будут вычисляться.

## Нормализация payload

В `Inertia` можно передать `PayloadNormalizerInterface`. Normalizer применяется
ко всем выбранным page/shared props после вычисления `Closure`, но до JSON, HTML
и SSR-рендеринга:

```php
use PhpSoftBox\Inertia\Integration\ResourcePayloadNormalizer;

$normalizer = new ResourcePayloadNormalizer($resourceSerializer);

$inertia = new Inertia(
    // ...
    payloadNormalizer: $normalizer,
);
```

`ResourcePayloadNormalizer` является опциональной интеграцией. Для него требуется
пакет `phpsoftbox/resource`; собственный адаптер в приложении не нужен.

При partial reload исключённые props не вычисляются и не передаются normalizer.

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

## SSR

SSR опционален и выключен по умолчанию. Для интеграции можно использовать
встроенный HTTP renderer или свой рендерер, реализующий `SsrRendererInterface`.

```php
use PhpSoftBox\Inertia\Ssr\HttpSsrRenderer;

$renderer = new HttpSsrRenderer(
    url: 'http://node:13714/render',
    timeout: 2.0,
);
```

`HttpSsrRenderer` отправляет `InertiaPage` JSON POST-запросом на SSR endpoint
и ожидает ответ формата `['head' => string[], 'body' => string]`.
По умолчанию renderer работает в fail-open режиме: если SSR endpoint
недоступен, он вернёт `null`, и Inertia отдаст обычный HTML shell.

Для строгого режима передайте `failSilently: false`: тогда недоступный сервер, HTTP-статус вне 2xx,
пустой или невалидный JSON-ответ, а также ответ без `head` и `body` приводят к `RuntimeException`.
Встроенный `NativeHttpSsrTransport` сам бросает `RuntimeException` в этих случаях; собственный
transport может бросить исключение или вернуть `null` — в строгом режиме оба варианта считаются ошибкой.

В конфиге Inertia:

```php
use PhpSoftBox\Inertia\Area\InertiaAreaConfig;

return [
    'ssr' => env('INERTIA_SSR', false),

    'areas' => [
        'default' => 'web',

        'web' => new InertiaAreaConfig(
            pathPrefixes: ['/'],
            ssr: true,
            shared: [
                'app' => [
                    'area' => 'web',
                ],
            ],
        ),

        'admin' => new InertiaAreaConfig(
            pathPrefixes: ['/admin'],
            ssr: false,
            shared: [
                'app' => [
                    'area' => 'admin',
                ],
            ],
        ),
    ],
];
```

Если SSR включён и доступен, view получает переменную `$ssr`:
`['head' => string[], 'body' => string]`. Дефолтный view в AppBackend
уже умеет вставлять эти данные.

### Areas

Areas позволяют централизованно разделять публичную часть, админку,
кабинет или tenant‑области по host/path и задавать для них свои
Inertia-настройки.

`InertiaAreaConfig` поддерживает:

- `hosts` — список host или URL, например `admin.example.local`;
- `paths` — точные пути;
- `pathPrefixes` — префиксы путей, где более длинный prefix приоритетнее;
- `ssr` — `true`, `false` или `null`, если нужно использовать базовую настройку;
- `shared` — area-specific props.

### Host-based areas

Для разделения публичной части и админки по доменам настройте areas через
`hosts`. Это удобнее, чем path-prefix, если приложение живёт на нескольких
поддоменах:

```php
use PhpSoftBox\Inertia\Area\InertiaAreaConfig;

return [
    'areas' => [
        'default' => 'web',

        'web' => new InertiaAreaConfig(
            hosts: ['www.example.com', 'docs.example.com'],
            shared: [
                'app' => [
                    'area' => 'web',
                ],
            ],
        ),

        'admin' => new InertiaAreaConfig(
            hosts: ['admin.example.com'],
            ssr: false,
            shared: [
                'app' => [
                    'area' => 'admin',
                ],
            ],
        ),
    ],
];
```

Доступ к такой area лучше защищать отдельным auth middleware, например
`AreaAccessMiddleware` из `phpsoftbox/auth`, с тем же именем area:

```php
use PhpSoftBox\Auth\Middleware\AreaAccessDeniedMode;
use PhpSoftBox\Auth\Middleware\AreaAccessRule;

new AreaAccessRule(
    area: 'admin',
    permission: 'admin.access',
    deniedMode: AreaAccessDeniedMode::NotFound,
)
```

Area-specific shared-data можно расширять отдельными провайдерами:

```php
use PhpSoftBox\Inertia\Area\AreaSharedDataProviderInterface;

final class AdminSharedDataProvider implements AreaSharedDataProviderInterface
{
    public function area(): string
    {
        return 'admin';
    }

    public function share(ServerRequestInterface $request): array
    {
        return [
            'admin' => [
                'sidebar' => [],
            ],
        ];
    }
}
```

`shared` из `InertiaAreaConfig` и данные из `AreaSharedDataProviderInterface`
объединяются рекурсивно. Provider применяется позже и поэтому может
дополнить или переопределить значения из конфига.

```php
'admin' => new InertiaAreaConfig(
    pathPrefixes: ['/admin'],
    ssr: false,
    shared: [
        'app' => [
            'area' => 'admin',
            'name' => 'Admin Panel',
        ],
    ],
),
```

```php
final class AdminSharedDataProvider implements AreaSharedDataProviderInterface
{
    public function area(): string
    {
        return 'admin';
    }

    public function share(ServerRequestInterface $request): array
    {
        return [
            'app' => [
                'theme' => 'compact',
            ],
            'admin' => [
                'sidebar' => [],
            ],
        ];
    }
}
```

Итоговые props будут содержать:

```php
[
    'app' => [
        'area' => 'admin',
        'name' => 'Admin Panel',
        'theme' => 'compact',
    ],
    'admin' => [
        'sidebar' => [],
    ],
]
```

Если provider вернёт уже существующий ключ, он заменит значение из
конфига:

```php
return [
    'app' => [
        'name' => 'Runtime Admin',
    ],
];
```

В этом случае итоговое `app.name` будет `Runtime Admin`.
