<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ladcms\Core\Models\User;

class MentionSearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:32']]);
        $q = trim((string) ($validated['q'] ?? ''));

        if ($q === '') {
            return response()->json([]);
        }

        // `!` как escape-символ: обратный слэш в литерале ESCAPE ломается в MySQL.
        $prefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';

        $users = User::query()
            ->where(fn (Builder $query) => $query
                ->whereRaw("login LIKE ? ESCAPE '!'", [$prefix])
                ->orWhereRaw("name LIKE ? ESCAPE '!'", [$prefix]))
            ->orderBy('login')
            ->limit(10)
            ->get(['id', 'login', 'name', 'avatar_path']);

        return response()->json($users->map(fn (User $user): array => [
            'id' => $user->id,
            'login' => $user->login,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
        ])->values());
    }
}
