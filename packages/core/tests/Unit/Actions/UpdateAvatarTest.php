<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Users\RemoveAvatar;
use Ladcms\Core\Actions\Users\UpdateAvatar;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

/** @return array{0: int, 1: int} */
function avatarSize(string $path): array
{
    $info = getimagesizefromstring((string) Storage::disk('public')->get($path));

    return [$info[0], $info[1]];
}

it('stores resized avatars in two sizes', function () {
    $user = User::factory()->create();

    $user = app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('a.png', 800, 600));

    $disk = Storage::disk('public');
    expect($disk->exists("avatars/{$user->id}/md.jpg"))->toBeTrue()
        ->and($disk->exists("avatars/{$user->id}/sm.jpg"))->toBeTrue()
        ->and(avatarSize("avatars/{$user->id}/md.jpg"))->toBe([256, 256])
        ->and(avatarSize("avatars/{$user->id}/sm.jpg"))->toBe([64, 64])
        ->and($user->fresh()?->avatar_path)->toBe("avatars/{$user->id}/md.jpg")
        ->and($user->avatar_url)->not->toBeNull()
        ->and($user->avatar_url)->toContain("avatars/{$user->id}/md.jpg")
        ->and($user->avatarUrl('sm'))->toContain("avatars/{$user->id}/sm.jpg");
});

it('returns null avatar url without avatar', function () {
    $user = User::factory()->create(['avatar_path' => null]);

    expect($user->avatar_url)->toBeNull()
        ->and($user->avatarUrl('sm'))->toBeNull();
});

it('replaces the previous avatar on re-upload', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Storage::disk('public')->put("avatars/{$other->id}/md.jpg", 'other');

    $user = app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('a.png', 800, 600));
    Storage::disk('public')->put("avatars/{$user->id}/stale.jpg", 'stale');
    $user = app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('b.jpg', 300, 900));

    expect(Storage::disk('public')->files("avatars/{$user->id}"))->toHaveCount(2)
        ->and(avatarSize("avatars/{$user->id}/md.jpg"))->toBe([256, 256])
        ->and(Storage::disk('public')->get("avatars/{$other->id}/md.jpg"))->toBe('other');
});

it('removes the avatar', function () {
    $user = User::factory()->create();
    $user = app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('a.png', 800, 600));

    $user = app(RemoveAvatar::class)->handle($user);

    expect(Storage::disk('public')->files("avatars/{$user->id}"))->toBeEmpty()
        ->and($user->fresh()?->avatar_path)->toBeNull()
        ->and($user->avatar_url)->toBeNull();
});

it('rejects non-image files', function () {
    $user = User::factory()->create();

    expect(fn () => app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->create('a.txt', 10, 'text/plain')))
        ->toThrow(ValidationException::class);
});

it('rejects oversized files', function () {
    $user = User::factory()->create();

    expect(fn () => app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('big.png', 100, 100)->size(3000)))
        ->toThrow(ValidationException::class)
        ->and(Storage::disk('public')->files("avatars/{$user->id}"))->toBeEmpty();
});
