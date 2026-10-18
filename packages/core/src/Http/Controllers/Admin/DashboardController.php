<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Admin\DashboardStats;
use Ladcms\Core\Http\Resources\Admin\ActivityResource;
use Ladcms\Core\Models\User;
use Spatie\Activitylog\Models\Activity;

class DashboardController
{
    public function __invoke(Request $request, DashboardStats $stats): Response
    {
        $recentActivity = Activity::query()->with(['causer', 'subject'])->latest()->orderByDesc('id')->limit(10)->get();
        $recentUsers = User::query()->latest()->orderByDesc('id')->limit(5)->get(['id', 'login', 'created_at']);

        return Inertia::render('core::admin/dashboard', [
            'stats' => $stats->collect(),
            'recent_activity' => ActivityResource::collection($recentActivity)->resolve($request),
            'recent_users' => $recentUsers->map(fn (User $user): array => [
                'id' => $user->id,
                'login' => $user->login,
                'created_at' => $user->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
