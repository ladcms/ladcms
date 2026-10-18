# LADCMS Core: публичный фронт (план 3) — план реализации

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Публичная часть core: `AppLayout` по дизайн-решению (шапка с навигацией/поиском/иконками модулей, левая колонка виджетов, нижняя панель иконок на телефоне), главная со слотами виджетов, профиль `/u/{login}` (шапка + вкладки; «Обзор» = карточка + блоки модулей), список пользователей, страница правил, уведомления (страница + колокольчик), настройки пользователя (профиль с `login`/аватаром/анкетой, приватность, уведомления, язык), контракты `HasNavigation`/`HasWidgets`/`HasHeaderActions`/`HasProfileSections`/`HasProfileActions` на сервере и клиенте, SEO-мета, адаптив и доступность.

**Architecture:** Серверные «реестры UI» в core (`Navigation`, `Widgets`, `ProfileSections`) собирают данные из модулей через `ModuleRegistry::implementing(...)` и шарят их в Inertia (`layout.navigation`, `layout.header_actions`, `layout.widgets`) только на публичных страницах; React-страницы `core::...` рендерятся в `PublicLayout` (core), виджеты и секции профиля — компоненты модулей, резолвятся по имени через тот же glob `vendor/ladcms/*/resources/js/widgets/**` / `profile/**` (расширение `resolve-page.ts` → `resolveModuleComponent(kind, name)`). Контроллеры тонкие; действия пользователя над собой — Actions из 2a (`ChangeLogin`, `UpdateAvatar`, …) + новые (`UpdateOwnProfile`, `UpdatePrivacy`, `UpdateNotificationPreferences`).

**Tech Stack:** как в плане 2b (Inertia 3, React 19, Tailwind 4, core UI-kit, Wayfinder, Pest/Testbench). Kit-страницы `settings/*` переезжают в core (`core::settings/*`), kit-страницы `auth/*` остаются в app.

**Spec:** `docs/superpowers/specs/2026-10-14-ladcms-foundation-and-core-design.md` — §5.3 (публичные роуты и настройки), §6.2 (layout), §6.5 (профиль), §4.3 (контракты), §5.1 (уведомления, приватность, онлайн).

## Global Constraints

- Публичные страницы core — `core::<section>/<page>` в `packages/core/resources/js/pages/**` (не `admin/`), все через `PublicLayout`; страницы `settings/*` — `core::settings/*` с вложенным `SettingsLayout` core.
- Роуты: `GET /` (`home`), `GET /u/{login}` (`users.show`), `GET /users` (`users.index`), `GET /rules` (`rules`), `GET /notifications` (`notifications.page`), `/settings/profile|password|appearance|two-factor|privacy|notifications` (имена `settings.*`); kit-роуты `profile.*`/`security.*`/`appearance.*` заменяются core-роутами (Fortify/2FA/passkeys остаются в app).
- Приватность: `show_email`, `show_birthday` соблюдаются в `PublicUserResource`; `messages_from` только хранится (использует `community`).
- Виджеты/навигация: модуль объявляет данные через `Has*`-интерфейсы; порядок — `order`; админ-настройка порядка — вне плана (отложено на `themes`/2c), сейчас порядок = `order` интерфейса.
- Мобильная нижняя панель — только иконки с `aria-label`, первые 4 пункта навигации + «Я».
- i18n через `t()`/`core::public.*`; `vp check`, `tsc`, build, Pint/PHPStan/Rector чистые; Pest `assertInertia` для каждой страницы + тесты приватности и доступа.
- Коммит после каждой задачи, trailer `Co-Authored-By: <модель-автор> <noreply@anthropic.com>`.

## Review Focus

1. Профиль пользователя с `show_email=false` — email не появляется ни в props, ни в HTML; с `show_email=true` — виден; владелец видит свой email всегда. — тесты в Task 5.
2. `/u/{старый_логин}` после смены логина → 301 на новый; `/u/{удалённый}` → 404; `/u/{несуществующий}` → 404. — тесты в Task 5.
3. Загрузка аватара 5 МБ или SVG через `/settings/profile/avatar` → 422 с понятной ошибкой, старый аватар не тронут. — тесты в Task 7.
4. Виджет модуля, компонент которого отсутствует во фронтовом бандле, не ломает главную: сервер фильтрует виджеты по списку доступных компонентов (`HasWidgets` → `component` должен существовать в glob), в dev — предупреждение в консоли. — тест в Task 3.
5. Забаненный (`readonly`) пользователь видит баннер «только чтение» в layout и не может отправлять формы настроек, меняющие публичный контент (имя/подпись) — 403; смена пароля разрешена. — тесты в Task 6.

---

### Task 1: Серверные реестры UI и shared props публичного layout

**Files:**
- Create: `packages/core/src/Ui/{Navigation,Widgets,HeaderActions}.php`, `packages/core/src/Http/Middleware/SharePublicLayout.php`, `packages/core/tests/Unit/Ui/{NavigationTest,WidgetsTest}.php`
- Modify: `packages/core/src/CoreServiceProvider.php` (`implements HasNavigation, HasWidgets, HasHeaderActions`; share), `packages/core/tests/Fixtures/DummyModule/*` (реализации интерфейсов для тестов)

**Interfaces:**
- `Navigation::items(User|null $user): list<array{key,label,icon,url,order,active}>` — core: `home` (`house`), `users` (`users`), `rules` (`scroll`); модули — `HasNavigation::navigation()`; фильтрация по `permission` (опционально в элементе).
- `Widgets::for(string $slot, ?User $user): list<array{name, component, module, order, props: array}>` — `component` = `<module>::<Name>` (файл `vendor/ladcms/<module>/resources/js/widgets/<Name>.tsx`); core-виджеты: `online` (`core::Online`, props: `count`, `users` top-10 онлайн), `latest-users` (`core::LatestUsers`); `props` вычисляются лениво замыканием `HasWidgets` элемента `props: Closure|array`.
- `HeaderActions::items(?User $user)`: core — `notifications` (`bell`, компонент `core::NotificationBell`, только для авторизованных); модули — `HasHeaderActions`.
- `SharePublicLayout` (группа `web`, пропускает `admin.*` и `auth`-страницы Fortify? — нет: шарит всегда кроме `admin.*`): `Inertia::share('layout', fn () => ['navigation' => …, 'header_actions' => …, 'widgets' => ['sidebar' => …, 'main' => request()->routeIs('home') ? … : []], 'site' => ['name' => GeneralSettings::site_name, 'description', 'logo_url'], 'locale', 'locales'])`.

- [ ] **Step 1: Тесты (падающие)** — навигация: core-пункты + Dummy-пункт отсортированы по `order`, `active` для текущего роута, пункт с permission скрыт без права; виджеты: slot-фильтрация, lazy props вычисляются, `component` формат; header actions: гость не видит `notifications`.
- [ ] **Step 2: Реализация.** — [ ] **Step 3: PASS, гейты, Commit** — `feat(core): navigation, widgets and header-action registries shared with the public layout`.

---

### Task 2: `PublicLayout` (десктоп/планшет/телефон) и резолвер компонентов модулей

**Files:**
- Create: `packages/core/resources/js/layouts/public-layout.tsx`, `packages/core/resources/js/components/public/{site-header.tsx,site-footer.tsx,sidebar-widgets.tsx,mobile-tab-bar.tsx,user-menu.tsx,search-box.tsx,readonly-banner.tsx}`, `packages/core/resources/js/inertia/resolve-component.ts`, `packages/core/resources/js/types/layout.ts`, `packages/core/resources/js/widgets/{Online,LatestUsers}.tsx`
- Modify: `app/resources/js/app.tsx` (`layout()`: `core::admin/*` → AdminLayout; `auth/*` → AuthLayout (kit); `core::settings/*` → `[PublicLayout, SettingsLayout]`; остальные `core::*` и `<module>::*` (не admin) → PublicLayout; глобы для `widgets/**` и `profile/**`), `app/resources/js/types/global.d.ts`

**Interfaces:**
- `resolveModuleComponent(kind: 'widgets'|'profile', name: string, registry: Record<string, () => Promise<unknown>>): React.LazyExoticComponent | null` — `null`, если нет файла (Review Focus 4: сервер уже отфильтровал; клиент защищается ещё раз, логируя `console.warn` в dev).
- `PublicLayout { children; title? }`: `SiteHeader` (логотип+название, навигация (desktop), `SearchBox` (ведёт на `/users?q=` пока нет глобального поиска), header actions (ленивые компоненты модулей + `NotificationBell` из плана 2b), `UserMenu` (аватар, display_name, роль цветом; ссылки: профиль, настройки, админка если `admin.access`, выход) / кнопки Войти/Регистрация (если `registration.open`)); `ReadonlyBanner` если `auth.user.can_write === false`; тело: `<aside class="hidden lg:block w-72">` с `SidebarWidgets`, `<main>`; `SiteFooter` (копирайт `site.name`, правила, переключатель языка → `POST /locale` (Task 8)); `MobileTabBar` (`lg:hidden`, фиксированная снизу: первые 4 `navigation` + «Я» (профиль или вход), только иконки, `aria-label`); виджеты сайдбара на телефоне — свёрнутая секция «Ещё» под контентом.

- [ ] **Step 1: Реализация + визуальная проверка** в браузере на 1280/900/400 px (описать в отчёте); `core::welcome` переводится на `PublicLayout` и становится главной с `main`-виджетами (slot `main`).
- [ ] **Step 2: `npm run check:fix && types:check && build`; Pest `HomePageTest` в app обновить (компонент `core::home`), Commit** — `feat(ui): public layout with header, sidebar widgets and mobile tab bar`.

---

### Task 3: Главная страница

**Files:**
- Create: `packages/core/src/Http/Controllers/HomeController.php` (переписать: `core::home`, props `widgets` берутся из shared `layout.widgets.main`), `packages/core/resources/js/pages/home.tsx`, `packages/core/tests/Feature/HomeTest.php`
- Delete: `packages/core/resources/js/pages/welcome.tsx` (заменена)

- [ ] **Step 1: Тесты** — гость и пользователь получают `core::home`; `layout.widgets.main` содержит core-виджеты; Dummy-виджет с несуществующим компонентом отфильтрован сервером (`Widgets` проверяет наличие файла `resources/js/widgets/<Name>.tsx` в модуле) — Review Focus 4.
- [ ] **Step 2: Реализация; удалить `welcome.tsx` (проверить, что на него никто не ссылается: `grep -rn welcome app/resources/js packages/core/resources/js`).** — [ ] **Step 3: PASS, гейты, Commit** — `feat(core): home page with widget slots`.

---

### Task 4: Список пользователей `/users`

**Files:**
- Create: `packages/core/src/Http/Controllers/UsersController.php` (`index`), `packages/core/src/Http/Resources/PublicUserResource.php`, `packages/core/resources/js/pages/users/index.tsx`, `packages/core/tests/Feature/UsersIndexTest.php`
- Modify: `packages/core/routes/web.php`

**Interfaces:**
- `GET /users?q=&sort=login|created_at|last_activity_at&direction=&online=1` (`users.index`, публичный): `TableQuery` из 2b; `PublicUserResource` → `id, login, name, display_name, avatar_url, roles [{name, color}], is_online, created_at, (email если show_email или это сам пользователь), (birth_date если show_birthday)`; 30 на страницу.
- Страница: сетка карточек пользователей (аватар, display_name, роль цветом, онлайн-точка), поиск, сортировка, фильтр «онлайн», пагинация.

- [ ] **Step 1: Тесты** — поиск/сортировка/онлайн-фильтр; `email` скрыт при `show_email=false` даже для авторизованного стороннего. — [ ] **Step 2: Реализация.** — [ ] **Step 3: PASS, гейты, Commit** — `feat(core): public users directory`.

---

### Task 5: Профиль `/u/{login}` — шапка + вкладки, «Обзор», секции модулей

**Files:**
- Create: `packages/core/src/Ui/ProfileSections.php`, `packages/core/src/Http/Controllers/ProfileController.php` (`show`), `packages/core/src/Http/Resources/ProfileResource.php`, `packages/core/resources/js/pages/users/show.tsx`, `packages/core/resources/js/components/profile/{profile-header.tsx,profile-tabs.tsx,overview-card.tsx,module-block.tsx}`, `packages/core/resources/js/profile/core/{Overview}.tsx`, `packages/core/tests/Feature/ProfileTest.php`, `packages/core/tests/Unit/Ui/ProfileSectionsTest.php`
- Modify: `packages/core/routes/web.php`, `packages/core/tests/Fixtures/DummyModule/*` (`HasProfileSections`, `HasProfileActions`)

**Interfaces:**
- `GET /u/{login}` (`users.show`): `User::findByLogin()`; если найден по истории и логин ≠ текущему → `redirect()->route('users.show', $user->login, 301)`; не найден/удалён → 404. Props: `profile: ProfileResource` (как `PublicUserResource` + `about`, `signature`, `sex`, `language`, `last_activity_at`, `is_banned`, `is_suspicious` (только для `users.view`), `counters: [{key,label,value,tab?}]` от модулей), `tabs: [{key,label,component}]` (`overview` + `HasProfileSections.tab` модулей), `overview_blocks: [{key, component, order}]`, `actions: [{key,label,icon,component}]` (`HasProfileActions`, фильтр по `can`), `moderation: {can_ban, can_assign_roles, can_mark_suspicious, admin_url}`; `?tab=<key>` выбирает вкладку (серверный рендер только `overview` данных; вкладки модулей грузят свои данные через `Inertia::optional` по ключу `tab_data` при `?tab=`).
- `ProfileSections::tabs(User $target, ?User $viewer)`, `::overview(...)`, `::actions(...)`, `::counters(...)` — агрегируют `HasProfileSections`/`HasProfileActions`.
- Страница: обложка (accent), аватар, display_name, роль цветом, `@login`, онлайн; кнопки действий модулей; `⋯` с модераторскими ссылками в админку (по `moderation.*`); счётчики; вкладки (горизонтальный скролл на телефоне); «Обзор»: слева карточка анкеты (регистрация, пол, д.р.*, язык, последний визит), справа `ModuleBlock`-и («последние 5 + все →» переключает вкладку).

- [ ] **Step 1: Тесты** — Review Focus 1 и 2; счётчики/вкладки от Dummy; `moderation` только с правами; `?tab=dummy` отдаёт `tab_data` через partial reload.
- [ ] **Step 2: Реализация.** — [ ] **Step 3: PASS, гейты, Commit** — `feat(core): public user profile with tabs and module sections`.

---

### Task 6: Настройки пользователя в core — профиль, приватность, уведомления, язык

**Files:**
- Create: `packages/core/src/Http/Controllers/Settings/{ProfileSettingsController,PrivacyController,NotificationPreferencesController,LocaleController}.php`, `packages/core/src/Http/Requests/Settings/{UpdateProfileRequest,UpdatePrivacyRequest,UpdateNotificationPreferencesRequest}.php`, `packages/core/src/Actions/Users/{UpdateOwnProfile,UpdatePrivacy,UpdateNotificationPreferences}.php`, `packages/core/database/migrations/2026_10_18_000001_add_notification_preferences_to_users_table.php` (`notification_preferences` json null), `packages/core/resources/js/layouts/settings-layout.tsx`, `packages/core/resources/js/pages/settings/{profile,privacy,notifications}.tsx`, `packages/core/tests/Feature/Settings/*Test.php`, `packages/core/tests/Unit/Actions/UpdateOwnProfileTest.php`
- Modify: `packages/core/routes/web.php`, `app/routes/settings.php` (убрать kit profile/appearance роуты, оставить security/password/passkeys), `app/resources/js/pages/settings/{profile,appearance}.tsx` (удалить — переезжают в core: `core::settings/profile`, `core::settings/appearance`), `app/resources/js/layouts/settings/layout.tsx` (удалить — core `SettingsLayout`), `app/app/Http/Controllers/Settings/ProfileController.php` (оставить только `destroy` → `DeleteOwnAccount`), `app/tests/Feature/Settings/ProfileUpdateTest.php` (адаптировать на core-роуты)

**Interfaces:**
- `UpdateOwnProfile::handle(User $user, array $data): User` — `name, sex, birth_date, about (max 2000), signature (max 500), timezone`; требует `canWrite()` для `name/about/signature` (Review Focus 5: readonly → `AuthorizationException`), остальное можно всегда; email меняется отдельно (kit `ProfileValidationRules` → лowercase + повторная верификация): `PATCH /settings/email` остаётся в app? — ruling: email тоже в core `UpdateOwnProfile` (lowercase, unique, при смене сбрасывает `email_verified_at` и шлёт verification notification, если `require_email_verification`).
- `UpdatePrivacy::handle(User, array{show_email, show_birthday, messages_from})`, `UpdateNotificationPreferences::handle(User, array<string, array{database: bool, mail: bool}>)` — ключи типов: `mentions`, `complaints`, `bans` (core) + модули через конфиг `core.notification_types`; уведомления `UserMentioned`/`ComplaintFiled`/`UserBanned` читают предпочтения в `via()` (mail off → только database).
- `LocaleController@update` (`POST /locale`, `locale` in available): авторизованному — `users.language`, гостю — cookie `locale` (unencrypted, 1 год).
- `SettingsLayout` core: вкладки Профиль, Приватность, Уведомления, Безопасность (kit `security.edit`), Оформление (`core::settings/appearance` — перенос kit `appearance`).

- [ ] **Step 1: Тесты** — профиль обновляется; readonly → 403 на name; email lowercase + сброс верификации; приватность; предпочтения уведомлений влияют на `via()`; `POST /locale` гость/пользователь.
- [ ] **Step 2: Реализация + перенос kit-страниц + адаптация app-тестов.** — [ ] **Step 3: PASS (root + Sail), гейты, Commit** — `feat(core): user settings pages (profile, privacy, notifications, locale)`.

---

### Task 7: Аватар в настройках

**Files:**
- Create: `packages/core/src/Http/Controllers/Settings/AvatarController.php` (`store` multipart, `destroy`), `packages/core/resources/js/components/settings/avatar-uploader.tsx`, `packages/core/tests/Feature/Settings/AvatarTest.php`
- Modify: `packages/core/src/Actions/Users/UpdateAvatar.php` (добавить правило `dimensions:max_width=8000,max_height=8000` — защита от decompression bomb, отложенный minor 2a), `packages/core/resources/js/pages/settings/profile.tsx`, `packages/core/routes/web.php`

- [ ] **Step 1: Тесты** — Review Focus 3 (5 МБ → 422; SVG → 422; старый аватар цел), успешная загрузка меняет `avatar_url`, удаление. — [ ] **Step 2: Реализация (`<input type=file>` + предпросмотр, `useForm`/`<Form>` с `forceFormData`).** — [ ] **Step 3: PASS, гейты, Commit** — `feat(core): avatar upload in user settings`.

---

### Task 8: Уведомления — страница и колокольчик в публичном layout, страница правил

**Files:**
- Create: `packages/core/src/Http/Controllers/{NotificationsPageController,RulesController}.php`, `packages/core/resources/js/pages/{notifications/index,rules}.tsx`, `packages/core/resources/js/widgets/core/NotificationBell.tsx` (переиспользует компонент из 2b), `packages/core/tests/Feature/{NotificationsPageTest,RulesPageTest}.php`
- Modify: `packages/core/routes/web.php`, `packages/core/src/Notifications/*` (`toArray` с `title`/`url` для рендера списка)

**Interfaces:**
- `GET /notifications` (`notifications.page`, `auth`): пагинация 20, `notifications: [{id, type, title, url, data, read_at, created_at}]`; открытие помечает прочитанными те, что на странице (`markAsRead`); `GET /rules`: `rules_markdown` → HTML на сервере (`Str::markdown` с `html_input: strip`, `allow_unsafe_links: false`), страница с `prose`.

- [ ] **Step 1: Тесты** — гость → login; список/прочтение; правила рендерят markdown, XSS-теги вырезаны. — [ ] **Step 2: Реализация.** — [ ] **Step 3: PASS, гейты, Commit** — `feat(core): notifications page and rules page`.

---

### Task 9: SEO, доступность, ошибки, i18n и документация

**Files:**
- Create: `packages/core/resources/js/pages/error.tsx` (403/404/500/503 — `core::error`), `packages/core/src/Http/Middleware/HandlePublicErrors.php`? — нет: регистрация в `app/bootstrap/app.php` `->withExceptions(fn ($e) => $e->respond(...))` рендер `core::error` для 403/404/500/503 на не-JSON запросах (как в документации Inertia), `packages/core/tests/Feature/ErrorPagesTest.php`, `packages/core/tests/Feature/PublicSmokeTest.php` (все публичные GET-роуты под гостем и пользователем → 200/редирект, компоненты существуют)
- Modify: все публичные страницы — `<Head>` с `title`/`description`/`og:*` из `site`, `lang` атрибут из `locale` в `app.blade.php`; `aria`-атрибуты на навигации/табах/таббаре; фокус-стили; `lang/{ru,en}/public.php`; `packages/core/resources/boost/guidelines/core.blade.php` (раздел «Публичный UI для модулей»: `HasNavigation/HasWidgets/HasHeaderActions/HasProfileSections/HasProfileActions`, где лежат компоненты виджетов/секций, именование), `README.md`, спека §6.2/§6.5 — уточнения по факту

- [ ] **Step 1: Smoke-тест (падающий до правок) → PASS; ErrorPagesTest.** — [ ] **Step 2: Полная проверка**: root pest, `sail pest`, `npm run check:fix && types:check && build`, Pint/PHPStan/Rector, `boost:update`; ручная проверка в браузере (главная, профиль, настройки, 404) на десктопе и 400 px. — [ ] **Step 3: Commit** — `feat(core): error pages, SEO meta, a11y, i18n and docs for the public UI`.

---

## Self-review (выполнено)

- **Spec coverage:** §6.2 layout (T1/T2), главная со слотами (T3), §5.3 публичные роуты: `/` T3, `/u/{login}` T5, `/users` T4, `/rules` T8, `/notifications` T8, `/settings/*` T6/T7 (`password`, `two-factor` — kit; `appearance` переносится в core); §6.5 профиль (T5); §5.1 приватность (T4/T5/T6), уведомления/колокольчик (T8 + 2b), онлайн (виджет T1); §4.3 контракты `HasNavigation/HasWidgets/HasHeaderActions/HasProfileSections/HasProfileActions` (T1/T5). `HasSearch` — вне плана (поиск в волне 3 по спеке).
- **Placeholders:** нет TBD; UI-задачи описаны поведением/props, серверные части — сигнатурами.
- **Type consistency:** `component` формат `<module>::<Name>` (T1 ↔ T2 резолвер ↔ T3 фильтр); `PublicUserResource` (T4) расширяется `ProfileResource` (T5); `canWrite()` (2a) — T2 баннер и T6 правило; `TableQuery` (2b) — T4.
- **Review Focus:** 1–2 → T5; 3 → T7; 4 → T3 (+T2 клиентская защита); 5 → T2/T6.
- **Зависимости:** план 3 требует выполненного 2b (UI-kit core, `TableQuery`, `NotificationBell`, `AdminLayout` маршрутизация в `app.tsx`).
