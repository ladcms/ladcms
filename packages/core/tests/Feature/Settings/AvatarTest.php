<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(User::avatarDisk());
    $this->user = User::factory()->create(['avatar_path' => null]);
});

/** Кладёт «старый» аватар пользователя. */
function seedAvatar(User $user): void
{
    $disk = Storage::disk(User::avatarDisk());
    foreach (array_keys((array) config('core.avatars.sizes')) as $name) {
        $disk->put(User::avatarFile($user->id, (string) $name), 'old-'.$name);
    }
    $user->forceFill(['avatar_path' => User::avatarFile($user->id, 'md')])->save();
}

function assertOldAvatarIntact(User $user): void
{
    $disk = Storage::disk(User::avatarDisk());
    expect($disk->get(User::avatarFile($user->id, 'md')))->toBe('old-md')
        ->and($disk->get(User::avatarFile($user->id, 'sm')))->toBe('old-sm')
        ->and($user->fresh()?->avatar_path)->toBe(User::avatarFile($user->id, 'md'));
}

it('rejects guests', function () {
    $this->postJson(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('a.png')])->assertUnauthorized();
    $this->deleteJson(route('settings.avatar.destroy'))->assertUnauthorized();
});

it('uploads a valid png and creates every configured size', function () {
    $this->actingAs($this->user)
        ->from(route('settings.profile'))
        ->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('a.png', 600, 400)])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.profile'));

    expect($this->user->fresh()?->avatar_path)->toBe(User::avatarFile($this->user->id, 'md'));
    foreach (array_keys((array) config('core.avatars.sizes')) as $name) {
        Storage::disk(User::avatarDisk())->assertExists(User::avatarFile($this->user->id, (string) $name));
    }
});

it('rejects a 5 MB file and keeps the old avatar', function () {
    seedAvatar($this->user);

    $this->actingAs($this->user)
        ->from(route('settings.profile'))
        ->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('big.png', 100, 100)->size(5 * 1024)])
        ->assertSessionHasErrors('avatar')
        ->assertRedirect(route('settings.profile'));

    assertOldAvatarIntact($this->user);
});

it('returns 422 json for an oversized file', function () {
    $this->actingAs($this->user)
        ->postJson(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('big.png', 100, 100)->size(5 * 1024)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('avatar');
});

it('rejects svg and keeps the old avatar', function () {
    seedAvatar($this->user);
    $svg = UploadedFile::fake()->createWithContent('a.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>');

    $this->actingAs($this->user)->from(route('settings.profile'))
        ->post(route('settings.avatar.store'), ['avatar' => $svg])
        ->assertSessionHasErrors('avatar');

    assertOldAvatarIntact($this->user);
});

it('rejects an image wider than 4000 px and keeps the old avatar', function () {
    seedAvatar($this->user);

    $this->actingAs($this->user)->from(route('settings.profile'))
        ->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('wide.png', 4001, 100)])
        ->assertSessionHasErrors('avatar');

    assertOldAvatarIntact($this->user);
});

it('removes the avatar', function () {
    seedAvatar($this->user);

    $this->actingAs($this->user)->from(route('settings.profile'))
        ->delete(route('settings.avatar.destroy'))
        ->assertRedirect(route('settings.profile'));

    expect(Storage::disk(User::avatarDisk())->files(User::avatarDirectory($this->user->id)))->toBeEmpty()
        ->and($this->user->fresh()?->avatar_path)->toBeNull();
});

it('lets a readonly-banned user change the avatar', function () {
    Ban::create(['user_id' => $this->user->id, 'banned_by_id' => User::factory()->create()->id, 'type' => BanType::Readonly, 'reason_code' => 'spam', 'starts_at' => now()->subMinute()]);

    $this->actingAs($this->user)->from(route('settings.profile'))
        ->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('a.png', 300, 300)])
        ->assertSessionHasNoErrors();

    expect($this->user->fresh()?->avatar_path)->not->toBeNull();
});

it('returns a dedicated error when the request has no avatar file', function () {
    seedAvatar($this->user);

    $this->actingAs($this->user)->from(route('settings.profile'))
        ->post(route('settings.avatar.store'), [])
        ->assertSessionHasErrors(['avatar' => __('core::public.settings.avatar.missing_or_too_large')]);

    assertOldAvatarIntact($this->user);
});
