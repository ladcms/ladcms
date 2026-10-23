<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Support\Facades\Storage;
use Ladcms\Core\Models\User;

/** Удаляет файлы аватара пользователя (только его каталог) и обнуляет avatar_path. */
class RemoveAvatar
{
    public function handle(User $user): User
    {
        Storage::disk(User::avatarDisk())->deleteDirectory(User::avatarDirectory($user->id));

        $user->avatar_path = null;
        $user->avatar_updated_at = null;
        $user->save();

        return $user;
    }
}
