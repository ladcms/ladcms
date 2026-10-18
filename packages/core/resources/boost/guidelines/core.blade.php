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
  Core resolves the app model via `Support\UserModel::class()/query()` (`auth.providers.users.model`); use it instead of
  `User::query()` so you get app instances. Every user class has morph alias `user` (roles, notifications, activity log)
  and spatie guard `web`.
- Roles carry a `level` (0-6, constants in `Support\RoleLevel`): guest 0, user 1, moderator 2, super_moderator 3, admin 4, main_admin 5, creator 6; `vip` has no level.
  System roles (`is_system`) can't be deleted and keep their `name`/`level` (`RolePolicy::rename`, `assignLevel`).
  Non-creators manage only roles below their own level. `$user->level()` is the max level of the user's loaded roles.
- Permissions say what may be done; levels say who stands above whom: `$actor->outranks($target)` (strictly higher level).
- The `creator` Gate bypass applies only to checks without arguments: plain permissions and module abilities
  defined without a model (`Gate::define('forum.moderate', fn ($user) => ...)`) pass for creators. Abilities called with
  a model/arguments (policies, `Gate::allows('x', $topic)`) run for creators too — handle creators there if needed.
- The last creator can never be deleted (including self-deletion) or demoted; creators can't change other creators' roles.
  Trashed users keep their login reserved.
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
- `Auth\AuthenticateByLoginOrEmail` - credential lookup by login or email; `Auth\RegisterUser` - registration honoring registration settings
  (`invite_only` requires a bound `Contracts\InviteValidator`, otherwise registration is refused; with verification off users are auto-verified).
- `Users\ChangeLogin`, `AssignRoles`, `MarkSuspicious`, `DeleteUser`, `UpdateAvatar`, `RemoveAvatar` - user management;
  `Users\DeleteOwnAccount` - self-deletion (refuses the last creator); `Users\PruneUnverifiedUsers` - deletes stale unverified
  accounts (no-op while email verification is disabled).
- `Moderation\BanUser`, `LiftBan` - bans; `FileComplaint`, `ReviewComplaint` - complaints.
- `Modules\InstallModule`, `UpdateInstalledModules`, `UninstallModule`, `SyncModulePermissions`, `SyncNpmDependencies` - module lifecycle.

### Authorization conventions
- Actions that take an `$actor` authorize themselves via policies (`UserPolicy`, `RolePolicy`) and `User::outranks()`; policies allow self-edit in `UserPolicy::update`.
- Subject-only actions (no `$actor`: `ChangeLogin`, `UpdateAvatar`, `RemoveAvatar`, `DeleteOwnAccount`) do not authorize; the caller must.
- Actions never touch HTTP (no `request()`, Inertia, redirects); Actions authorize themselves (policies via `Gate::forUser($actor)->authorize(...)`, or `can()` + `outranks()`); controllers only translate exceptions (`AuthorizationException` -> 403, `ValidationException` -> 422, `InvalidArgumentException` -> 422/409).

### Admin panel for modules
- A module appears in the admin by implementing `HasAdminPanel::adminMenu()`: a list of sections `label` (translation key), `icon` (lucide name, kebab-case),
  `route`, `order`, optional `permission` (item hidden without it), `children`, `key`, `path`, `params`. Core builds the two-level menu (`Admin\AdminMenu`) and shares it as the `admin` Inertia prop.
- Admin routes go to `routes/admin.php`: the base class mounts them under `/admin/<module>` with names `admin.<module>.*` and middleware
  `web, auth, admin, ladcms.password-confirm`. Protect each route with `can:<permission>`; mutations authorize again inside Actions.
- Admin pages are Inertia components named `<module>::admin/<Path>` and live in `resources/js/pages/admin/<Path>.tsx` (e.g. `forum::admin/topics/index`).
  Build them only from the core UI kit: `@ladcms/core/components/*` (`PageHeader`, `DataTable`, `EmptyState`, `ConfirmDialog`, `ui/*`) and `@ladcms/core/layouts/admin-layout`.
  Set `Page.layout = { title: '<module>::admin.<key>', breadcrumbs: [{ title: '<module>::admin.<key>', href }] }` - the layout translates the keys.
- Tables: return a paginator (`LengthAwarePaginator::toArray()` shape) and render with `<DataTable columns rows pagination sort />`.
  Server side use `Admin\TableQuery::apply($query, $request, $sortable, $defaultSort, $searchable)` + `TableQuery::paginate()` (whitelisted sort, escaped search, `page`/`sort`/`direction`/`q` query params).
- Settings forms are generated: list `Settings` classes in `settings()`; `SettingsSchema` infers the field type from the PHP type and `#[Field(type:, options:, description:, min:, max:)]`
  refines it (`options` is a provider name: `locales`, `themes`, `timezones`; `min`/`max` bound a number's value, a text's length or a list's size,
  Number fields default to `min: 0`). Labels come from `<module>::settings.<group>.title` / `.fields.<name>.label`.
  The settings page `admin.settings.edit` gets a tab per group of every module.
- Texts: never hard-code UI strings. Put them in `lang/<locale>/admin.php` of the module (both `ru` and `en`) and read them with `const t = useT()` from `@ladcms/core/lib/i18n`:
  `t('<module>::admin.some.key', { name })` (`:name` placeholders, falls back to the key). Core shares a flat `i18n` map built from every module's `admin` lang namespace on `admin.*` routes only.
  Server exception messages stay English; user-facing flash toasts use `Inertia::flash('toast', [...])` with translated text.
- The admin can disable a module on the Modules page (`modules.is_enabled`); it is skipped on the next boot. Octane and queue workers keep the old module set in memory:
  restart them (`octane:reload`, `queue:restart`) after toggling a module. With cached routes (`route:cache`) the toggle takes effect only after
  `php artisan route:clear` (or `optimize:clear`); the Modules page gets `routes_cached` and shows a warning. The web request never calls Artisan itself.
- Admin lint: `npm run check:fix` in the app also lints `vendor/ladcms/*/resources/js` (UI kit `components/ui/**` is excluded).

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
