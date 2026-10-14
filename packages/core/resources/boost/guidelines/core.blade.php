## LADCMS Core

Module system for LADCMS. Every module is a Composer package whose service provider extends
`Ladcms\Core\Module\ModuleServiceProvider` and implements `Has*` interfaces from `Ladcms\Core\Module\Contracts`.

### Conventions
- Business logic lives in `src/Actions/*` classes with a single public `handle()` method; controllers are thin.
- Inertia pages of a module are named `<module>::Path/Page` and live in `resources/js/pages`.
- npm dependencies are declared via `npmDependencies()` and installed with `php artisan ladcms:install <module>`.

@verbatim
<code-snippet name="Minimal module provider" lang="php">
final class ForumServiceProvider extends ModuleServiceProvider implements HasPermissions
{
    public function name(): string { return 'forum'; }
    public function version(): string { return '1.0.0'; }
    public function coreVersion(): string { return '^1.0'; }
    public function permissions(): array { return ['forum.view' => 'forum::permissions.view']; }
}
</code-snippet>
@endverbatim
