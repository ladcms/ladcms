# ladcms/core

LADCMS core: users, roles, admin, module system.

## What core provides
- Models `User`, `Role`, `Ban`, `Complaint`, `LoginHistory`, `Module`; enums `Sex`, `MessagesFrom`, `BanType`, `ComplaintStatus`.
- Roles with levels 0-6 (`RolesSeeder`), core permissions (`PermissionsSeeder`), `UserPolicy`, `RolePolicy`, `User::outranks()`.
- Settings: `GeneralSettings`, `RegistrationSettings`, `ModerationSettings`, `AppearanceSettings` (defaults via settings migrations).
- Actions for install, auth/registration, users, moderation and module lifecycle (see the Boost guideline for the catalogue).
- Middleware appended to `web`: `CheckBan`, `TrackOnline`, `EnsureRegistrationOpen`, `SetLocale`. `AdminAccess` is only the route alias `admin` (used by module `routes/admin.php`); `ladcms.ban` is an alias for `CheckBan`.
- `MentionService`, `LocaleManager`, notifications (`ComplaintFiled`, `UserMentioned`, `UserBanned`), `GET /api/users/mention-search`, `/banned`.

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
