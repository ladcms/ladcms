## LADCMS Core

Module system for LADCMS. Every module is a Composer package whose service provider extends
`Ladcms\Core\Module\ModuleServiceProvider` and implements `Has*` interfaces from `Ladcms\Core\Module\Contracts`.

### Conventions
- Business logic lives in `src/Actions/*` classes with a single public `handle()` method; controllers are thin.
- Inertia pages of a module are named `<module>::Path/Page` and live in `resources/js/pages`.
- npm dependencies are declared via `npmDependencies()` and installed with `php artisan ladcms:install <module>`.
- `coreVersion()` is a Composer constraint `^<major.minor>` of the installed core (core is `0.1.x` now, so `'^0.1'`).
  A module whose constraint is not satisfied (or is unparsable) is not booted and is listed in the registry failures.

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
