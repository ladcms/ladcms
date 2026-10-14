# LADCMS Core Architecture Design

> Дата: 2026-04-04
> Статус: **Superseded** — заменена `2026-10-14-ladcms-foundation-and-core-design.md` (React вместо Vue, Inertia 3, монорепо, гибридные права, дизайн-решения). Оставлена для истории.
> Проект: LADCMS — модульная CMS на Laravel 13 + Vue + Inertia.js
> Перенос старого DCMS (чистый PHP монолит) на современный стек

---

## 1. Обзор системы

LADCMS — модульная CMS, где каждый пакет — отдельный Laravel Package в GitHub-организации `ladcms`. Обязательный пакет `core` предоставляет фундамент: пользователи, авторизация, права, i18n, админку, UI-kit. Опциональные модули подключаются через `composer require` и автоматически интегрируются.

### Стек технологий

- **Backend**: Laravel 13, PHP 8.3+
- **Frontend**: Vue 3, Inertia.js
- **Auth scaffolding**: Laravel 13 starter kit (вариант C — берём роутинг/бэкенд auth, заменяем UI-слой)
- **Permissions**: Spatie Laravel Permission
- **Real-time**: Laravel Reverb (WebSocket) + Laravel Echo
- **Иконки**: Lucide (`lucide-vue-next`)
- **Редактор**: Взаимозаменяемый через контракт `EditorInterface` (рекомендуемая реализация — Tiptap)
- **Дизайн**: Один адаптивный дизайн, система тем через CSS-переменные + опциональный модуль `themes`

---

## 2. Карта пакетов

| # | Пакет | Тип | Описание |
|---|---|---|---|
| 1 | `app` | Приложение | Точка входа, composer.json, конфиги, Laravel Reverb |
| 2 | `core` | Обязательный | Users, auth, Spatie permissions, VIP, i18n, жалобы, подозрительные, баны, аватары, онлайн-трекинг, уведомления, подписи, mentions, скелет админки, UI-kit |
| 3 | `editor-tiptap` | Обязательный* | Реализация EditorInterface на Tiptap (взаимозаменяемый) |
| 4 | `forum` | Опциональный | Категории, темы, посты, рейтинг |
| 5 | `chat` | Опциональный | Мини-чат real-time (Reverb + Echo) |
| 6 | `news` | Опциональный | Новости, комментарии, email-рассылка |
| 7 | `files` | Опциональный | Файловый менеджер, метаданные, виджет "Новые файлы" |
| 8 | `photos` | Опциональный | Фотогалерея |
| 9 | `community` | Опциональный | Друзья, личные сообщения |
| 10 | `balls` | Опциональный | Система баллов (правила в БД, управление через админку) |
| 11 | `socialite` | Опциональный | OAuth (VK, Google и др.) через Laravel Socialite |
| 12 | `analytics` | Опциональный | Статистика, посещения, рефереры, история логинов, аудит-лог админов |
| 13 | `themes` | Опциональный | Управление темами, выбор/установка тем |
| 14 | `polls` | Опциональный | Опросы/голосования (используется из forum, chat, news) |
| 15 | `reviews` | Опциональный | Отзывы на пользователей (зависит от community) |
| 16 | `feed` | Опциональный | Лента активности (слушает события всех модулей) |

*`editor-tiptap` обязателен как реализация по умолчанию, но может быть заменён на `editor-ckeditor` и т.д.

### Граф зависимостей

```
core (обязательный)
├── editor-tiptap (реализация EditorInterface)
├── forum        (зависит от core)
├── chat         (зависит от core, использует Reverb)
├── news         (зависит от core)
├── files        (зависит от core)
├── photos       (зависит от core, опционально files)
├── community    (зависит от core)
├── balls        (зависит от core, слушает события всех модулей)
├── socialite    (зависит от core)
├── analytics    (зависит от core, слушает события всех модулей)
├── themes       (зависит от core)
├── polls        (зависит от core, используется другими модулями)
├── reviews      (зависит от core + community)
└── feed         (зависит от core, слушает события всех модулей)
```

Никаких циклических зависимостей. Все модули зависят только от core. Межмодульное общение — через events и `ModuleRegistry::has()`.

---

## 3. Структура пакета core

```
ladcms/core/
├── config/
│   └── ladcms.php                  # Основной конфиг
├── database/
│   └── migrations/
│       ├── add_ladcms_fields_to_users_table.php
│       ├── create_bans_table.php
│       └── create_complaints_table.php
├── resources/
│   ├── js/
│   │   ├── Components/             # UI-kit: UiButton, UiCard, UiTable, UiModal, UiForm, UiNavItem...
│   │   ├── Layouts/
│   │   │   ├── AppLayout.vue       # Публичный layout
│   │   │   └── AdminLayout.vue     # Админский layout (меню собирается из модулей)
│   │   ├── Pages/
│   │   │   ├── Auth/               # Login, Register, ForgotPassword
│   │   │   ├── Profile/            # Просмотр/редактирование профиля
│   │   │   ├── Admin/              # Дашборд, пользователи, группы, жалобы, баны, настройки
│   │   │   └── Error/              # 403, 404, 500
│   │   ├── Composables/            # useAuth(), usePermissions(), useTheme(), useLocale()
│   │   └── app.ts                  # Точка входа
│   ├── css/
│   │   └── theme.css               # Дефолтная тема (CSS-переменные)
│   └── lang/                       # 11 языков (ru, en, uk, pl, be, kk, uz, id, ro, lt, az)
├── routes/
│   ├── web.php                     # Публичные роуты
│   ├── admin.php                   # Админ-роуты
│   └── api.php                     # API (mention-search и др.)
├── src/
│   ├── CoreServiceProvider.php
│   ├── Contracts/
│   │   ├── ModuleInterface.php
│   │   ├── HasAdminPanel.php
│   │   ├── HasPermissions.php
│   │   ├── HasBallsRules.php
│   │   ├── HasWidgets.php
│   │   ├── HasSearch.php
│   │   └── EditorInterface.php
│   ├── Models/
│   │   ├── User.php                # Расширяет Laravel User
│   │   ├── Ban.php
│   │   └── Complaint.php
│   ├── Services/
│   │   ├── ModuleRegistry.php
│   │   ├── LocaleManager.php
│   │   ├── MentionService.php
│   │   └── ComplaintService.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   └── ProfileController.php
│   │   └── Middleware/
│   │       ├── AdminAccess.php
│   │       ├── CheckBan.php
│   │       ├── SetLocale.php
│   │       └── TrackOnline.php
│   ├── Events/
│   │   ├── UserRegistered.php
│   │   ├── UserBanned.php
│   │   └── ComplaintCreated.php
│   ├── Notifications/
│   │   └── UserMentioned.php
│   └── Support/
│       └── ModuleServiceProvider.php   # Базовый класс для провайдеров модулей
└── tests/
```

---

## 4. Механизм регистрации модулей

### Подход: Interface-based контракты + стандартный ServiceProvider

Каждый модуль наследует `ModuleServiceProvider` и реализует нужные интерфейсы. Core обнаруживает модули через Laravel Package Discovery.

### Базовый контракт

```php
interface ModuleInterface
{
    public function name(): string;        // 'forum', 'chat'
    public function version(): string;     // '1.0.0'
    public function description(): string;
    public function coreVersion(): string; // '>=1.0'
}
```

### Опциональные интерфейсы

```php
interface HasAdminPanel {
    public function adminMenuItems(): array;
}
interface HasPermissions {
    public function permissions(): array;
}
interface HasBallsRules {
    public function ballsRules(): array;
}
interface HasWidgets {
    public function widgets(): array;
}
interface HasSearch {
    public function searchableModels(): array;
}
```

### Пример регистрации модуля

```php
class ForumServiceProvider extends ModuleServiceProvider implements
    HasAdminPanel, HasPermissions, HasWidgets
{
    public function name(): string { return 'forum'; }
    public function version(): string { return '1.0.0'; }
    public function coreVersion(): string { return '>=1.0'; }
    public function description(): string { return 'Forum module'; }

    public function adminMenuItems(): array
    {
        return [
            [
                'label' => 'forum::admin.menu_title',
                'icon' => 'message-square',
                'route' => 'admin.forum.index',
                'order' => 20,
                'children' => [
                    ['label' => 'forum::admin.categories', 'route' => 'admin.forum.categories'],
                    ['label' => 'forum::admin.settings', 'route' => 'admin.forum.settings'],
                ],
            ],
        ];
    }

    public function permissions(): array
    {
        return ['forum.view', 'forum.create_theme', 'forum.post', 'forum.moderate', 'forum.delete'];
    }

    public function widgets(): array
    {
        return [
            ['name' => 'latest-topics', 'component' => 'Forum/Widgets/LatestTopics'],
        ];
    }

    public function optionalDependencies(): array
    {
        return ['balls', 'community', 'polls'];
    }
}
```

### ModuleServiceProvider (базовый класс)

Автоматически в `boot()`:
- Регистрирует модуль в `ModuleRegistry`
- Загружает миграции из `database/migrations/`
- Загружает роуты из `routes/web.php`, `routes/admin.php`, `routes/api.php`
- Загружает переводы из `resources/lang/`
- Регистрирует Vue-страницы с namespace (например `Forum::Admin/Index`)
- Проверяет `coreVersion()` и зависимости

### ModuleRegistry

```php
class ModuleRegistry
{
    public function all(): Collection;
    public function has(string $name): bool;
    public function get(string $name): ?ModuleInterface;
    public function adminMenuItems(): array;    // собирает из всех HasAdminPanel
    public function permissions(): array;        // собирает из всех HasPermissions
    public function widgets(): array;            // собирает из всех HasWidgets
    public function ballsRules(): array;         // собирает из всех HasBallsRules
}
```

---

## 5. Схема БД

### Расширение users (add_ladcms_fields_to_users_table)

Добавляемые поля к стандартной Laravel `users`:

| Поле | Тип | Описание |
|---|---|---|
| login | string, unique | Ник для входа |
| rating | decimal(10,2), default 0 | Рейтинг |
| language | string, default 'ru' | Язык интерфейса |
| avatar | string, nullable | Путь к аватарке |
| about | text, nullable | О себе |
| signature | text, nullable | Подпись (рендерится в forum) |
| last_activity_at | timestamp, nullable | Последняя активность |
| registered_ip | string, nullable | IP при регистрации |
| is_suspicious | boolean, default false | Флаг подозрительности |
| deleted_at | timestamp, nullable | Soft delete |

### Spatie Permission (расширение таблицы roles)

Spatie создаёт таблицы: `roles`, `permissions`, `role_has_permissions`, `model_has_roles`, `model_has_permissions`.

Добавляемые поля к `roles`:

| Поле | Тип | Описание |
|---|---|---|
| level | integer, unique | 0-6, для сравнения прав |
| color | string, nullable | Цвет ника |
| is_default | boolean, default false | Группа при регистрации |

Стандартные группы (seeder):

| Level | Slug | Название |
|---|---|---|
| 0 | guest | Гость |
| 1 | user | Пользователь |
| 2 | moderator | Модератор |
| 3 | super_moderator | Супер Модератор |
| 4 | admin | Администратор |
| 5 | main_admin | Главный Администратор |
| 6 | creator | Создатель |
| — | vip | VIP (дополнительная роль, постоянная) |

### Таблица bans

| Поле | Тип | Описание |
|---|---|---|
| id | bigint, PK | |
| user_id | FK → users | Кого забанили |
| banned_by | FK → users | Кто забанил |
| reason | text, nullable | Причина |
| type | enum: full, soft | Полный или частичный |
| started_at | timestamp | Начало бана |
| expires_at | timestamp, nullable | null = перманентный |
| timestamps | | |

### Таблица complaints

| Поле | Тип | Описание |
|---|---|---|
| id | bigint, PK | |
| user_id | FK → users | Кто жалуется |
| target_user_id | FK → users, nullable | На кого |
| module | string | 'forum', 'chat', 'news' |
| target_type | string | Morph type: 'forum_message' |
| target_id | bigint | ID контента |
| reason | text | Причина |
| status | enum: pending, reviewed, resolved, rejected | |
| reviewed_by | FK → users, nullable | Кто рассмотрел |
| reviewed_at | timestamp, nullable | |
| timestamps | | |

---

## 6. API-контракты между core и модулями

### Три механизма взаимодействия

#### 1. Интерфейсы (строгие контракты)

Модуль реализует интерфейсы — core знает что ожидать. IDE-автокомплит, типобезопасность.

#### 2. Events (слабая связанность)

Для опциональных связей между модулями:

```
Core публикует:           Модули публикуют:
- UserRegistered          - ForumPostCreated
- UserBanned              - ChatMessageSent
- ComplaintCreated        - FileUploaded
                          - NewsCommentCreated
```

Модули `balls`, `analytics`, `feed` подписываются на события других модулей. Если модуль-источник не установлен — событий просто нет.

#### 3. ModuleRegistry (runtime проверки)

```php
// Бэкенд
if (app(ModuleRegistry::class)->has('community')) { ... }

// Фронтенд (через Inertia shared data)
Inertia::share('modules', fn () => app(ModuleRegistry::class)->names());
```

```vue
<UiButton v-if="$page.props.modules.includes('community')" @click="sendMessage(user)">
    Написать
</UiButton>
```

#### 4. Сервисы core для модулей (DI)

```php
app(ModuleRegistry::class)->has('balls');
app(MentionService::class)->processContent($html, $author);
app(ComplaintService::class)->create($data);
app(LocaleManager::class)->current();
```

---

## 7. Система тем

### Три уровня темизации

#### Уровень 1: CSS-переменные (дизайн-токены)

Core определяет токены, тема переопределяет:

```css
:root {
    --color-primary: #3490dc;
    --color-secondary: #6c757d;
    --border-radius: 8px;
    --spacing-md: 16px;
    --font-family: 'Inter', sans-serif;
}
```

#### Уровень 2: UI-kit компоненты

Core предоставляет `UiButton`, `UiCard`, `UiTable`, `UiModal`, `UiForm`, `UiNavItem` и др. Модули строят интерфейс только из этих компонентов. Тема может переопределить базовый компонент — все модули подхватят.

#### Уровень 3: Layout с именованными слотами

```vue
<ThemeLayout>
    <template #sidebar>...</template>
    <template #content>...</template>
</ThemeLayout>
```

### Модуль themes (опциональный)

Без модуля — одна дефолтная тема. С модулем — управление темами в админке, выбор в профиле пользователя, установка новых тем.

---

## 8. Взаимозаменяемый редактор

### Контракт

```php
interface EditorInterface
{
    public function vueComponent(): string;
    public function extensions(): array;
    public function sanitize(string $html): string;
}
```

### Реализация: editor-tiptap (по умолчанию)

- Полный режим (форум, новости): заголовки, списки, цитаты, код, изображения, смайлы, @mentions
- Лёгкий режим (чат): текст + смайлы + @mentions
- Смайлы: Unicode emoji по умолчанию, расширяемые через плагины
- `[hide]` контент: Tiptap-расширение, зависит от наличия модуля `balls`

### Mentions (core)

Работают независимо от редактора:

1. Редактор вызывает `GET /api/users/mention-search?q=...` для автокомплита
2. Вставляет `<span data-mention data-user-id="42">@vadimushka</span>`
3. При сохранении контента `MentionService::processContent()` находит mentions и отправляет уведомления

---

## 9. Система баллов (модуль balls)

### Правила хранятся в БД

Таблица `balls_rules`:

| Поле | Тип | Описание |
|---|---|---|
| id | bigint, PK | |
| module | string | 'forum', 'news' |
| event | string | Class name события |
| label | string | i18n ключ |
| amount | integer | Баллы (админ меняет) |
| is_active | boolean | Вкл/выкл |
| timestamps | | |

Модули регистрируют дефолтные значения через `HasBallsRules`. При установке — seed в `balls_rules`. Админ управляет через админку: меняет `amount`, включает/отключает правила.

---

## 10. Админка

### Одно Vue-приложение

Админка — тот же Inertia, отдельный `AdminLayout` с проверкой прав через middleware `AdminAccess`.

### Сборка меню

Core через `Inertia::share()` передаёт собранное меню из всех модулей:

```php
Inertia::share('adminMenu', fn () => app(ModuleRegistry::class)->adminMenuItems());
Inertia::share('modules', fn () => app(ModuleRegistry::class)->names());
```

`AdminLayout.vue` рендерит сайдбар из `$page.props.adminMenu`.

### Роуты модулей

Каждый модуль подключает `routes/admin.php` с prefix `admin/{module}` и middleware `['web', 'auth', 'admin']`. `ModuleServiceProvider` загружает файл автоматически.

### Vue-страницы модулей

Namespace для Inertia: `Forum::Admin/Index` → `forum/resources/js/Pages/Admin/Index.vue`.

### Жизненный цикл модуля

1. `composer require ladcms/forum`
2. Laravel Package Discovery → `ForumServiceProvider`
3. `ModuleServiceProvider::boot()` → миграции, роуты, переводы, Vue-страницы
4. `HasAdminPanel` → пункт меню появляется
5. `php artisan migrate` → таблицы создаются
6. `composer remove ladcms/forum` → раздел исчезает

---

## 11. Защита и безопасность

| Механизм | Реализация | Применение |
|---|---|---|
| Подтверждение пароля | `password.confirm` middleware | Удаление аккаунта, смена email, админ-действия |
| Rate limiting | Laravel Rate Limiter | Логин, регистрация, отправка сообщений |
| CSRF | Laravel из коробки | Все формы |
| Баны | `CheckBan` middleware | Все запросы авторизованных пользователей |
| Подозрительные | Флаг `is_suspicious` на User | Ручная модерация |
| Аудит-лог | Логирование действий админов | В модуле `analytics` |

---

## 12. Что убрано из старого DCMS

| Фича | Причина |
|---|---|
| Браузерный детект / субдомены | Адаптивный дизайн |
| PNG-спрайт иконок | Lucide Vue |
| BBCode-парсер | Tiptap WYSIWYG |
| Captcha | Rate limiting + password.confirm |
| Псевдо-крон | Laravel Task Scheduling |
| Система авто-обновлений | `composer update` |
| INI-конфиги | Laravel config |
| Нативный PHP-шаблонизатор | Vue + Inertia |
| Отдельные мобильные темы | Один адаптивный дизайн |

---

## 13. Scheduled Tasks (крон)

Модули регистрируют задачи в своих ServiceProvider:

```php
// core
Schedule::command('ladcms:cleanup-online')->everyMinute();

// news
Schedule::command('news:send-newsletter')->hourly();

// analytics
Schedule::command('analytics:archive-logs')->daily();
```

Одна запись в системном crontab: `* * * * * cd /path && php artisan schedule:run`.
