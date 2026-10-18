<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Fixtures\AppUser;

use Ladcms\Core\Database\Factories\UserFactory;

class AppUserFactory extends UserFactory
{
    protected $model = AppUser::class;
}
