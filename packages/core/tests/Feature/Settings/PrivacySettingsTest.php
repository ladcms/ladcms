<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Enums\MessagesFrom;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

it('renders the privacy page with the current values', function () {
    $user = User::factory()->create(['show_email' => true, 'show_birthday' => false, 'messages_from' => 'friends']);

    $this->actingAs($user)->get(route('settings.privacy'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('core::settings/privacy')
        ->where('privacy', ['show_email' => true, 'show_birthday' => false, 'messages_from' => 'friends'])
        ->where('messages_from_options', ['all', 'friends', 'none']));
});

it('saves the privacy settings', function () {
    $user = User::factory()->create(['show_email' => false, 'show_birthday' => true, 'messages_from' => 'all']);

    $this->actingAs($user)
        ->patch(route('settings.privacy.update'), ['show_email' => '1', 'show_birthday' => '0', 'messages_from' => 'none'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.privacy'));

    $fresh = $user->fresh();
    expect($fresh->show_email)->toBeTrue()->and($fresh->show_birthday)->toBeFalse()->and($fresh->messages_from)->toBe(MessagesFrom::None);
});

it('rejects an invalid messages_from', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('settings.privacy.update'), ['show_email' => true, 'show_birthday' => true, 'messages_from' => 'everyone'])
        ->assertSessionHasErrors('messages_from');
});

it('is allowed for a readonly-banned user', function () {
    $user = User::factory()->create();
    Ban::create(['user_id' => $user->id, 'banned_by_id' => User::factory()->create()->id, 'type' => BanType::Readonly, 'reason_code' => 'spam', 'starts_at' => now()->subMinute()]);

    $this->actingAs($user)->patch(route('settings.privacy.update'), ['show_email' => true, 'show_birthday' => true, 'messages_from' => 'none'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->show_email)->toBeTrue();
});

it('requires auth', function () {
    $this->getJson(route('settings.privacy'))->assertUnauthorized();
    $this->patchJson(route('settings.privacy.update'), [])->assertUnauthorized();
});
