<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Users\UpdatePrivacy;
use Ladcms\Core\Enums\MessagesFrom;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

it('stores the privacy flags and messages_from', function () {
    $user = User::factory()->create(['show_email' => false, 'show_birthday' => true, 'messages_from' => 'all']);

    app(UpdatePrivacy::class)->handle($user, ['show_email' => true, 'show_birthday' => false, 'messages_from' => 'friends']);

    $fresh = $user->fresh();
    expect($fresh->show_email)->toBeTrue()
        ->and($fresh->show_birthday)->toBeFalse()
        ->and($fresh->messages_from)->toBe(MessagesFrom::Friends);
});

it('rejects an unknown messages_from value', function () {
    $user = User::factory()->create();

    expect(fn () => app(UpdatePrivacy::class)->handle($user, ['show_email' => true, 'show_birthday' => true, 'messages_from' => 'nobody']))
        ->toThrow(ValidationException::class);
});
