<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Fixtures\VerifiableUser;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Ladcms\Core\Models\User;

/** Наследник core User с контрактом MustVerifyEmail — как App\Models\User в приложении. */
class VerifiableUser extends User implements MustVerifyEmail
{
    protected $table = 'users';
}
