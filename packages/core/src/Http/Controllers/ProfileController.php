<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Http\Resources\ProfileResource;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\LoginRules;
use Ladcms\Core\Support\UserModel;
use Ladcms\Core\Ui\ProfileSections;

/** Публичный профиль `/u/{login}`: шапка, вкладки, «Обзор» и секции модулей. */
class ProfileController
{
    public function show(Request $request, string $login, ProfileSections $sections): Response|RedirectResponse
    {
        $user = UserModel::class()::findByLogin($login);
        abort_if(! $user instanceof User, 404);

        $tab = $request->query('tab');
        $tab = is_string($tab) ? $tab : null;

        // Старый логин → постоянный редирект на текущий (вкладка сохраняется).
        if (LoginRules::normalize($user->login) !== LoginRules::normalize($login)) {
            return redirect()->route('users.show', array_filter(['login' => $user->login, 'tab' => $tab]), 301);
        }

        $viewer = $request->user();
        $viewer = $viewer instanceof User ? $viewer : null;

        $user->load('roles');

        $active = $tab !== null && $sections->hasModuleTab($user, $viewer, $tab) ? $tab : ProfileSections::OVERVIEW;

        return Inertia::render('core::users/show', [
            'profile' => fn (): array => ProfileResource::make($user)->resolve($request),
            'tabs' => fn (): array => $sections->tabs($user, $viewer),
            'active_tab' => fn (): string => $active,
            'overview_blocks' => fn (): array => $sections->overview($user, $viewer),
            'actions' => fn (): array => $sections->actions($user, $viewer),
            'moderation' => fn (): array => $this->moderation($user, $viewer),
            // Данные вкладки модуля — сразу при заходе по `?tab=`, иначе только по запросу partial reload.
            'tab_data' => $active === ProfileSections::OVERVIEW
                ? Inertia::optional(fn (): array => [])
                : fn (): array => $sections->tabData($user, $viewer, $active),
        ]);
    }

    /** @return array{can_ban: bool, can_assign_roles: bool, can_mark_suspicious: bool, can_edit: bool, admin_url: ?string} */
    private function moderation(User $target, ?User $viewer): array
    {
        if (! $viewer instanceof User) {
            return ['can_ban' => false, 'can_assign_roles' => false, 'can_mark_suspicious' => false, 'can_edit' => false, 'admin_url' => null];
        }

        $gate = Gate::forUser($viewer);

        return [
            'can_ban' => $gate->allows('ban', $target),
            'can_assign_roles' => $gate->allows('assignRoles', $target),
            // Те же условия, что проверяет MarkSuspicious (см. админский UserController::show).
            'can_mark_suspicious' => $viewer->can('users.edit') && $viewer->outranks($target),
            'can_edit' => $gate->allows('update', $target),
            'admin_url' => $viewer->can('users.view') ? route('admin.users.show', $target) : null,
        ];
    }
}
