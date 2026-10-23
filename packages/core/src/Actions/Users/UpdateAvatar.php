<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;
use Ladcms\Core\Models\User;

/**
 * Загружает аватар: валидация (тип, вес, габариты — защита от decompression bomb), единственное декодирование
 * исходника, ресайз cover() во все размеры из config('core.avatars.sizes') в памяти. Файлы перезаписываются на месте
 * (имена фиксированы), лишние в каталоге удаляются только после сохранения пользователя: сбой записи
 * не оставляет пользователя без аватара.
 */
class UpdateAvatar
{
    public function handle(User $user, UploadedFile $file): User
    {
        $max = (int) config('core.avatars.max_dimension', 4000);

        Validator::make(['avatar' => $file], [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', "dimensions:max_width={$max},max_height={$max}", 'max:'.(int) config('core.avatars.max_kb', 2048)],
        ])->validate();

        /** @var array<string, int> $sizes */
        $sizes = config('core.avatars.sizes', ['md' => 256, 'sm' => 64]);

        // Один раз декодируем исходник и кодируем все размеры в памяти: ошибка ресайза не затронет старые файлы.
        $source = ImageManager::gd()->read((string) $file->getRealPath());
        $encoded = [];
        foreach ($sizes as $name => $size) {
            $encoded[$name] = (clone $source)->cover($size, $size)->toJpeg(85)->toString();
        }
        unset($source);

        $disk = Storage::disk(User::avatarDisk());
        foreach ($encoded as $name => $binary) {
            $disk->put(User::avatarFile($user->id, (string) $name), $binary);
        }

        $user->avatar_path = User::avatarFile($user->id, 'md');
        $user->avatar_updated_at = now();
        $user->save();

        $keep = array_map(fn (string|int $name): string => User::avatarFile($user->id, (string) $name), array_keys($sizes));
        foreach ($disk->files(User::avatarDirectory($user->id)) as $existing) {
            if (! in_array($existing, $keep, true)) {
                $disk->delete($existing);
            }
        }

        return $user;
    }
}
