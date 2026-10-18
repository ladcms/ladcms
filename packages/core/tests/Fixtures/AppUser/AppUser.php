<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Fixtures\AppUser;

use Ladcms\Core\Models\User;

/** Наследник core User, как App\Models\User в приложении (модель `auth.providers.users.model`). */
class AppUser extends User
{
    protected $table = 'users';

    protected static function newFactory(): AppUserFactory
    {
        return AppUserFactory::new();
    }
}
