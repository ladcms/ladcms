<?php

declare(strict_types=1);

namespace Ladcms\Core\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Ladcms\Core\Models\User;
use Ladcms\Core\Notifications\UserMentioned;
use Ladcms\Core\Support\UserModel;

class MentionService
{
    /**
     * Находит упомянутых пользователей: `<span data-mention data-user-id="N">` (порядок атрибутов любой).
     *
     * @return Collection<int, User>
     */
    public function extract(string $html): Collection
    {
        preg_match_all('/<span\b[^>]*>/i', $html, $tags);

        $ids = [];

        foreach ($tags[0] as $tag) {
            if (preg_match('/\sdata-mention\b/i', $tag) === 1 && preg_match('/\sdata-user-id\s*=\s*["\']?(\d+)/i', $tag, $m) === 1) {
                $ids[(int) $m[1]] = true;
            }
        }

        if ($ids === []) {
            return new Collection;
        }

        return UserModel::query()->whereIn('id', array_keys($ids))->get();
    }

    /** Отправляет `UserMentioned` всем упомянутым, кроме автора; возвращает число получателей. */
    public function notify(string $html, User $author, Model $context, string $url): int
    {
        $users = $this->extract($html)->reject(fn (User $user): bool => $user->is($author));

        if ($users->isNotEmpty()) {
            Notification::send($users, new UserMentioned($author, $context, $url));
        }

        return $users->count();
    }
}
