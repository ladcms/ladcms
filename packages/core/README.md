# ladcms/core

LADCMS core: users, roles, admin, module system.

## What core provides
- Models `User`, `Role`, `Ban`, `Complaint`, `LoginHistory`, `Module`; enums `Sex`, `MessagesFrom`, `BanType`, `ComplaintStatus`.
- Roles with levels 0-6 (`RolesSeeder`), core permissions (`PermissionsSeeder`), `UserPolicy`, `RolePolicy`, `User::outranks()`.
- Settings: `GeneralSettings`, `RegistrationSettings`, `ModerationSettings`, `AppearanceSettings` (defaults via settings migrations).
- Actions for install, auth/registration, users, moderation and module lifecycle (see the Boost guideline for the catalogue).
- Middleware appended to `web`: `CheckBan`, `TrackOnline`, `EnsureRegistrationOpen`, `SetLocale`. `AdminAccess` is only the route alias `admin` (used by module `routes/admin.php`); `ladcms.ban` is an alias for `CheckBan`.
- `MentionService`, `LocaleManager`, notifications (`ComplaintFiled`, `UserMentioned`, `UserBanned`), `GET /api/users/mention-search`, `/banned`.

## Admin panel
- `/admin` (permission `admin.access`, password re-confirmation with the TTL from settings): dashboard, users (list, profile, roles, bans, suspicious flag, login change, delete),
  roles and permission matrix, moderation (complaints, bans), settings forms generated from `Settings` classes, modules (status, enable/disable), activity log, notification bell.
- Two-level menu: an icon rail plus a section panel (hover on desktop, tap on touch). Modules add sections through `HasAdminPanel::adminMenu()`.
- UI kit lives in `resources/js` (`components/ui/*`, `DataTable`, `PageHeader`, `ConfirmDialog`, ...) and is imported as `@ladcms/core/*`.
- Admin texts: `lang/{ru,en}/admin.php` + `useT()` (`@ladcms/core/lib/i18n`); the `i18n` Inertia prop is shared on admin routes only.
- Toggling a module changes its routes only on the next boot: with `route:cache` run `php artisan route:clear` (or `optimize:clear`) afterwards, the Modules page shows a warning while routes are cached. Octane/queue workers must be restarted after toggling a module. JS lint/format of the packages: `cd app && npm run check:fix`.

## Public site
- Routes: `/` (home with widget slots), `/users` (searchable, sortable list), `/u/{login}` (profile: header, tabs, overview), `/rules` (sanitised Markdown), `/notifications`, `/settings/*` (profile, avatar, privacy, notifications, appearance), `/banned`.
- `PublicLayout` (header with navigation, search, header actions and the notification bell; sidebar widgets; footer with language switcher; bottom icon bar below `lg`, with a "More" sheet when there are more than four navigation items).
- Modules extend it through `HasNavigation`, `HasWidgets`, `HasHeaderActions`, `HasProfileSections`, `HasProfileActions` (see the Boost guideline "Public UI for modules").
- Error pages (`core::error`) for 403/404/500/503 via `ErrorPageRenderer`, SEO/Open Graph meta via `<Seo>`, accessibility landmarks, skip link and keyboard navigation.
- Public texts: `lang/{ru,en}/public.php` + `useT()`; the `i18n` Inertia prop is a once-prop keyed by locale and scope.

## Install flow
```
composer require ladcms/core
php artisan ladcms:install          # site wizard: migrate, roles, administrator, settings
php artisan ladcms:install <module> # install a module (idempotent)
```

## Commands
- `ladcms:install [module]` - site wizard without an argument, module install with one.
- `ladcms:update` - re-install all installed modules.
- `ladcms:uninstall <module>` - uninstall a module.
- `ladcms:prune-unverified [--days=]` - delete long-unverified accounts (scheduled).

## Tests
From the monorepo root: `vendor/bin/pest` (all), `vendor/bin/pest packages/core` (core only), `vendor/bin/pint --test packages/core` (style).
