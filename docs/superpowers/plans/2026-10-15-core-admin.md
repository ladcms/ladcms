# LADCMS Core: админка (план 2b) — план реализации

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Построить админ-панель core: двухуровневый сайдбар (иконки разделов/модулей + подменю по hover), дизайн-токены «тёплой» темы, страницы Дашборд, Пользователи (список/карточка/действия), Роли и права, Модерация (жалобы, баны), Настройки (автоформы из классов `Settings`), Модули, Журнал действий — на Inertia 3 + React 19 поверх домена плана 2a, с контроллерами-обёртками над Actions и feature-тестами.

**Architecture:** Админ-роуты core живут в `packages/core/routes/admin.php` с префиксом `/admin` (core переопределяет префикс модульного соглашения `admin/{module}`), группа middleware `['web','auth','admin','password.confirm']`. Меню собирается на сервере (`AdminMenu`: секции core + `HasAdminPanel` модулей) и шарится в Inertia только на админ-страницах. Таблицы — серверные (пагинация/сортировка/фильтры через query string), без TanStack. Настройки — схема формы из рефлексии класса `Settings` + атрибут `#[Field]`. React-страницы — в `packages/core/resources/js/pages/admin/**`, общий layout `AdminLayout` и UI-kit в `packages/core/resources/js/{layouts,components}`; импорт `@ladcms/core/...`. Приложение добавляет `case name.startsWith('core::admin/')` в `layout()`.

**Tech Stack:** Laravel 13, Inertia 3 (`Inertia::render/share/flash`, `assertInertia`), React 19 + TS strict, Tailwind 4, shadcn/ui (+ `table`, `pagination`, `tabs`, `switch`, `textarea`, `alert-dialog`, `popover`, `command` через `npx shadcn@latest add`), Wayfinder, lucide-react, sonner, Pest 5 / Testbench.

**Spec:** `docs/superpowers/specs/2026-10-14-ladcms-foundation-and-core-design.md` — §5.3 (админ-роуты), §5.4 (permissions), §6.1 (структура JS core), §6.3 (стиль), §6.4 (админка), §8.

## Global Constraints

- Все страницы core в админке именуются `core::admin/<section>/<page>` (файлы `packages/core/resources/js/pages/admin/<section>/<page>.tsx`).
- Admin-роуты core: префикс `/admin`, имена `admin.<section>.<action>` (например `admin.users.index`), middleware `['web','auth','admin','password.confirm']`; TTL `password.confirm` берётся из `ModerationSettings::$password_confirm_minutes`.
- Контроллеры тонкие: валидация (FormRequest), вызов Action, `Inertia::flash('toast', ['type' => …, 'message' => …])`, редирект. Исключения → HTTP: `AuthorizationException` 403, `ValidationException` 422 (стандарт), `InvalidArgumentException` → 422 с сообщением в `errors.general`.
- Permissions из плана 2a — единственный источник прав: `admin.access` (вход), `users.view/edit/ban/delete/assign-roles`, `roles.manage`, `settings.manage`, `modules.manage`, `complaints.review`, `activity.view`. Пункты меню и кнопки скрываются по `can`, роуты защищены middleware `can:`.
- UI-текст — через `__()`/lang-файлы core (`lang/ru`, `lang/en`), ключи `core::admin.*`; на фронте — переводы приходят в props/shared (`Inertia::share('i18n.admin', fn () => __('core::admin'))`).
- JS: `vp check` (lint+fmt) и `tsc --noEmit` чистые; сборка `npm run build` зелёная; shadcn-компоненты в `packages/core/resources/js/components/ui/*` (не в app) — в `vite.config.ts` lint/fmt ignore добавить `vendor/ladcms/*/resources/js/components/ui/*`.
- PHP: strict_types, Pint/PHPStan/Rector чистые; Pest-тесты страниц через `assertInertia` + тесты доступа (гость → login, без права → 403).
- Команды: `vendor/bin/pest` (корень), `cd app && vendor/bin/sail pest|npm run check:fix|npm run types:check|npm run build|artisan wayfinder:generate --with-form`.
- Коммит после каждой задачи, trailer `Co-Authored-By: <модель-автор> <noreply@anthropic.com>`.

## Review Focus

1. Пользователь с `admin.access`, но без `users.ban`, открывает карточку пользователя — кнопки «Забанить/Снять бан» скрыты, а прямой POST на `admin.users.ban` отвечает 403. — тесты в Task 6.
2. Модератор (level 2) пытается через форму назначить роль `admin` пользователю — сервер отвечает 403 (политика), форма показывает ошибку, роли не изменены. — тест в Task 6.
3. Настройка `ModerationSettings::$violation_codes` редактируется как JSON/таблица — невалидная структура (нет `code`) отклоняется 422, валидная сохраняется и сразу применяется в `BanUser`. — тест в Task 9.
4. Поиск в таблице пользователей по строке с `%`/`_` и кириллицей не ломает запрос и не делает wildcard-совпадений. — тест в Task 5.
5. На `/admin` без подтверждения пароля в пределах TTL — редирект на `password.confirm`; после подтверждения — доступ; TTL из настроек (не из `config/auth.php`). — тест в Task 2.

---

### Task 1: Дизайн-токены «тёплой» темы и UI-kit core

**Files:**
- Create: `packages/core/resources/js/theme.css`, `packages/core/resources/js/components/ui/{table,pagination,tabs,switch,textarea,alert-dialog,popover,command}.tsx` (shadcn), `packages/core/resources/js/lib/utils.ts` (`cn`), `packages/core/resources/js/components/{data-table.tsx,page-header.tsx,confirm-dialog.tsx,empty-state.tsx}`
- Modify: `app/resources/css/app.css` (`@import '../../vendor/ladcms/core/resources/js/theme.css';` после импорта tailwind; удалить kit-значения `:root/.dark`, оставив их в `theme.css`), `app/vite.config.ts` (lint/fmt ignore `vendor/ladcms/*/resources/js/components/ui/*`), `app/components.json` (aliases на `@ladcms/core/components/ui`? — нет: компоненты генерируются в app и переносятся; см. шаги)

**Interfaces:**
- `theme.css`: переопределяет shadcn-токены: light `--background: oklch(0.975 0.004 80)` (тёплый `#f7f6f3`), `--card: oklch(1 0 0)`, `--primary: oklch(0.55 0.11 185)` (teal-600), `--primary-foreground: white`, `--radius: 0.75rem`; dark `--background: oklch(0.19 0.006 60)` (`#1c1b1a`), `--card: oklch(0.23 0.006 60)`, `--primary: oklch(0.78 0.13 180)`; `--font-sans: 'Nunito', ui-sans-serif, system-ui, sans-serif`; sidebar-токены согласованы (sidebar = card). Шрифт Nunito через `bunny('Nunito', {weights: [400, 600, 700]})` в `vite.config.ts` вместо Instrument Sans.
- `DataTable<T>` (серверная): props `{ columns: Column<T>[]; rows: T[]; pagination: Paginator; sort?: {column: string; direction: 'asc'|'desc'}; onSort?(column); emptyText? }` где `Column<T> = { key: string; label: string; sortable?: boolean; className?: string; render?(row: T): ReactNode }`, `Paginator = { current_page: number; last_page: number; per_page: number; total: number; links: {url: string|null; label: string; active: boolean}[] }` (формат `LengthAwarePaginator::toArray()`); сортировка/страницы — ссылки `<Link preserveState preserveScroll>` на тот же URL с `?sort=&direction=&page=`.
- `PageHeader { title; description?; actions?: ReactNode }`, `ConfirmDialog { trigger: ReactNode; title; description; confirmLabel; destructive?; onConfirm() }` (на `alert-dialog`), `EmptyState { icon?; title; description? }`.

- [ ] **Step 1: Сгенерировать shadcn-компоненты** — из `app/`: `vendor/bin/sail npx shadcn@latest add table pagination tabs switch textarea alert-dialog popover command` (они появятся в `app/resources/js/components/ui/`), затем переместить новые файлы в `packages/core/resources/js/components/ui/` и поправить импорты `@/lib/utils` → `@ladcms/core/lib/utils` (создать `lib/utils.ts` в core с `cn`). Kit-компоненты, которые уже есть в app (`button`, `input`, `card`, `dialog`, `dropdown-menu`, `badge`, `avatar`, `tooltip`, `select`, `checkbox`, `label`, `separator`, `sheet`, `skeleton`, `sidebar`, `breadcrumb`, `collapsible`, `alert`, `sonner`) — **скопировать** в core `components/ui/` тем же способом (core не должен импортировать `@/components/ui/*` из приложения: страницы модулей живут в пакетах). Приложение продолжает использовать свои копии для kit-страниц.
- [ ] **Step 2: `theme.css`** с токенами из Interfaces (light `:root`, `.dark`), подключить в `app.css`, заменить шрифт в `vite.config.ts`; `npm run build`, визуально проверить `http://localhost/` (welcome в тёплых тонах) — `curl` на наличие `--primary` в собранном CSS.
- [ ] **Step 3: `DataTable`, `PageHeader`, `ConfirmDialog`, `EmptyState`** по Interfaces (TS strict, без `any`).
- [ ] **Step 4: Проверки** — `vendor/bin/sail npm run check:fix && npm run types:check && npm run build`. **Commit** — `feat(ui): warm theme tokens and core UI kit (data table, dialogs)`.

---

### Task 2: Админ-группа роутов, `AdminMenu`, `HasAdminPanel` для core, `password.confirm` с TTL из настроек

**Files:**
- Create: `packages/core/src/Admin/AdminMenu.php`, `packages/core/src/Admin/MenuItem.php`, `packages/core/src/Http/Middleware/ConfirmPasswordWithSettingsTtl.php`, `packages/core/routes/admin.php`, `packages/core/src/Http/Controllers/Admin/DashboardController.php`, `packages/core/resources/js/pages/admin/dashboard.tsx` (заглушка; наполнение в Task 10), `packages/core/tests/Feature/Admin/AdminAccessTest.php`, `packages/core/tests/Unit/AdminMenuTest.php`
- Modify: `packages/core/src/Module/ModuleServiceProvider.php` (хуки `adminRoutePrefix()`/`adminRouteNamePrefix()`/`adminMiddleware()`), `packages/core/src/CoreServiceProvider.php` (`implements HasAdminPanel`, share `admin.menu` на админ-роутах), `packages/core/lang/{ru,en}/admin.php` (create)

**Interfaces:**
- `ModuleServiceProvider`: `protected function adminRoutePrefix(): string { return 'admin/'.$this->name(); }`, `adminRouteNamePrefix(): string { return 'admin.'.$this->name().'.'; }`, `adminMiddleware(): array { return ['web','auth','admin','ladcms.password-confirm']; }` — `loadModuleRoutes()` использует их. Core переопределяет: префикс `admin`, имена `admin.`.
- `ConfirmPasswordWithSettingsTtl` (алиас `ladcms.password-confirm`): обёртка над `RequirePassword` — передаёт `passwordTimeoutSeconds = ModerationSettings::$password_confirm_minutes * 60`, редирект на route `password.confirm` (если роут не зарегистрирован, например в Testbench без Fortify — fallback: `abort(403)`? нет — в тестах регистрируем заглушку роута `password.confirm`).
- `MenuItem` readonly: `key`, `label` (переведённый), `icon` (lucide name, kebab-case), `route` (имя), `url`, `order`, `permission` (?string), `children: list<MenuItem>`, `active: bool`.
- `AdminMenu::build(User $user, Request $request): list<MenuItem>` — core-секции: `dashboard` (`layout-dashboard`), `users` (`users`, `users.view`, дети: all, suspicious), `roles` (`shield`, `roles.manage`), `moderation` (`gavel`, `complaints.review`, дети: complaints, bans), `settings` (`settings`, `settings.manage`, дети по группам настроек всех `HasSettings`-модулей), `modules` (`puzzle`, `modules.manage`), `activity` (`scroll-text`, `activity.view`); затем для каждого модуля `HasAdminPanel` (кроме core) — секция с `label` = имя модуля и его `adminMenu()` детьми. Фильтрует по `$user->can($permission)`; `active` по `request()->routeIs(route.'*')` или url-префиксу.
- `CoreServiceProvider::adminMenu()` возвращает те же core-секции в формате `HasAdminPanel` (single source: `AdminMenu` читает их через провайдер).
- Inertia share на админ-роутах: `admin: { menu: MenuItem[], user: CurrentUserResource }` (через `Inertia::share('admin', fn () => request()->routeIs('admin.*') ? [...] : null)`).

- [ ] **Step 1: Тесты (падающие)** — `AdminAccessTest`: гость `/admin` → redirect `login`; пользователь без `admin.access` → 403; модератор без подтверждённого пароля → redirect на `password.confirm` (в тесте: `Route::get('/user/confirm-password')->name('password.confirm')`); с `session(['auth.password_confirmed_at' => time()])` → 200 и `assertInertia` `core::admin/dashboard` с `admin.menu` где есть `moderation`, нет `roles`; TTL: `ModerationSettings` `password_confirm_minutes = 1`, подтверждение 2 минуты назад → redirect (Review Focus 5). `AdminMenuTest`: creator видит все 7 секций; `settings.children` содержит `general/registration/moderation/appearance`; Dummy-модуль с `HasAdminPanel` даёт секцию `dummy` с его пунктами; `active` помечается для текущего роута.
- [ ] **Step 2: Реализация** по Interfaces; `routes/admin.php` core: `Route::get('/', DashboardController::class)->name('dashboard');` (остальные роуты добавляют следующие задачи); `DashboardController` → `Inertia::render('core::admin/dashboard', ['stats' => []])` (заглушка); `lang/*/admin.php` — ключи меню. Фикстура `DummyServiceProvider implements HasAdminPanel` с одним пунктом (`label 'dummy::messages.admin'`, `route 'admin.dummy.index'`, `icon 'box'`).
- [ ] **Step 3: PASS, гейты, Commit** — `feat(core): admin route group, menu builder and password confirmation TTL`.

---

### Task 3: `AdminLayout` — двухуровневый сайдбар с hover-подменю

**Files:**
- Create: `packages/core/resources/js/layouts/admin-layout.tsx`, `packages/core/resources/js/components/admin/{icon-rail.tsx,section-panel.tsx,admin-header.tsx,admin-breadcrumbs.tsx}`, `packages/core/resources/js/types/admin.ts`, `packages/core/resources/js/lib/icons.ts` (маппинг kebab-имени → lucide компонент для набора используемых иконок)
- Modify: `app/resources/js/app.tsx` (`case name.startsWith('core::admin/') || /^[a-z0-9-]+::admin\//.test(name): return AdminLayout`), `app/resources/js/types/global.d.ts` (shared `admin?: AdminShared`)

**Interfaces:**
- `types/admin.ts`: `MenuItem`, `AdminShared { menu: MenuItem[]; user: CurrentUser }`, `Paginator`, `Toast`.
- `AdminLayout { children; title?: string; breadcrumbs?: {title; href?}[] }`: слева `IconRail` (ширина 3.5rem; кнопка-иконка на секцию; `aria-label`; активная подсвечена; **hover** на десктопе (`pointer: fine`) переключает `SectionPanel` на секцию под курсором, клик — переходит на `children[0].url ?? url` и фиксирует; на тач — только клик открывает панель); `SectionPanel` (ширина 14rem; заголовок секции, список `children` как `Link` с `active`; на телефоне — `Sheet`); контент: `AdminHeader` (`SidebarTrigger`-аналог для мобильного, breadcrumbs, справа колокольчик-заглушка и `NavUser`-подобное меню: профиль, выход `POST logout`), `<main class="p-6">`.
- Состояние «закреплённая секция» = секция активного роута; hover временно показывает другую; `mouseleave` на обеих панелях → возврат к закреплённой через 150 мс.

- [ ] **Step 1: Реализовать** компоненты и layout; подключить в `app.tsx`; dashboard-страница использует `Page.layout = { title: 'Dashboard' }`-паттерн kit (статическое свойство) ИЛИ layout читает `usePage().props.title` — выбрать: страница экспортирует `Page.layout = {...}`, как в kit.
- [ ] **Step 2: Проверка** — `npm run check:fix && types:check && build`; в браузере `http://localhost/admin` под создателем (создать через `sail artisan ladcms:install` если БД dev пустая: `--site-name=Dev --admin-login=admin --admin-name=Admin --admin-email=admin@example.com --admin-password=<12+ символов>`; не печатать пароль в отчёт) — hover переключает подменю, клик фиксирует, на ширине 400px рельса сворачивается в Sheet. Скриншот не нужен; описать проверку в отчёте.
- [ ] **Step 3: Commit** — `feat(ui): two-level admin layout with hover section panel`.

---

### Task 4: Серверная таблица: `TableQuery` и `UserResource`

**Files:**
- Create: `packages/core/src/Admin/TableQuery.php`, `packages/core/src/Http/Resources/Admin/{UserListResource,UserDetailResource,RoleResource,BanResource,ComplaintResource,ActivityResource,ModuleResource}.php`, `packages/core/tests/Unit/TableQueryTest.php`

**Interfaces:**
- `TableQuery::apply(Builder $q, Request $r, array $sortable, string $defaultSort, array $searchable = []): Builder` — `?sort=<col>&direction=asc|desc` (только из `$sortable`, иначе дефолт), `?q=` → `where(fn) orWhere col LIKE %escaped%` по `$searchable` (escape `%`, `_`, `!` с `ESCAPE '!'`, как в `MentionSearchController`), возвращает query; `TableQuery::paginate(Builder, Request, int $perPage = 25): LengthAwarePaginator` → `->paginate($perPage)->withQueryString()`.
- Ресурсы: `UserListResource` → `id, login, name, display_name, email, avatar_url, roles: [{name, color, level}], is_online, is_banned, is_suspicious, created_at, last_activity_at`; `UserDetailResource` → + `sex, birth_date, about, signature, language, timezone, registered_ip, suspicious_note, email_verified_at, active_ban: BanResource|null, bans_count, complaints_count`; остальные — по колонкам моделей + связанные логины (`banned_by: {id, login}` и т.п.).

- [ ] **Step 1: Тесты (падающие)** — `TableQueryTest`: sort по разрешённой колонке применяется, по запрещённой — дефолт; `q=ab%` ищет литеральный `%` (Review Focus 4), кириллица `q=вад` находит `вадим`; `paginate` сохраняет query string в ссылках.
- [ ] **Step 2: Реализация.** — [ ] **Step 3: PASS, гейты, Commit** — `feat(core): table query helper and admin resources`.

---

### Task 5: Пользователи — список

**Files:**
- Create: `packages/core/src/Http/Controllers/Admin/UsersController.php` (`index`), `packages/core/resources/js/pages/admin/users/index.tsx`, `packages/core/tests/Feature/Admin/UsersIndexTest.php`
- Modify: `packages/core/routes/admin.php`

**Interfaces:**
- `GET /admin/users` (`admin.users.index`, `can:users.view`): props `users: {data: UserListResource[], ...paginator}`, `filters: {q, role, status: 'all'|'banned'|'suspicious'|'online', sort, direction}`, `roles: RoleResource[]`. Фильтры: `role` → `whereHas('roles', name)`, `status` → соответствующие скоупы. Сортируемые: `login, name, email, created_at, last_activity_at`; поиск: `login, name, email`.
- Страница: `PageHeader` («Пользователи», счётчик), панель фильтров (поиск с debounce 300 мс → `router.get(url, {q…}, {preserveState, replace})`, select роли, select статуса), `DataTable` с колонками аватар+логин (ссылка на карточку), имя, email, роли (бейджи цветом роли), статус (онлайн/бан/подозрительный), регистрация, последняя активность.

- [ ] **Step 1: Тесты (падающие)** — доступ (гость/без права/с правом), props структура, фильтр `status=banned` возвращает только забаненных, `q` ищет по email, сортировка по `created_at desc` — порядок, `role=moderator`.
- [ ] **Step 2: Реализация** (контроллер через `TableQuery`, страница). — [ ] **Step 3: PASS, `npm run check:fix/types:check/build`, Commit** — `feat(admin): users list with filters and sorting`.

---

### Task 6: Пользователь — карточка и действия (роли, бан, подозрительный, удаление, смена логина)

**Files:**
- Create: `packages/core/src/Http/Controllers/Admin/{UserController,UserBanController,UserRolesController}.php`, `packages/core/src/Http/Requests/Admin/{UpdateUserRequest,BanUserRequest,AssignRolesRequest,MarkSuspiciousRequest,ChangeLoginRequest}.php`, `packages/core/src/Actions/Users/UpdateUserProfile.php`, `packages/core/resources/js/pages/admin/users/show.tsx`, `packages/core/resources/js/components/admin/users/{profile-form.tsx,roles-form.tsx,ban-form.tsx,danger-zone.tsx,bans-history.tsx}`, `packages/core/tests/Feature/Admin/UserActionsTest.php`, `packages/core/tests/Unit/Actions/UpdateUserProfileTest.php`
- Modify: `packages/core/routes/admin.php`

**Interfaces:**
- Роуты: `GET /admin/users/{user}` (`admin.users.show`, `users.view`), `PATCH /admin/users/{user}` (`admin.users.update`, policy `update`), `PATCH /admin/users/{user}/login` (`admin.users.login`), `PUT /admin/users/{user}/roles` (`admin.users.roles`, policy `assignRoles`), `POST /admin/users/{user}/ban` (`admin.users.ban`, policy `ban`), `DELETE /admin/users/{user}/ban/{ban}` (`admin.users.ban.lift`), `PATCH /admin/users/{user}/suspicious` (`admin.users.suspicious`), `DELETE /admin/users/{user}` (`admin.users.destroy`, policy `delete`).
- `UpdateUserProfile::handle(User $actor, User $target, array $data): User` — policy `update`; поля `name, email, sex, birth_date, about, signature, language, timezone` (валидация в FormRequest; email unique ignore self); audit `user.updated`.
- `show` props: `user: UserDetailResource`, `roles: RoleResource[]` (все), `assignable_roles: string[]` (level < actor.level или все для creator), `violation_codes` (из настроек), `bans: BanResource[]` (история), `can: {update, assignRoles, ban, delete, markSuspicious}` (из Gate) — кнопки/вкладки по `can`.
- Страница: шапка с аватаром/ролями/статусами; `Tabs`: Профиль (форма), Роли (чекбоксы ролей; недоступные — disabled с подсказкой), Модерация (форма бана: тип, код из списка, срок — дни или «бессрочно» если код позволяет, комментарий; список банов с «снять»; подозрительный + заметка), Опасная зона (смена логина, удаление с `ConfirmDialog`).
- Контроллеры: ловят `AuthorizationException` → 403; `InvalidArgumentException` → `back()->withErrors(['general' => msg])`; успех → `Inertia::flash('toast', ['type' => 'success', 'message' => __('core::admin.saved')])`, `back()`.

- [ ] **Step 1: Тесты (падающие)** — `UserActionsTest`: show props и `can` для moderator vs admin (Review Focus 1: без `users.ban` → `can.ban=false` и POST ban → 403); moderator назначает `admin` → 403, роли не изменены (Review Focus 2); admin назначает `moderator` → 200, роль есть; бан с валидным кодом → активный бан + toast flash (`assertInertiaFlash`? — проверить сессию `session('_flash')`/Inertia flash API: использовать `->assertSessionHas` на ключ, который применяет Inertia 3 flash; реализовать по факту адаптера); бан с неизвестным кодом → 422; снятие бана; удаление последнего creator → 422 с `errors.general`; смена логина на занятый → 422.
- [ ] **Step 2: Реализация** (Actions уже есть: `AssignRoles`, `BanUser`, `LiftBan`, `MarkSuspicious`, `DeleteUser`, `ChangeLogin`; новый `UpdateUserProfile`). — [ ] **Step 3: PASS (root + `npm` гейты), Commit** — `feat(admin): user detail page with moderation and role actions`.

---

### Task 7: Роли и права

**Files:**
- Create: `packages/core/src/Http/Controllers/Admin/RolesController.php`, `packages/core/src/Http/Requests/Admin/{StoreRoleRequest,UpdateRoleRequest,SyncRolePermissionsRequest}.php`, `packages/core/src/Actions/Roles/{CreateRole,UpdateRole,DeleteRole,SyncRolePermissions}.php`, `packages/core/resources/js/pages/admin/roles/{index,edit}.tsx`, `packages/core/tests/Feature/Admin/RolesTest.php`, `packages/core/tests/Unit/Actions/RoleActionsTest.php`
- Modify: `packages/core/routes/admin.php`

**Interfaces:**
- Роуты (`roles.manage`): `GET /admin/roles` (index: роли с `users_count`, `level`, `color`, `is_system`, `permissions_count`), `POST /admin/roles` (store: `name` regex `^[a-z][a-z0-9_]*$` unique, `level` int 0–6 unique nullable, `color` hex nullable), `GET /admin/roles/{role}/edit` (матрица permissions: все permissions, сгруппированные по префиксу до точки = модуль, с переводами `<module>::permissions.<name>`), `PUT /admin/roles/{role}` (name/level/color — для системных ролей `name` и `level` неизменяемы), `PUT /admin/roles/{role}/permissions` (`permissions: string[]`), `DELETE /admin/roles/{role}` (policy `delete`: не системная; пользователи с этой ролью её теряют).
- Actions: `CreateRole::handle(User $actor, array $data): Role`, `UpdateRole`, `DeleteRole` (policy + `is_system` защита + audit `role.*`), `SyncRolePermissions::handle(User $actor, Role $role, array $permissionNames): Role` (нельзя выдать роли permissions, которых нет у самого актора, кроме creator — иначе эскалация через роль; `syncPermissions`; audit).

- [ ] **Step 1: Тесты (падающие)** — CRUD, системную роль нельзя удалить/переименовать (422/403), admin (без `roles.manage`) → 403, main_admin не может выдать роли permission, которого нет у него (например `modules.manage` есть у main_admin — взять случай super_moderator с ручным `roles.manage`: не может выдать `users.delete`) → 403; матрица сгруппирована по модулю.
- [ ] **Step 2: Реализация.** — [ ] **Step 3: PASS, гейты, Commit** — `feat(admin): roles and permissions management`.

---

### Task 8: Модерация — жалобы и баны

**Files:**
- Create: `packages/core/src/Http/Controllers/Admin/{ComplaintsController,BansController}.php`, `packages/core/resources/js/pages/admin/moderation/{complaints,bans}.tsx`, `packages/core/tests/Feature/Admin/ModerationTest.php`
- Modify: `packages/core/routes/admin.php`

**Interfaces:**
- `GET /admin/moderation/complaints?status=pending|reviewed|resolved|rejected|all` (`complaints.review`): таблица (автор, цель/объект — `reportable_type` короткое имя + id, код, комментарий, статус, создано), действие «рассмотреть» → `PATCH /admin/moderation/complaints/{complaint}` body `status` → `ReviewComplaint`; счётчик pending в меню (бейдж `badge` в `MenuItem`: `AdminMenu` для `moderation` добавляет `badge = Complaint::pending()->count()` при праве).
- `GET /admin/moderation/bans?status=active|expired|all` (`users.ban`): таблица (пользователь, кем, тип, код, начало, истекает, комментарий), «снять» → существующий `admin.users.ban.lift`.

- [ ] **Step 1: Тесты (падающие)** — фильтры статусов, review → статус и `reviewed_by`, 403 без права, бейдж pending в меню.
- [ ] **Step 2: Реализация.** — [ ] **Step 3: PASS, гейты, Commit** — `feat(admin): complaints and bans moderation pages`.

---

### Task 9: Настройки — автоформы из классов `Settings`

**Files:**
- Create: `packages/core/src/Settings/Schema/{Field,SettingsSchema,FieldType}.php`, `packages/core/src/Http/Controllers/Admin/SettingsController.php`, `packages/core/src/Actions/Settings/UpdateSettingsGroup.php`, `packages/core/resources/js/pages/admin/settings/edit.tsx`, `packages/core/resources/js/components/admin/settings/{field-renderer.tsx,violation-codes-editor.tsx}`, `packages/core/tests/Unit/SettingsSchemaTest.php`, `packages/core/tests/Feature/Admin/SettingsTest.php`
- Modify: `packages/core/src/Settings/*Settings.php` (атрибуты `#[Field]`), `packages/core/routes/admin.php`, `packages/core/lang/{ru,en}/settings.php` (labels/descriptions)

**Interfaces:**
- `#[Attribute(TARGET_PROPERTY)] Field(FieldType $type = null, ?string $options = null, ?string $description = null)`; `FieldType` enum: `Text, Textarea, Markdown, Number, Boolean, Select, Color, Json, ViolationCodes`. По умолчанию из PHP-типа: `string→Text`, `int→Number`, `bool→Boolean`, `array→Json`; атрибут уточняет (`rules_markdown` → Markdown, `default_language`/`default_theme`/`timezone` → Select с options-провайдером: `locales` / `['system','light','dark']` / `timezone_identifiers_list()`; `accent_color` → Color; `violation_codes` → ViolationCodes).
- `SettingsSchema::for(string $class): array{group: string, label: string, fields: list<array{name, type, label, description, options?, value}>}`; валидация в `UpdateSettingsGroup::handle(User $actor, string $class, array $input): Settings` по типу (`Boolean` → boolean, `Number` → integer, `Select` → in options, `Color` → regex hex, `ViolationCodes` → массив объектов `{code: regex ^[a-z0-9_-]+$, label: string, min_days: int ≥0, max_days: ?int ≥ min_days}`, уникальные коды); `settings.manage`; audit `settings.updated` с diff (без значений длиннее 200 символов).
- Роуты: `GET /admin/settings/{group}` (`admin.settings.edit`), `PUT /admin/settings/{group}` (`admin.settings.update`); `{group}` резолвится в класс через зарегистрированные `HasSettings` всех модулей (неизвестная группа → 404).
- Страница: табы по группам (из меню), форма по `fields` с `FieldRenderer`; `ViolationCodesEditor` — таблица с добавлением/удалением строк.

- [ ] **Step 1: Тесты (падающие)** — схема для `GeneralSettings` содержит ожидаемые типы; `PUT` с `violation_codes` без `code` → 422, валидный → сохранён и `BanUser` принимает новый код (Review Focus 3); `open=false` сохраняется как bool из `"0"`; неизвестная группа → 404; группа модуля Dummy отображается.
- [ ] **Step 2: Реализация.** — [ ] **Step 3: PASS, гейты, Commit** — `feat(admin): settings forms generated from settings classes`.

---

### Task 10: Дашборд, Модули, Журнал действий

**Files:**
- Create: `packages/core/src/Http/Controllers/Admin/{ModulesController,ActivityController}.php`, `packages/core/src/Actions/Modules/ToggleModule.php`, `packages/core/src/Admin/DashboardStats.php`, `packages/core/resources/js/pages/admin/{modules/index,activity/index}.tsx`, `packages/core/tests/Feature/Admin/{DashboardTest,ModulesPageTest,ActivityTest}.php`
- Modify: `packages/core/src/Http/Controllers/Admin/DashboardController.php`, `packages/core/resources/js/pages/admin/dashboard.tsx`, `packages/core/routes/admin.php`, `packages/core/src/Module/ModuleServiceProvider.php` (уважать `modules.is_enabled`: выключенный модуль не бутится, как failed, с причиной `disabled by administrator` — через `ModuleRegistry::markDisabled`, читается из таблицы при boot, если таблица существует)

**Interfaces:**
- `DashboardStats::collect(): array{users_total, users_online, users_today, pending_complaints, active_bans, modules: {installed, failed}}`; дашборд: карточки + список последних 10 записей аудита + последние 5 регистраций.
- `GET /admin/modules` (`modules.manage`): `ModuleResource[]` = объединение `ModuleRegistry::all()` + `failures()` + таблица `modules` (name, version (код), installed_version (БД), is_enabled, status: `active|failed|disabled|not_installed`, reason, needs_update = version ≠ installed_version); `PATCH /admin/modules/{name}` body `is_enabled` → `ToggleModule` (нельзя выключить `core`; audit); кнопка «обновить» не нужна — показывать подсказку `php artisan ladcms:update`.
- `GET /admin/activity?causer=&event=&from=&to=` (`activity.view`): таблица аудита (`ActivityResource`: время, кто, событие, субъект (тип+id+логин если User), свойства — раскрываемый JSON).

- [ ] **Step 1: Тесты (падающие)** — stats считаются; модули: статус failed для `BrokenModule`, toggle выключает Dummy (следующий запрос — его роуты 404), core выключить нельзя (422); активити фильтр по event.
- [ ] **Step 2: Реализация.** — [ ] **Step 3: PASS, гейты, Commit** — `feat(admin): dashboard, modules and activity log pages`.

---

### Task 11: Уведомления в шапке админки и flash-тосты

**Files:**
- Create: `packages/core/src/Http/Controllers/NotificationsController.php` (`index` JSON последних 20 + `unread_count`, `markRead`, `markAllRead`), `packages/core/resources/js/components/notification-bell.tsx`, `packages/core/tests/Feature/NotificationsEndpointTest.php`
- Modify: `packages/core/routes/web.php` (`/notifications` группа `auth`: `GET /api/notifications` (`notifications.index`), `POST /api/notifications/{id}/read`, `POST /api/notifications/read-all`), `admin-header.tsx` (колокольчик с `useHttp`/`fetch` через Inertia 3 `router.get`? — использовать `useHttp().get(route)` из `@inertiajs/react`), `app/resources/js/components/ui/sonner.tsx`? — нет; тосты уже работают через `router.on('flash')`

**Interfaces:**
- JSON: `{ unread_count: number, notifications: [{id, type (короткое имя класса), data, read_at, created_at}] }`; `markRead` → 204.
- `NotificationBell`: polling каждые 60 с (`usePoll` из Inertia 3? — только для Inertia-props; здесь `setInterval` + `useHttp`), dropdown с 20 последними, «прочитать все».

- [ ] **Step 1: Тесты (падающие)** — JSON структура, `markRead` чужого уведомления → 404, `read-all`. — [ ] **Step 2: Реализация.** — [ ] **Step 3: PASS, гейты, Commit** — `feat(core): notifications endpoints and admin bell`.

---

### Task 12: i18n админки, финальная сборка и документация

**Files:**
- Modify: `packages/core/lang/{ru,en}/admin.php` (все ключи, использованные на страницах), `packages/core/resources/js/lib/i18n.ts` (`t(key, replacements?)` читающий `usePage().props.i18n`), страницы — заменить захардкоженные строки на `t()`, `packages/core/resources/boost/guidelines/core.blade.php` (раздел «Админка для модулей»: `HasAdminPanel`, именование страниц `module::admin/...`, использование `@ladcms/core` UI-kit, `DataTable`, `SettingsSchema`), `packages/core/README.md`, `.ai/rules/` (новое правило `admin-ui.md`: страницы админки — только компоненты core UI-kit, тексты через `t()`), `docs/superpowers/specs/...` (§6.4 уточнения по факту)
- Create: `packages/core/tests/Feature/Admin/AdminSmokeTest.php` — обход всех `admin.*` GET-роутов под creator → 200 и `assertInertia` компонент существует (`ensure_pages_exist` true для этого теста)

- [ ] **Step 1: i18n + smoke-тест (падающий до правок) → PASS.** — [ ] **Step 2: Полная проверка**: root `vendor/bin/pest`, `sail pest`, `npm run check:fix && types:check && build`, Pint/PHPStan/Rector, `boost:update`. — [ ] **Step 3: Commit** — `feat(admin): i18n, smoke tests and documentation`.

---

## Self-review (выполнено)

- **Spec coverage §5.3 админ-роуты:** dashboard (T2/T10), users + users/{id} с ролью/баном/подозрительным/удалением (T5/T6), roles + permissions (T7), moderation/complaints + bans (T8), settings/{group} (T9), modules (T10), activity (T10). §6.4: двухуровневый сайдбар с hover (T3), DataTable (T1/T4), автоформы из `Settings` (T9), страница «Модули» со статусами и `ladcms:update`-подсказкой (T10), вход `admin.access` + `password.confirm` c TTL из настроек (T2). §6.3 стиль (T1). §6.1 структура JS (T1/T3). Колокольчик (§5.1 Уведомления) — T11.
- **Placeholders:** тесты описаны кейсами (UI-тяжёлые задачи), интерфейсы заданы; React-компоненты описаны поведением и props — для фронта это допустимый уровень детализации (исполнитель — модель с cheat-sheet kit).
- **Type consistency:** `Paginator` формат = `LengthAwarePaginator::toArray()` (T1, T4, T5); `MenuItem` (T2 PHP ↔ T3 TS); `can` объект в show (T6) и `Gate`; `violation_codes` структура (T9 ↔ `BanUser`); `ModuleResource` статусы (T10 ↔ `ModuleRegistry`).
- **Review Focus:** 1–2 → T6; 3 → T9; 4 → T4; 5 → T2.
