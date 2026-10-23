<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;
use Ladcms\Core\Models\User;

/**
 * Загружает аватар: валидация (тип, вес, габариты — защита от decompression bomb), ресайз cover() во все размеры
 * из config('core.avatars.sizes') в памяти; старые файлы заменяются только после успешной генерации всех вариантов.
 */
class UpdateAvatar
{
    public function handle(User $user, UploadedFile $file): User
    {
        Validator::make(['avatar' => $file], [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=8000,max_height=8000', 'max:'.(int) config('core.avatars.max_kb', 2048)],
        ])->validate();

        $manager = ImageManager::gd();
        /** @var array<string, int> $sizes */
        $sizes = config('core.avatars.sizes', ['md' => 256, 'sm' => 64]);

        // Сначала кодируем все размеры в памяти: ошибка декодирования/ресайза не оставит пользователя без старого аватара.
        $encoded = [];
        foreach ($sizes as $name => $size) {
            $encoded[$name] = $manager->read((string) $file->getRealPath())->cover($size, $size)->toJpeg(85)->toString();
        }

        $disk = Storage::disk(User::avatarDisk());
        $disk->deleteDirectory(User::avatarDirectory($user->id));
        foreach ($encoded as $name => $binary) {
            $disk->put(User::avatarFile($user->id, $name), $binary);
        }

        $user->avatar_path = User::avatarFile($user->id, 'md');
        $user->save();

        return $user;
    }
}
