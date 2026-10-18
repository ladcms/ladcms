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
use Ladcms\Core\Actions\Users\AssignRoles;
use Ladcms\Core\Actions\Users\ChangeLogin;
use Ladcms\Core\Actions\Users\DeleteUser;
use Ladcms\Core\Actions\Users\MarkSuspicious;
use Ladcms\Core\Actions\Users\UpdateUserProfile;
use Ladcms\Core\Http\Requests\Admin\ChangeLoginRequest;
use Ladcms\Core\Http\Requests\Admin\MarkSuspiciousRequest;
use Ladcms\Core\Http\Requests\Admin\UpdateUserRequest;
use Ladcms\Core\Http\Resources\Admin\BanResource;
use Ladcms\Core\Http\Resources\Admin\RoleResource;
use Ladcms\Core\Http\Resources\Admin\UserDetailResource;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\ModerationSettings;

/**
 * Карточка пользователя в админке. Тонкий слой: авторизация и правила — в Actions
 * (`AuthorizationException` → 403 рендерит Laravel), инварианты (`InvalidArgumentException`) → `errors.general`.
 */
class UserController
{
    public function show(Request $request, #[CurrentUser] User $actor, User $user, ModerationSettings $settings): Response
    {
        $user->load('roles');
        $gate = Gate::forUser($actor);

        return Inertia::render('core::admin/users/show', [
            'user' => UserDetailResource::make($user)->resolve($request),
            'roles' => RoleResource::collection(Role::query()->orderBy('level')->orderBy('name')->get())->resolve($request),
            'assignable_roles' => AssignRoles::assignableBy($actor)->pluck('name')->values()->all(),
            'violation_codes' => $settings->violation_codes,
            'bans' => BanResource::collection(
                $user->bans()->with(['user', 'bannedBy'])->latest('starts_at')->latest('id')->get()
            )->resolve($request),
            'can' => [
                'update' => $gate->allows('update', $user),
                'assignRoles' => $gate->allows('assignRoles', $user),
                'ban' => $gate->allows('ban', $user),
                'delete' => $gate->allows('delete', $user),
                // Те же условия, что проверяет MarkSuspicious.
                'markSuspicious' => $actor->can('users.edit') && $actor->outranks($user),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, #[CurrentUser] User $actor, User $user, UpdateUserProfile $action): RedirectResponse
    {
        $action->handle($actor, $user, $request->validated());

        return $this->saved();
    }

    public function changeLogin(ChangeLoginRequest $request, #[CurrentUser] User $actor, User $user, ChangeLogin $action): RedirectResponse
    {
        // ChangeLogin работает только с субъектом — права проверяем здесь.
        Gate::forUser($actor)->authorize('update', $user);
        $action->handle($user, (string) $request->validated('login'), $actor);

        return $this->saved();
    }

    public function markSuspicious(MarkSuspiciousRequest $request, #[CurrentUser] User $actor, User $user, MarkSuspicious $action): RedirectResponse
    {
        $action->handle($actor, $user, $request->boolean('is_suspicious'), $request->note());

        return $this->saved();
    }

    public function destroy(#[CurrentUser] User $actor, User $user, DeleteUser $action): RedirectResponse
    {
        try {
            $action->handle($actor, $user);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::admin.user_deleted')]);

        return to_route('admin.users.index');
    }

    private function saved(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::admin.saved')]);

        return back();
    }
}
