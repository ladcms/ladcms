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

it('rejects images exceeding the dimension limit', function () {
    $user = User::factory()->create();

    expect(fn () => app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('wide.png', 4001, 100)))
        ->toThrow(ValidationException::class)
        ->and(Storage::disk('public')->files("avatars/{$user->id}"))->toBeEmpty();
});

it('accepts an image just below the dimension limit', function () {
    $user = User::factory()->create();

    $user = app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('wide.png', 4000, 50));

    expect($user->avatar_path)->not->toBeNull();
});

it('changes the avatar url after a re-upload and has no version without avatar', function () {
    $user = User::factory()->create(['avatar_path' => null]);
    expect($user->avatarUrl())->toBeNull();

    $user = app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('a.png', 300, 300));
    $first = $user->avatar_url;
    expect($first)->toContain('?v=');

    $this->travel(5)->seconds();
    $user = app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('b.png', 300, 300));

    expect($user->avatar_url)->not->toBe($first)->and($user->avatar_url)->toContain('?v=');

    $user = app(RemoveAvatar::class)->handle($user);
    expect($user->avatar_updated_at)->toBeNull()->and($user->avatar_url)->toBeNull();
});

it('prunes files that are not configured sizes after a successful upload', function () {
    $user = User::factory()->create();
    Storage::disk('public')->put("avatars/{$user->id}/stale.jpg", 'stale');

    app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('a.png', 300, 300));

    expect(Storage::disk('public')->exists("avatars/{$user->id}/stale.jpg"))->toBeFalse();
});

it('never loses the avatar when a disk write fails midway', function () {
    $user = User::factory()->create();
    $user = app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('a.png', 800, 600));
    $before = Storage::disk('public')->get("avatars/{$user->id}/md.jpg");

    $real = Storage::disk('public');
    $failing = Mockery::mock($real)->makePartial();
    $failing->shouldReceive('put')->andReturnUsing(function (string $path, $contents) use ($real) {
        static $calls = 0;
        if (++$calls === 2) {
            throw new RuntimeException('disk full');
        }

        return $real->put($path, $contents);
    });
    Storage::set('public', $failing);

    expect(fn () => app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('b.png', 300, 300)))->toThrow(RuntimeException::class);

    expect($real->exists("avatars/{$user->id}/md.jpg"))->toBeTrue()
        ->and($real->exists("avatars/{$user->id}/sm.jpg"))->toBeTrue()
        ->and($user->fresh()?->avatar_path)->toBe("avatars/{$user->id}/md.jpg")
        ->and($real->get("avatars/{$user->id}/md.jpg"))->not->toBe('');
    unset($before);
});

it('keeps the old avatar when an image passes validation but cannot be decoded', function () {
    $user = User::factory()->create();
    $user = app(UpdateAvatar::class)->handle($user, UploadedFile::fake()->image('a.png', 800, 600));
    $before = Storage::disk('public')->get("avatars/{$user->id}/md.jpg");

    // Обрезанный PNG: заголовок читается (проходит image/mimes/dimensions), а полное декодирование падает.
    $source = UploadedFile::fake()->image('b.png', 400, 400);
    $full = (string) file_get_contents((string) $source->getRealPath());
    $broken = UploadedFile::fake()->createWithContent('broken.png', substr($full, 0, (int) (strlen($full) * 0.5)));

    expect(fn () => app(UpdateAvatar::class)->handle($user, $broken))->toThrow(Exception::class)
        ->and(Storage::disk('public')->get("avatars/{$user->id}/md.jpg"))->toBe($before)
        ->and(Storage::disk('public')->files("avatars/{$user->id}"))->toHaveCount(2)
        ->and($user->fresh()?->avatar_path)->toBe("avatars/{$user->id}/md.jpg");
});
