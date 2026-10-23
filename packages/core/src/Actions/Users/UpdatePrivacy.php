<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Enums\MessagesFrom;
use Ladcms\Core\Models\User;

class UpdatePrivacy
{
    /**
     * @param  array{show_email: bool, show_birthday: bool, messages_from: string}  $data
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $data): User
    {
        Validator::make($data, [
            'messages_from' => ['required', Rule::enum(MessagesFrom::class)],
        ])->validate();

        $user->forceFill([
            'show_email' => $data['show_email'],
            'show_birthday' => $data['show_birthday'],
            'messages_from' => MessagesFrom::from($data['messages_from']),
        ])->save();

        return $user;
    }
}
