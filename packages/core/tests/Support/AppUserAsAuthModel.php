<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Support;

use Ladcms\Core\Tests\Fixtures\AppUser\AppUser;

/**
 * Модель пользователя — наследник core User (как App\Models\User в приложении), задаётся до boot,
 * чтобы morphMap и UserModel ядра видели её. Трейт, а не подкласс TestCase (см. ReversedProviderOrder).
 */
trait AppUserAsAuthModel
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', AppUser::class);
    }
}
