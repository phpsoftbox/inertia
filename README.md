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
