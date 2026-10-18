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
