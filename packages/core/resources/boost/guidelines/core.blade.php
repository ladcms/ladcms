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
  `t('<module>::admin.some.key', { name })` (`:name` placeholders, falls back to the key). The `admin` namespace is included in the shared `i18n` map on `admin.*` routes (see below).
  Server exception messages stay English; user-facing flash toasts use `Inertia::flash('toast', [...])` with translated text.
- The admin can disable a module on the Modules page (`modules.is_enabled`); it is skipped on the next boot. Octane and queue workers keep the old module set in memory:
  restart them (`octane:reload`, `queue:restart`) after toggling a module. With cached routes (`route:cache`) the toggle takes effect only after
  `php artisan route:clear` (or `optimize:clear`); the Modules page gets `routes_cached` and shows a warning. The web request never calls Artisan itself.
- Admin lint: `npm run check:fix` in the app also lints `vendor/ladcms/*/resources/js` (UI kit `components/ui/**` is excluded).

### Public UI for modules
- A module plugs into the public site by implementing `Has*` contracts; core collects them (`Ui\Navigation`, `Ui\Widgets`, `Ui\HeaderActions`, `Ui\ProfileSections`) and shares the result as the `layout` Inertia prop
  (`null` on `admin.*`). Entries that are malformed, throw, or point to a missing component file are skipped and logged once - they never break the page. Sorting is by `order`, ties by key/name.
  - `HasNavigation::navigation()`: `key`, `label` (fallback; the text is `<module>::public.nav.<key>`), `icon` (lucide, kebab-case), `route` (name) or `path`, `order`, optional `params`, `permission` (hidden without it).
  - `HasWidgets::widgets()`: `name`, `component` (`<module>::<Name>`), `slot` (`sidebar` | `main`), `order`, optional `props` as an array or a closure `fn (?User $user): array` (evaluated lazily, only for the requested slot).
  - `HasHeaderActions::headerActions()`: `key`, `icon`, `component`, `order`, optional `auth` (signed-in users only).
  - `HasProfileSections::profileSections()`: `tab` (`label`, `component`; key = module name, opened with `?tab=<module>`), `overview` (`component`, `order`), `counters` (`Closure(User $target, ?User $viewer)`: list of `key`, `label`, `value`, optional `tab`).
    Optional `profileTabData(User $target, ?User $viewer): array` (checked with `method_exists`) feeds the `tab_data` prop; it is computed only when the module tab is open.
  - `HasProfileActions::profileActions()`: `label`, `icon`, `component`, `order`, optional `permission` (viewers without it do not see the button). Guests and the profile owner never see module actions.
- Components: `<module>::<Name>` resolves to `resources/js/<kind>/<Name>.tsx` of the module with kind `widgets` | `profile` | `header-actions` (flat, no nested directories).
  Widgets get their `props`; profile tab components `{profile, tab_data}`, overview blocks and actions `{profile}`; header actions get nothing. They are lazy and wrapped in an error boundary; a missing file hides the slot.
- Public pages of a module are `<module>::Path/Page` (`resources/js/pages/...`) and render inside `PublicLayout` automatically (admin pages: `AdminLayout`). Use `<Seo title description image type />`
  (`@ladcms/core/components/public/seo`) for `<title>`, description and Open Graph tags.
- Texts: `lang/{ru,en}/public.php` of the module, keys `<module>::public.*`, read with `useT()` (`@ladcms/core/lib/i18n`). `i18n` is a normal shared prop sent with every Inertia response
  (built per request by `Support\TranslationMap` in `SharePublicLayout`): a flat map of strings for the current locale with `<module>::public.*` of every module everywhere,
  plus `<module>::admin.*` on `admin.*` routes. Placeholders (`:name`) are replaced in a single pass.
- `layout` share: `navigation` (`key, label, icon, url, order, active`), `header_actions` (`key, icon, component, order`), `widgets.sidebar` / `widgets.main` (`main` only on `home`; `name, component, module, order, props`),
  `site` (`name`, `url`, `description`, `logo_url`, `registration_open` from `RegistrationSettings::isAvailable()` - use it instead of reading registration settings yourself).
- `auth.user` (`CurrentUserResource`): `id, login, name, display_name, email, avatar_url, roles, permissions, level, is_creator, is_banned, can_write`; `roles` is a list of `{name, color}` (admin-configured hex or `null`), not strings.
  `can_write` is `false` for banned users and during the post-registration readonly period: forms that change public content must check `canWrite()` on the server and disable controls in the UI.
- Privacy: user data for other people goes through `PublicUserResource` / `ProfileResource` only. `email` and `birth_date` are exposed when the user enabled `show_email` / `show_birthday` or the viewer is the owner;
  suspicious flag, raw activity and ban reasons are never exposed (only a public boolean `is_banned`). Never serialize a `User` model into props.
- Notification types appear in the user's notification settings through `Support\NotificationTypes::register('<key>', <Notification::class>)` (call it in `boot()`; `mergeConfigFrom` is shallow).
- Error pages: `403/404/405/500/503` render `core::error` through `Http\ErrorPageRenderer` (wired in the app's `bootstrap/app.php`); in debug mode a 500 keeps Laravel's debug page; `419` redirects back with a toast. JSON requests are untouched. Unknown URLs hit a GET-only `Route::fallback` in the `web` group (so a POST there is a 405); asset-like paths (`*.ico`, `*.map`, `*.php`, `*.js`, …) get a plain 404 without the layout share or Inertia.
- Accessibility: skip link and `#main` in `PublicLayout`, labelled `nav` landmarks, `aria-current` on active links, icon-only controls need `aria-label`, `focus-visible:ring-2 focus-visible:ring-ring` on interactive elements, tabs use `role=tablist/tab/tabpanel` with arrow keys.
- Every public GET route must render an existing page: keep `PublicSmokeTest` green.

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
