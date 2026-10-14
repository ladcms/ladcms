# LADCMS

Модульная self-hosted CMS для сообществ на Laravel 13 + React 19 + Inertia 3.

- `app/` — приложение и starter kit (`laravel new site --using=ladcms/app`)
- `packages/*` — пакеты `ladcms/*` (сплитятся в отдельные репозитории)
- `docs/superpowers/specs` — спецификации, `docs/superpowers/plans` — планы

## Разработка

```bash
cd app && vendor/bin/sail up -d && vendor/bin/sail npm run dev
```

Тесты пакетов: `vendor/bin/pest` из корня. Тесты приложения: `cd app && vendor/bin/sail pest`.
