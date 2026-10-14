# LADCMS

Монорепо: `app/` (Laravel 13 + Inertia 3 + React 19) и `packages/*` (пакеты `ladcms/*`).
Спека: `docs/superpowers/specs/2026-10-14-ladcms-foundation-and-core-design.md`.

## Правила
- Бизнес-логика только в `src/Actions/*` (один публичный `handle()`), каждый Action — с unit-тестом без HTTP.
- Страницы модулей именуются `<module>::Path/Page`; npm-зависимости модулей — через `npmDependencies()`, не через свой package.json.
- Все команды приложения — через Sail: `cd app && vendor/bin/sail artisan …`.
- Тесты пакетов — из корня: `vendor/bin/pest`. Форматирование — `vendor/bin/pint`.
- Язык общения — русский, код и коммиты — английский.
