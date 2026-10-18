<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Admin\ActivitySubjects;
use Ladcms\Core\Admin\DashboardStats;
use Ladcms\Core\Http\Resources\Admin\ActivityResource;
use Ladcms\Core\Models\User;
use Spatie\Activitylog\Models\Activity;

class DashboardController
{
    public function __invoke(Request $request, DashboardStats $stats): Response
    {
        $user = $request->user();

        return Inertia::render('core::admin/dashboard', [
            'stats' => $stats->collect(),
            // Блоки с данными пользователей/журнала — только при соответствующих правах (admin.access их не даёт).
            'recent_activity' => $user?->can('activity.view') ? $this->recentActivity($request) : null,
            'recent_users' => $user?->can('users.view') ? $this->recentUsers() : null,
        ]);
    }

    /** @return array<array-key, mixed> */
    private function recentActivity(Request $request): array
    {
        $recentActivity = Activity::query()->with('causer')->latest()->orderByDesc('id')->limit(10)->get();
        ActivitySubjects::load($recentActivity);

        return ActivityResource::collection($recentActivity)->resolve($request);
    }

    /** @return list<array{id: int, login: string, created_at: ?string}> */
    private function recentUsers(): array
    {
        return User::query()->latest()->orderByDesc('id')->limit(5)->get(['id', 'login', 'created_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'login' => $user->login,
                'created_at' => $user->created_at?->toIso8601String(),
            ])->values()->all();
    }
}
