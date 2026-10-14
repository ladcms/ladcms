## LADCMS Core

Module system for LADCMS. Every module is a Composer package whose service provider extends
`Ladcms\Core\Module\ModuleServiceProvider` and implements `Has*` interfaces from `Ladcms\Core\Module\Contracts`.

### Conventions
- Business logic lives in `src/Actions/*` classes with a single public `handle()` method; controllers are thin.
- Inertia pages of a module are named `<module>::Path/Page` and live in `resources/js/pages`.
- npm dependencies are declared via `npmDependencies()` and installed with `php artisan ladcms:install <module>`.
- `coreVersion()` is a Composer constraint `^<major.minor>` of the installed core (core is `0.1.x` now, so `'^0.1'`).
  A module whose constraint is not satisfied (or is unparsable) is not booted and is listed in the registry failures.

### Users, roles and levels
- `Ladcms\Core\Models\User` (soft deletes; the app's `User` extends it). `login` is for sign-in and URLs, `name` is the display name.
- Roles carry a `level` (0-6, constants in `Support\RoleLevel`): guest 0, user 1, moderator 2, super_moderator 3, admin 4, main_admin 5, creator 6; `vip` has no level.
  System roles (`is_system`) are protected by `RolePolicy`. `$user->level()` is the max level of the user's roles.
- Permissions say what may be done; levels say who stands above whom: `$actor->outranks($target)` (strictly higher level).
- The `creator` Gate bypass applies only to plain permission checks (`can('x')`); policies still run.
- The last creator can never be deleted or demoted. Trashed users keep their login reserved.
- Modules may store `min_role_level` on their objects; compare it with `$user->level()` or use `RoleLevel`.
- Ban state: `$user->isBanned()`, `$user->canWrite()` (false for readonly bans). Online = activity within 10 minutes; `TrackOnline` writes at most once per 60 s.

### Settings (`HasSettings`)
A module lists its `Spatie\LaravelSettings\Settings` classes in `settings()` (`HasSettings`); they are registered automatically.
Defaults are seeded by settings migrations (they run with `migrate`), not by classes. Core ships
`GeneralSettings`, `RegistrationSettings`, `ModerationSettings`, `AppearanceSettings`.

### Permissions (`HasPermissions`)
`permissions()` returns `name => translation key`. Core permissions: `admin.access`, `users.view`, `users.edit`, `users.ban`,
`users.delete`, `users.assign-roles`, `roles.manage`, `settings.manage`, `modules.manage`, `complaints.review`, `activity.view`.
`PermissionsSeeder` only adds missing permissions and never revokes ones granted manually.

### Actions catalogue (`Ladcms\Core\Actions`)
- `Install\InstallSite` - one-time site setup (settings, roles, administrator); `Install\CreateAdministrator` - creates the creator account.
- `Auth\AuthenticateByLoginOrEmail` - credential lookup by login or email; `Auth\RegisterUser` - registration honoring registration settings.
- `Users\ChangeLogin`, `AssignRoles`, `MarkSuspicious`, `DeleteUser`, `UpdateAvatar`, `RemoveAvatar` - user management;
  `Users\PruneUnverifiedUsers` - deletes stale unverified accounts.
- `Moderation\BanUser`, `LiftBan` - bans; `FileComplaint`, `ReviewComplaint` - complaints.
- `Modules\InstallModule`, `UpdateInstalledModules`, `UninstallModule`, `SyncModulePermissions`, `SyncNpmDependencies` - module lifecycle.

### Authorization conventions
- Actions that affect users authorize via policies (`UserPolicy`, `RolePolicy`) and `User::outranks()`; policies allow self-edit in `UserPolicy::update`.
- Actions never touch HTTP (no `request()`, Inertia, redirects); Actions authorize themselves (policies via `Gate::forUser($actor)->authorize(...)`, or `can()` + `outranks()`); controllers only translate exceptions (`AuthorizationException` -> 403, `ValidationException` -> 422, `InvalidArgumentException` -> 422/409).

### Conventions a module must follow
- `name()` must equal the Composer package directory (`vendor/ladcms/<name>`): the frontend resolves
  `<name>::Page` to `vendor/ladcms/<name>/resources/js/pages/Page.tsx`.
- The provider class lives directly in `src/` (the module root is resolved as the provider's parent directory).
- When overriding `register()`, `boot()`, `packageRegistered()` or `packageBooted()`, always call `parent::…`:
  these methods register the module in the registry, validate it and load its config, routes, migrations and translations.
- Module names match `^[a-z][a-z0-9-]*$`.

@verbatim
<code-snippet name="Minimal module provider" lang="php">
final class ForumServiceProvider extends ModuleServiceProvider implements HasPermissions
{
    public function name(): string { return 'forum'; }
    public function version(): string { return '1.0.0'; }
    public function coreVersion(): string { return '^0.1'; }
    public function permissions(): array { return ['forum.view' => 'forum::permissions.view']; }
}
</code-snippet>
@endverbatim
