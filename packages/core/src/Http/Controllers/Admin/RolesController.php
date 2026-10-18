<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Admin;

use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Ladcms\Core\Actions\Roles\CreateRole;
use Ladcms\Core\Actions\Roles\DeleteRole;
use Ladcms\Core\Actions\Roles\SyncRolePermissions;
use Ladcms\Core\Actions\Roles\UpdateRole;
use Ladcms\Core\Http\Requests\Admin\StoreRoleRequest;
use Ladcms\Core\Http\Requests\Admin\SyncRolePermissionsRequest;
use Ladcms\Core\Http\Requests\Admin\UpdateRoleRequest;
use Ladcms\Core\Http\Resources\Admin\RoleResource;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasPermissions;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Support\RolePermissionGuard;
use Spatie\Permission\Models\Permission;

/**
 * Роли и права в админке. Авторизация и инварианты — в Actions
 * (`AuthorizationException` → 403 рендерит Laravel), нарушение инварианта → `errors.general`.
 */
class RolesController
{
    public function index(Request $request, #[CurrentUser] User $actor): Response
    {
        $roles = Role::query()->withCount(['users', 'permissions'])->orderBy('level')->orderBy('name')->get();

        return Inertia::render('core::admin/roles/index', [
            'roles' => RoleResource::collection($roles)->resolve($request),
            'can' => ['create' => Gate::forUser($actor)->allows('create', Role::class)],
        ]);
    }

    public function store(StoreRoleRequest $request, #[CurrentUser] User $actor, CreateRole $action): RedirectResponse
    {
        try {
            $role = $action->handle($actor, $request->roleData());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::admin.saved')]);

        return to_route('admin.roles.edit', $role);
    }

    public function edit(Request $request, #[CurrentUser] User $actor, Role $role, ModuleRegistry $modules): Response
    {
        $gate = Gate::forUser($actor);
        $canUpdate = $gate->allows('update', $role);

        $labels = [];
        foreach ($modules->implementing(HasPermissions::class) as $module) {
            $labels += $module->permissions();
        }

        $names = Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->all();
        $grouped = [];
        foreach ($names as $name) {
            $key = $labels[$name] ?? null;
            $translated = $key === null ? $name : __($key);
            $grouped[explode('.', $name, 2)[0]][] = ['name' => $name, 'label' => $translated === $key ? $name : $translated];
        }

        $grantable = match (true) {
            ! $canUpdate => [],
            default => RolePermissionGuard::grantable($actor, $names),
        };

        // Проба «другой уровень»: policy проверяет право менять уровень именно этой роли.
        $levelProbe = $role->level === null ? 0 : null;

        return Inertia::render('core::admin/roles/edit', [
            'role' => RoleResource::make($role->loadCount(['users', 'permissions']))->resolve($request),
            'permissions' => $grouped,
            'granted' => $role->permissions()->orderBy('name')->pluck('name')->all(),
            'grantable' => $grantable,
            'can' => [
                'update' => $canUpdate,
                'rename' => $gate->allows('rename', $role),
                'assignLevel' => $gate->allows('assignLevel', [Role::class, $levelProbe, $role]),
                'delete' => $gate->allows('delete', $role),
            ],
        ]);
    }

    public function update(UpdateRoleRequest $request, #[CurrentUser] User $actor, Role $role, UpdateRole $action): RedirectResponse
    {
        try {
            $action->handle($actor, $role, $request->roleData());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return $this->saved();
    }

    public function permissions(SyncRolePermissionsRequest $request, #[CurrentUser] User $actor, Role $role, SyncRolePermissions $action): RedirectResponse
    {
        try {
            $action->handle($actor, $role, $request->permissionNames());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return $this->saved();
    }

    public function destroy(#[CurrentUser] User $actor, Role $role, DeleteRole $action): RedirectResponse
    {
        try {
            $action->handle($actor, $role);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::admin.saved')]);

        return to_route('admin.roles.index');
    }

    private function saved(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::admin.saved')]);

        return back();
    }
}
