# LADCMS: фундамент и ядро — дизайн

> Дата: 2026-10-14
> Статус: Draft (на ревью)
> Заменяет: `2026-04-04-ladcms-core-architecture-design.md` (помечена superseded; карта модулей и контракты оттуда адаптированы здесь)
> Охват: волна 0 (монорепо, инструменты, `app`) и волна 1 (`core`). Архитектурные решения зафиксированы для всех последующих модулей; сами модули получат отдельные спеки.

---

## 1. Цель и границы

LADCMS — модульная self-hosted CMS для сообществ (форум, новости, файлы, чат, профили), переписывание старого DCMS 7.5 (процедурный PHP, 2007–2016) на современный стек. Код DCMS **не портируется** (проприетарная лицензия), переносится только функциональность. Новый код — MIT.

**Чистый старт.** Миграция данных с живых DCMS-сайтов не входит в дизайн; если понадобится — отдельный пакет-импортёр (БД + файлы), архитектура ему не мешает.

**Что в этой спеке:** монорепо, окружение, `app` как starter kit, контракт модуля, пакет `core` целиком (домен, схема, админка, UI, дизайн). **Чего нет:** дизайн отдельных модулей (`news`, `forum`, …) — каждый получит свою спеку по мере очереди.

### Волны

| Волна | Содержимое | Спека |
|---|---|---|
| 0 | Монорепо, split-воркфлоу, `app` на react-starter-kit, Sail, CI, Boost | эта |
| 1 | `core` | эта |
| 2 | `news` — самый маленький модуль, обкатка контракта | отдельная |
| 3 | `editor-tiptap`, `forum` (+ `polls`) | отдельные |
| 4 | `files`, `photos`, `chat` (Reverb) | отдельные |
| 5 | `community`, `reviews`, `balls`, `feed`, `analytics`, `socialite`, `themes` | отдельные |

---

## 2. Стек (актуальные версии на 2026-10-14)

| Слой | Выбор | Версия | Примечание |
|---|---|---|---|
| PHP | | **8.4** минимум | Pest 5 требует 8.4; Laravel 14 перейдёт на 8.4 |
| Backend | Laravel | 13.x | |
| Frontend | React + TypeScript strict | 19 / 5.x | решение: React, а не Vue — опыт автора + будущее мобильное приложение |
| Связка | Inertia | 3.x (`inertia-laravel`, `@inertiajs/react`) | SSR включаем позже, если понадобится SEO |
| Сборка | Vite | 8.x | |
| CSS | Tailwind | 4.x | |
| UI-kit | shadcn/ui (Radix) | | копируется в `core`, не в `app` |
| Иконки | `@lucide/react` | 1.x | |
| Auth | Fortify | из starter kit | логин по `login` или email, 2FA, passkeys |
| Роуты в TS | Wayfinder | 0.1.x | вместо Ziggy |
| Права | `spatie/laravel-permission` | 8.x | + наши `level`, `color` на ролях |
| Настройки | `spatie/laravel-settings` | 3.x | типизированные классы по группам |
| Аудит | `spatie/laravel-activitylog` | 5.x | |
| Пакетная база | `spatie/laravel-package-tools` | 1.9x | под нашим `ModuleServiceProvider` |
| API-токены | Sanctum | | ставится сразу, роуты модулей — по мере надобности |
| Realtime | Reverb + Echo | | контейнер есть с волны 0, используется с волны 4 |
| Картинки | `intervention/image` | 3.x | ресайз аватаров |
| Тесты | Pest 5 + `orchestra/testbench` | | |
| Качество | Pint, Larastan (level 6), Rector, ESLint 9, Prettier, `tsc` | | |
| AI-tooling | `laravel/boost` | | MCP `search-docs`, guidelines, `.ai/rules` |
| Dev-окружение | Laravel Sail | | PHP 8.4, MySQL 8.4, Redis, Mailpit, Reverb |

Не используем: Ziggy, FontAwesome, Livewire, Next.js, DDEV/Lando, Vue.

---

## 3. Монорепо

### 3.1. Структура

```
ladcms/                              # github.com/ladcms/ladcms
├── app/                             # приложение = starter kit (split → ladcms/app)
├── packages/
│   ├── core/                        # split → ladcms/core
│   └── <module>/                    # split → ladcms/<module>
├── docs/superpowers/{specs,plans}/
├── .github/workflows/{ci,split}.yml
├── .ai/rules/                       # проектные правила Boost (коммитятся)
├── composer.json                    # только dev-инструменты монорепо (pint, phpstan, rector)
└── .gitignore                       # .superpowers/, app/.mcp.json, app/boost.json
```

`app/composer.json`:
```json
"repositories": [{ "type": "path", "url": "../packages/*", "options": { "symlink": true } }],
"require": { "ladcms/core": "*" }
```

### 3.2. Split

Воркфлоу `split.yml` на push в `main` и на тег `v*`: `symplify/monorepo-split-github-action` выталкивает `packages/<name>` → `ladcms/<name>` и `app/` → `ladcms/app`. При сплите `app/composer.json` переписывается: `path`-репозитории удаляются, `ladcms/*: "*"` → `"^<major.minor>"`. Теги прокидываются во все сплит-репо; Packagist подхватывает их.

**Единая версия**: все пакеты монорепо получают один номер версии (как Laravel/Symfony). Модуль требует `ladcms/core: ^1.x` той же минорной линии.

Существующие репозитории организации `core` и `chat` (пустые) и `app` (старый starter kit на Laravel 12) становятся целями сплита; история `app` не сохраняется.

### 3.3. Starter kit

`ladcms/app` — обычное Laravel-приложение, установленное через `laravel new app --react --npm --boost --no-interaction`, с `composer require ladcms/core` и Sail. Для конечного пользователя: `laravel new mysite --using=ladcms/app`. В `composer.json` приложения — `post-create-project-cmd` (копия `.env`, `key:generate`), `post-update-cmd` (`boost:update`, `ladcms:update`). Больше ничего специально под starter kit не делаем.

### 3.4. Docker (Sail)

`app/docker-compose.yml` (сгенерирован `sail:install`, затем правится):
- сервисы: `laravel.test` (PHP 8.4 + Node 22), `mysql` 8.4, `redis`, `mailpit`, `reverb`;
- **монтируется корень монорепо** (`../:/var/www/html`), `working_dir: /var/www/html/app`, чтобы `path`-репозитории `../packages/*` резолвились внутри контейнера;
- все команды — через `sail artisan|composer|npm|pest`.

MySQL — первичная БД (целевая аудитория), PostgreSQL поддерживается (без MySQL-специфичного SQL), SQLite in-memory — для тестов пакетов.

Boost: `.mcp.json` приложения регистрирует `laravel-boost` через `vendor/bin/sail artisan boost:mcp`.

### 3.5. CI

`ci.yml`, один воркфлоу, jobs по порядку:
1. `lint` — `pint --test`, `eslint`, `prettier --check`;
2. `static` — `phpstan` (Larastan level 6), `tsc --noEmit`, `rector --dry-run`;
3. `test` — матрица: каждый `packages/*` (Testbench, SQLite) + `app` (MySQL-сервис), PHP 8.4;
4. `build` — `vite build`.

---

## 4. Контракт модуля

### 4.1. Раскладка пакета (одинаковая для всех)

```
packages/<module>/
├── composer.json                 # ladcms/<module>; require ladcms/core; extra.laravel.providers
├── config/<module>.php
├── database/{migrations,factories,seeders}/
├── routes/{web,admin,api}.php    # api.php — пусто до появления потребителя
├── lang/{ru,en,…}/*.php
├── resources/
│   ├── js/pages/**/*.tsx         # Inertia-страницы; имя "<module>::Admin/Index"
│   ├── js/components/            # приватные компоненты модуля
│   └── boost/{guidelines,skills}/
├── src/
│   ├── <Module>ServiceProvider.php
│   ├── Actions/                  # вся бизнес-логика
│   ├── Models/ Policies/ Events/ Listeners/ Notifications/ Settings/
│   └── Http/{Controllers,Requests,Resources}/
└── tests/                        # Pest + Testbench
```

### 4.2. ServiceProvider

`Ladcms\Core\Module\ModuleServiceProvider extends Spatie\LaravelPackageTools\PackageServiceProvider`. Модуль объявляет:

```php
final class ForumServiceProvider extends ModuleServiceProvider
    implements HasAdminPanel, HasPermissions, HasNavigation, HasWidgets, HasSettings, HasProfileSections
{
    public function name(): string { return 'forum'; }
    public function version(): string { return '1.0.0'; }
    public function coreVersion(): string { return '^1.0'; }
    public function dependencies(): array { return []; }            // обязательные модули
    public function optionalDependencies(): array { return ['balls', 'polls']; }
    public function npmDependencies(): array { return []; }         // {'@tiptap/react': '^3.0'}
    public function install(): void {}                              // хук после стандартных шагов
    public function uninstall(): void {}
    // + методы интерфейсов
}
```

Базовый класс в `boot()`: конфиг, миграции, роуты (`web.php` без префикса, `admin.php` с `admin/<module>` + middleware `['web','auth','admin']`, `api.php` с `api/<module>` + `auth:sanctum`), переводы (namespace `<module>`), регистрация в `ModuleRegistry`, проверка `coreVersion()` и `dependencies()`. При провале проверки провайдер **не бутится**, пишет в лог и в `ModuleRegistry::failures()` (админка показывает «модуль отключён: причина»), сайт продолжает работать.

### 4.3. Интерфейсы

| Интерфейс | Метод | Кто потребляет |
|---|---|---|
| `HasAdminPanel` | `adminMenu(): array` — пункты `{label, icon, route, order, children[]}` | двухуровневый сайдбар админки |
| `HasPermissions` | `permissions(): array` — `['forum.view' => 'forum::permissions.view', …]` | `ladcms:install`, страница ролей |
| `HasNavigation` | `navigation(): array` — `{label, icon, route, order}` | верхнее меню + нижняя панель на телефоне |
| `HasWidgets` | `widgets(): array` — `{name, component, slot: 'sidebar'\|'main', order}` | layout публичной части |
| `HasSettings` | `settings(): array` — классы `Settings` | автоформы в админке |
| `HasProfileSections` | `profileSections(): array` — `{tab: {label, component}, overview: {component, order}}` | страница профиля |
| `HasProfileActions` | `profileActions(): array` — кнопки в шапке профиля `{label, icon, component, order}` | страница профиля («Написать», «В друзья») |
| `HasHeaderActions` | `headerActions(): array` — иконки в шапке `{icon, component, order}` | шапка (✉ от `community`) |
| `HasSearch` | `searchables(): array` | глобальный поиск (волна 3+) |
| `HasBallsRules` | `ballsRules(): array` | модуль `balls` |

Проверка наличия модуля: `ModuleRegistry::has('community')`; на фронте — `usePage().props.modules` (массив имён).

### 4.4. Actions

Вся бизнес-логика — классы в `src/Actions`, один публичный метод `handle(...)`, без зависимости от HTTP. Контроллеры Inertia и (будущие) API-контроллеры — тонкие обёртки. **Правило:** каждый Action покрыт unit-тестом без HTTP. Это правило записывается в `.ai/rules` через Boost `record-rule`.

### 4.5. React-страницы модулей

Отдельный Vite-плагин не нужен (уточнено при планировании 2026-10-14): `app/resources/js/app.tsx` передаёт в `createInertiaApp` свой `resolve`, который использует два `import.meta.glob`: `./pages/**/*.tsx` (страницы приложения) и `/vendor/ladcms/*/resources/js/pages/**/*.tsx` (страницы модулей; `vendor/ladcms/*` — symlink на `packages/*` в dev и реальная папка в prod). Хелпер `resolveModulePage()` живёт в `core/resources/js/inertia/resolve-page.ts`. В Vite — `resolve.preserveSymlinks: true`, чтобы зависимости модулей резолвились из `app/node_modules`. На сервере `ModulePageFinder` декорирует `inertia.view-finder` и понимает имена `module::Path`.

Алиасы: `@ladcms/core` → `vendor/ladcms/core/resources/js` (UI-kit, layouts, hooks). Модули **не имеют** собственного `package.json`: npm-зависимости объявляются в `npmDependencies()` и дописываются в `app/package.json` командой установки.

### 4.6. Команды

| Команда | Когда | Что делает |
|---|---|---|
| `ladcms:install` | один раз, новый сайт | мастер: проверка окружения, `migrate`, сидер ролей, админ, базовые настройки, язык. Замена `/install/` |
| `ladcms:install <module>` | после `composer require` и после обновления модуля | идемпотентно: 1) `migrate`; 2) создать недостающие permissions; 3) дефолты настроек (реализуются settings-миграциями, которые выполняет `migrate` на шаге 1); 4) `npmDependencies()` → `app/package.json`; 5) хук `install()`; 6) запись в `modules`; 7) подсказка `npm install && npm run build` |
| `ladcms:update` | после `composer update` (в `post-update-cmd`) | `ladcms:install` для всех модулей из `modules` |
| `ladcms:uninstall <module>` | перед `composer remove` | хук `uninstall()`, откат миграций модуля (с подтверждением), удаление permissions и записи в `modules` |

Поток пользователя CMS:
```
composer require ladcms/forum
php artisan ladcms:install forum
npm install && npm run build
```

### 4.7. Boost для модулей

Каждый модуль кладёт `resources/boost/guidelines/core.blade.php` и при необходимости `resources/boost/skills/<name>/SKILL.md` — агент пользователя LADCMS получает инструкции по модулю после `boost:install`. Шаблон модуля (волна 2) включает заготовки.

---

## 5. Пакет `core`

### 5.1. Домен

| Область | Решение |
|---|---|
| **User** | `Ladcms\Core\Models\User`; `app` наследует его. `login` — для входа и URL, `name` — отображаемое (по умолчанию = login). Soft delete |
| **Auth** | Fortify: логин по `login` или email; регистрация с настройками `registration.open`, `registration.require_email_verification`, `registration.require_rules`, `registration.invite_only` (инвайты реализует `community`, core только читает флаг и вызывает `InviteValidator`-контракт, если он забинден). `registration.readonly_hours` — новый аккаунт read-only N часов. 2FA и passkeys — из kit |
| **Роли/права (гибрид)** | Spatie. Роли с `level` (0–6), `color`, `is_default`, `is_system`. Сидер: `guest 0`, `user 1`, `moderator 2`, `super_moderator 3`, `admin 4`, `main_admin 5`, `creator 6` + `vip` (без уровня). **Что можно делать** — permissions модулей; **кто выше кого** — `level`: `$user->outranks($other)`, модератор не трогает равных/старших. Gate-bypass для `creator` действует только на обычные проверки permissions (policy выполняются); последнего creator нельзя удалить или понизить. Объекты модулей (категория форума, папка) хранят `min_role_level` для просмотра/записи — решение каждого модуля, core даёт хелпер `RoleLevel` |
| **Настройки** | `laravel-settings`. Core: `GeneralSettings` (название, описание, язык по умолчанию, таймзона, стартовая страница, текст правил сайта в Markdown для `/rules`), `RegistrationSettings`, `ModerationSettings` (коды нарушений с диапазонами сроков бана), `AppearanceSettings` (акцентный цвет, логотип, режим темы по умолчанию) |
| **i18n** | Laravel lang. Старт: `ru`, `en`; структура под 11 языков DCMS. Выбор: профиль → cookie → `Accept-Language` → дефолт. Переключатель в футере и в настройках |
| **Модерация** | `bans` (full / readonly, срок, код нарушения, комментарий), `complaints` (polymorphic на любой контент). `CheckBan` middleware: full → `/banned` с причиной и сроком; readonly → `$user->canWrite() === false`, формы модулей проверяют через policy-хелпер `WriteAccess` |
| **Уведомления** | Laravel Notifications: `database` + `mail` + `broadcast`. Колокольчик в шапке, страница `/notifications`, настройки `/settings/notifications` (по типам). Это замена «личка как шина уведомлений» DCMS |
| **Mentions** | `MentionService::extract(html)` + `GET /api/users/mention-search?q=`; формат `<span data-mention data-user-id="42">@login</span>`, не зависит от редактора |
| **Онлайн** | `TrackOnline` обновляет `last_activity_at` не чаще раза в 60 с; онлайн = `last_activity_at > now - 10 min`. Гости — в `analytics` |
| **Аватары** | колонка `avatar_path`, диск `public`, ресайз `intervention/image` в 2 размера. Без medialibrary (YAGNI; пересмотрим в `files`) |
| **Аудит** | `activity_log` (spatie): действия админов, смена ролей, баны |
| **Профиль** | `/u/{login}`; `login_history` даёт редирект со старого логина; приватность `show_email`, `show_birthday`, `messages_from` (all/friends/none — читает `community`) |
| **Планировщик** | `ladcms:prune-unverified` (ежедневно), `activitylog:clean` (ежедневно) |

### 5.2. Схема БД

Только ядро; всё модульное — в модулях.

| Таблица | Поля сверх стандартных Laravel |
|---|---|
| `users` | `login` unique, `name`, `email` unique, `password`, `avatar_path` null, `sex` enum(male,female) null, `birth_date` null, `about` text null, `signature` text null, `language`, `timezone`, `last_activity_at` null, `registered_ip` null, `is_suspicious` bool, `suspicious_note` null, `show_email` bool, `show_birthday` bool, `messages_from` enum(all,friends,none), `two_factor_*` (Fortify), `email_verified_at`, `remember_token`, `deleted_at`, timestamps |
| `roles` (spatie +) | `level` tinyint unique null, `color` null, `is_default` bool, `is_system` bool |
| `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | spatie |
| `settings` | spatie: `group`, `name`, `payload` json, `locked` |
| `modules` | `name` unique, `version`, `installed_at`, `is_enabled` bool |
| `bans` | `user_id`, `banned_by_id`, `type` enum(full,readonly), `reason_code`, `comment` null, `starts_at`, `expires_at` null, timestamps |
| `complaints` | `user_id`, `target_user_id` null, `reportable_type`, `reportable_id`, `reason_code`, `comment`, `status` enum(pending,reviewed,resolved,rejected), `reviewed_by_id` null, `reviewed_at` null, timestamps |
| `login_history` | `user_id`, `old_login`, `changed_at` |
| `notifications`, `activity_log`, `sessions`, `cache`, `jobs`, `password_reset_tokens`, `personal_access_tokens` | стандартные |

Нет и почему: `users_online`/`guest_online` → `last_activity_at` + `analytics`; `log_of_*` → `analytics`; `users_tokens` → remember-me; `friends`/`mail`/`invations`/`reviews_users` → `community`/`reviews`; `balls` → модуль; `vk_*` → `socialite`; `mail_queue`/`mail_unsubscribe` → очереди + `news`; `users.rating` → `reviews`.

### 5.3. Роуты core

| Группа | Маршруты |
|---|---|
| Публичные | `/` (главная: слоты виджетов), `/u/{login}`, `/users` (список, поиск, сортировка, онлайн), `/banned`, `/rules`, `/notifications` |
| Fortify | `/login`, `/register`, `/forgot-password`, `/reset-password`, `/email/verify`, `/two-factor-challenge`, `/user/confirm-password` |
| Настройки | `/settings/profile`, `/settings/password`, `/settings/appearance`, `/settings/two-factor` (kit) + `/settings/privacy`, `/settings/notifications` |
| Админка `admin/*` | `dashboard`, `users`, `users/{id}` (роль, бан, подозрительный, удалить), `roles`, `roles/{id}/permissions`, `moderation/complaints`, `moderation/bans`, `settings/{group}`, `modules`, `activity` |
| API | `GET /api/users/mention-search` (`auth`, throttle; в группе `web`, т.е. полный путь `/api/users/mention-search`) |

Middleware: `admin` = `auth` + `can:admin.access` + `password.confirm` (TTL из настроек, по умолчанию 30 мин).

### 5.4. Permissions core

`admin.access`, `users.view`, `users.edit`, `users.ban`, `users.delete`, `users.assign-roles`, `roles.manage`, `settings.manage`, `modules.manage`, `complaints.review`, `activity.view`. Сидер привязывает их к ролям по умолчанию (moderator: `users.view`, `users.ban`, `complaints.review`; admin: всё кроме `roles.manage`, `modules.manage`; main_admin/creator: всё).

---

## 6. Фронтенд и дизайн

### 6.1. Структура `core/resources/js`

```
components/ui/*        # shadcn/ui (экспорт @ladcms/core/ui/*)
components/*           # DataTable, UserBadge (ник цветом роли), Avatar, Mention, NotificationBell, …
layouts/AppLayout.tsx  # публичный
layouts/AdminLayout.tsx
layouts/AuthLayout.tsx
layouts/SettingsLayout.tsx
hooks/{useAuth,useCan,useModules,useSettings,useLocale}.ts
pages/**               # страницы core
lib/                   # утилиты, wayfinder-обёртки
vite/index.ts          # @ladcms/vite
theme.css              # дизайн-токены
```

### 6.2. Публичный layout (решение: гибрид A + B)

- **Десктоп ≥ 1024px**: шапка (логотип, навигация из `HasNavigation`, поиск, иконки из `HasHeaderActions` + колокольчик, меню пользователя); **левая колонка** — слот `sidebar` (виджеты модулей: онлайн, мини-чат, новые файлы, друзья…); контент справа; футер (копирайт, правила, переключатель языка).
- **Планшет 768–1024**: колонка сужается, часть виджетов сворачивается.
- **Телефон < 1024 (до `lg`)**: шапка (логотип, поиск, иконки, меню пользователя), контент, **нижняя панель — только иконки** (пункты `HasNavigation` + «Я»; подписи в `aria-label`; если пунктов больше четырёх — первые три, «Ещё» (Sheet с остальными) и «Я»); виджеты сайдбара — свёрнутым блоком «Ещё» под контентом (на десктопе — левая колонка, рендерится один раз по `matchMedia`).
- Данные layout приходят shared-пропом `layout` (`navigation`, `header_actions`, `widgets.sidebar|main`, `site{name,url,description,logo_url,registration_open}`); на `admin.*` он `null`. Битые записи модулей (нет ключа/роута/файла компонента, исключение в `props`) пропускаются с однократным предупреждением в лог. Компоненты модулей — `<module>::<Name>` → `resources/js/<widgets|profile|header-actions>/<Name>.tsx` (плоско), ленивые, в error boundary.
- Доступность: ссылка «Перейти к содержимому» (`#main`), подписанные `nav`-ландмарки, `aria-current`, `aria-label` на иконках, `focus-visible`-кольца, вкладки профиля — `role=tablist/tab/tabpanel` со стрелками/Home/End. SEO: `<Seo>` выводит `title`, `description`, `og:*` (`og:url`/`og:image` абсолютные от `site.url`). Ошибки 403/404/405/500/503 — страница `core::error` через `ErrorPageRenderer` (в debug 500 остаётся страницей Laravel; 419 — редирект назад с тостом; несуществующий URL идёт через `Route::fallback` в группе `web`, POST на него — 405; ассет-подобные пути (`*.ico`, `*.map`, `*.php`, …) получают обычный 404 без layout и Inertia).
- Публичные тексты — `lang/{ru,en}/public.php` + `useT()`; карта `i18n` — обычный shared-проп на каждый Inertia-ответ (собирается заново на каждый запрос `Support/TranslationMap` в `SharePublicLayout`): `<module>::public.*` всех модулей везде плюс `<module>::admin.*` на `admin.*`; только строки текущей локали, плейсхолдеры подставляются за один проход.
- Главная страница: слот `main` с виджетами модулей (последние темы, новости, лента), порядок настраивается админом.

### 6.3. Визуальный стиль (решение: «тёплый, сообщество»)

- Тёплый фон (`#f7f6f3` light / `#1c1b1a` dark), карточки с `rounded-xl` и мягкой тенью, округлый шрифт (Nunito с системным фолбэком), цветной акцент (по умолчанию бирюза `teal-600`, меняется в `AppearanceSettings`), кнопки `rounded-full`.
- Реализуется переопределением токенов shadcn в `theme.css` (CSS-переменные `--background`, `--card`, `--primary`, `--radius` и т.д.). Модули используют только токены и компоненты UI-kit — тема применяется ко всему.
- Тёмная тема: переключатель «системная / светлая / тёмная» из starter kit (`/settings/appearance`), токены для обеих тем определены в `theme.css`.
- Модуль `themes` (волна 5) добавит смену наборов токенов и переопределение компонентов; core — одна дефолтная тема.

### 6.4. Админка (решение: два уровня с hover)

- Узкая полоса иконок слева: разделы core (дашборд, пользователи, роли, модерация, настройки, модули, журнал) + по иконке на модуль с `HasAdminPanel`. Меню собирает `AdminMenu` (с учётом permissions, бейджа жалоб и групп настроек всех модулей) и отдаёт shared-пропом `admin` только на роутах `admin.*`.
- При **наведении** на иконку (десктоп, `pointer: fine`) второй сайдбар (`SectionPanel`) переключается на подменю этого раздела, через 150 мс после ухода курсора возвращается к закреплённому; **клик** фиксирует раздел и переходит на его первый пункт. На тач-устройствах — по тапу, на мобильных (< `lg`) меню — выезжающая панель с аккордеоном. Tooltip с названием.
- Контент: breadcrumbs, заголовок, действия. Заголовок и крошки страницы (`Page.layout`) — ключи `<module>::admin.*`, перевод делает `AdminLayout`. Таблицы — единый `DataTable` (сортировка и пагинация через query-параметры, `TableQuery` на сервере: белый список сортировок, экранированный поиск); массовых действий в v1 нет.
- Формы настроек генерируются из классов `Settings` (`SettingsSchema`): тип поля по PHP-типу свойства, атрибут `#[Field]` уточняет тип/источник options/описание; страница `admin.settings.edit` — вкладка на каждую группу всех модулей.
- Страница «Модули»: список из `modules` + `ModuleRegistry` (версия, статус, ошибки загрузки, зависимости, включить/выключить, подсказка про `ladcms:update`). Выключение модуля вступает в силу при следующей загрузке; Octane и воркеры очередей нужно перезапустить.
- i18n админки: тексты лежат в `lang/{ru,en}/admin.php` каждого модуля. На `admin.*` в shared-проп `i18n` (см. выше: обычный проп на каждый запрос, `Support/TranslationMap` в `SharePublicLayout`) дополнительно попадает пространство `admin` всех зарегистрированных модулей — плоская карта `{"<module>::admin.<ключ>": "перевод"}` для текущей локали (`Arr::dot`). На клиенте `useT()` (`@ladcms/core/lib/i18n`) подставляет `:name`-плейсхолдеры и при отсутствии ключа возвращает сам ключ; названия ролей — `core::admin.roles.names.<name>` с откатом на сырое имя. Тексты исключений Actions остаются английскими.
- Покрытие: `AdminSmokeTest` обходит все GET-роуты `admin.*` под creator и проверяет, что компонент существует на диске. JS пакетов линтуется вместе с приложением (`npm run check:fix`), `components/ui/**` исключён.

### 6.5. Профиль (решение: шапка + вкладки; «Обзор» = карточка + блоки)

- Шапка: обложка (цвет акцента, без загрузки картинки в v1), аватар, `name`, роль цветом, `@login`, онлайн-статус, действия (`HasProfileActions` модулей: «Написать», «В друзья»; меню «⋯» с модераторскими действиями по permission), счётчики от модулей.
- Вкладки: «Обзор» (core) + вкладки модулей из `HasProfileSections.tab` (ключ вкладки = имя модуля, `?tab=<module>`; данные вкладки — необязательный `profileTabData()`, считаются только при открытой вкладке). Доступно: стрелки/Home/End переключают вкладку.
- Приватность: чужие данные — только через `PublicUserResource`/`ProfileResource`: `email` и дата рождения видны при `show_email`/`show_birthday` или владельцу; подозрительность, сырая активность и причина бана не отдаются (только публичный `is_banned`). `auth.user.roles` — список `{name, color}`; `/u/{старый_логин}` → 301, удалённый/несуществующий → 404.
- «Обзор»: слева карточка анкеты (регистрация, пол, д.р., язык, последний визит — с учётом приватности), справа блоки модулей `HasProfileSections.overview` («последние 5 + все →», где «все →» переключает на вкладку модуля).
- Телефон: шапка, вкладки горизонтальным скроллом, карточка сверху, блоки ниже.

---

## 7. Тестирование

| Уровень | Как |
|---|---|
| Пакет изолированно | Testbench `TestCase` с `CoreServiceProvider` (+ провайдер модуля), SQLite in-memory, Pest 5 |
| Actions | unit, без HTTP — обязательны для каждого Action |
| HTTP/Inertia | feature: `assertInertia()` — компонент, props, доступ по permission/бану |
| Контракт модуля | в `core/tests/Fixtures/DummyModule`: регистрация роутов/меню/permissions/страниц; идемпотентность `ladcms:install dummy`; поведение при неверной `coreVersion` и отсутствующей зависимости |
| Auth-флоу | feature-тесты из starter kit адаптируются под `login` |
| Frontend | `tsc`, ESLint; компонентные тесты — когда появится логика; браузерные (Pest 5) — с волны 2 |

---

## 8. Ошибки и граничные случаи

- Несовместимый `coreVersion` или отсутствующая зависимость → модуль не бутится, лог + «Модули» в админке, сайт жив.
- `ladcms:install <module>` прерван → повторный запуск доделывает (все шаги идемпотентны, запись в `modules` — последняя).
- Удалённый модуль → `ModuleRegistry` отдаёт только живые; меню/виджеты/вкладки профиля мёртвых не рендерятся; его таблицы остаются до `ladcms:uninstall`.
- Забаненный: full → `/banned`; readonly → `canWrite()` false, формы модулей обязаны проверять.
- Попытка модератора действовать на пользователя с `level` ≥ своего → 403 через `outranks()`.
- Удаление системной роли или последнего `creator` → запрещено.
- Смена `login` → старый в `login_history`, `/u/{old}` → 301 на новый; повторное использование чужого старого логина запрещено 30 дней.
- Пользователь удалён (soft) → профиль 404, контент модулей показывает «Удалённый пользователь» через `UserBadge`.

---

## 9. Что убрано из DCMS и чем заменено

| Было | Замена |
|---|---|
| Типы браузеров light/mobile/full, субдомены, 3 темы | один адаптивный layout |
| INI-конфиги, запись в код на лету | `config/` + `laravel-settings` в БД; меню/виджеты — конфигурация модулей + настройки |
| Псевдо-крон в запросе, очередь писем в запросе | `Schedule` + crontab, очереди Redis |
| Капча | rate limiting (Fortify), email-верификация, `password.confirm`; при необходимости — модуль Turnstile/hCaptcha |
| Повторный логин в админку с капчей | `password.confirm` |
| BBCode, смайлы-картинки, `[hide]` | HTML от редактора + санитайзер (`EditorInterface::sanitize`), Unicode emoji, `[hide]` — расширение редактора в `balls` |
| Авто-обновления, проверка версий | `composer update`; уведомление о новых версиях — позже |
| WebMoney, donate | нет; отдельный модуль `payments` при необходимости |
| `time_shift` | `users.timezone` |
| Спрайт иконок | Lucide |
| Личка как шина уведомлений | Laravel Notifications; личка — `community` |
| Логи визитов/рефереров в ядре | `analytics` |
| Инвайты, баллы, VK, друзья, отзывы в ядре | модули |
| md5-пароли с солью, `users_tokens` | bcrypt/argon2, remember-me, Sanctum |
| Браузерный инсталлер `/install/` | `ladcms:install` |

---

## 10. Инвентарь DCMS (справочно, для спек модулей)

Источник: `/home/vadimushka_d/Projects/personal/dcms` (DCMS 7.5.2.154, ~15k LOC движка). Запущен в Docker на `http://127.0.0.1:8090/`.

| Раздел DCMS | Что делает | Куда идёт |
|---|---|---|
| `/forum/` (40 файлов, 3.6k LOC) | категории → разделы → темы → сообщения; `group_show/write/edit` на каждом уровне; закреплённые темы, модератор темы, история правок, рейтинг ±, вложения, одно голосование на тему (`v1..v10`), поиск со стеммером, «мои темы», прочитанность, статистика | `forum`, `polls` |
| `/files/` + классы (≈3.4k LOC) | дерево папок на диске, **метаданные в INI-сайдкарах**, скриншоты, ID3, рейтинг, комментарии, счётчик скачиваний, права папок; корни `.downloads`, `.obmen`, `.forum`, `.photos`, `.avatars`, `.bbcode` | `files` (метаданные → БД) |
| `/photos/` | альбомы = папки `.photos/<user>/<album>`, комментарии общие с файлами | `photos` |
| `/news/` | CRUD, комментарии, рассылка всем через `mail_queue`, отписка | `news` |
| `/chat_mini/` | шаутбокс, удаление админом | `chat` (Reverb) |
| `pages/*` пользователи | регистрация (активация, правила, инвайты), VK OAuth, восстановление, профиль, смена логина, настройки по типам браузера, список пользователей, онлайн, история логинов, бан | `core` (+ `socialite`, `community`) |
| `my.mail.php` | плоская личка + шина уведомлений | `community` + Notifications |
| `my.friends.php` | друзья с подтверждением | `community` |
| `profile.reviews.php` | отзывы с float-рейтингом, `users.rating` = сумма, рейтинг форума пишет сюда же | `reviews` |
| `my.invitations.php` | инвайты за баллы | `community` + `balls` |
| `complaint.php`, коды нарушений | жалобы, коды с диапазонами сроков бана | `core` |
| баллы (`users.balls`, `sys.balls.ini`) | начисления за чат/пост/тему/файл/комментарий, траты на минус-рейтинг/инвайт/смену логина/`[hide]` | `balls` |
| статистика, логи визитов/рефереров/авторизаций, журнал админов | | `analytics` (журнал админов → `core` activity_log) |
| `/dpanel/` (37 файлов) | настройки, темы, виджеты, редактор меню, смайлы, пользователи, подозрительные, DB-tools, логи | `core` админка + модули |
| виджеты (`sys/widgets`) | chat_mini, feed, files, forum, main_menu, news, obmen, user_menu, users; кэш по группе/языку/браузеру | `HasWidgets` модулей, `feed` |
| темы (3 шт.), API (`sys/api.php`), BBCode, капча, браузер-детект, псевдо-крон, инсталлер | | см. §9 |

Схема БД DCMS (38 таблиц, int-таймстампы, `enum('0','1')`-булевы, без FK) — в `sys/preinstall/table.*.structure.json`; пригодится импортёру, если он понадобится.

---

## 11. Открытые вопросы (не блокируют волны 0–1)

- SSR для Inertia — включать ли для публичных страниц ради SEO (решить в волне 2 по `news`).
- Глобальный поиск (`HasSearch`): Scout + драйвер БД или Meilisearch — решить в волне 3 по `forum`.
- Хранение файлов (`files`): свой слой vs medialibrary — волна 4.
- Captcha-модуль — только если появится спам.
