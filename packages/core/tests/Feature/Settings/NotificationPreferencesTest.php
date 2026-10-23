<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Models\User;
use Ladcms\Core\Notifications\UserMentioned;

uses(RefreshDatabase::class);

it('renders the matrix with defaults and the locked bans.database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.notifications'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('core::settings/notifications')
        ->where('preferences.mentions', ['database' => true, 'mail' => false])
        ->where('preferences.bans', ['database' => true, 'mail' => true])
        ->where('locked', ['bans.database'])
        ->where('types', ['mentions', 'complaints', 'bans']));
});

it('saves the matrix and the preferences drive via()', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('settings.notifications.update'), [
        'preferences' => [
            'mentions' => ['database' => '1', 'mail' => '1'],
            'complaints' => ['database' => '0', 'mail' => '0'],
            'bans' => ['database' => '0', 'mail' => '0'],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect(route('settings.notifications'));

    $fresh = $user->fresh();
    expect($fresh->notification_preferences)->toBe([
        'mentions' => ['database' => true, 'mail' => true],
        'complaints' => ['database' => false, 'mail' => false],
        'bans' => ['database' => true, 'mail' => false],
    ])->and(new UserMentioned($fresh, $fresh, '/')->via($fresh))->toBe(['database', 'mail']);
});

it('rejects unknown notification types', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('settings.notifications.update'), ['preferences' => ['spam' => ['database' => true, 'mail' => true]]])
        ->assertSessionHasErrors('preferences');
});

it('requires auth', function () {
    $this->getJson(route('settings.notifications'))->assertUnauthorized();
    $this->patchJson(route('settings.notifications.update'), [])->assertUnauthorized();
});
