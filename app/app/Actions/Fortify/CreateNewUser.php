<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Ladcms\Core\Actions\Auth\RegisterUser;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user (rules and registration settings live in the core action).
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $user = app(RegisterUser::class)->handle($input, request()->ip());

        assert($user instanceof User);

        return $user;
    }
}
