<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Support;

use Ladcms\Core\CoreServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Tests\Fixtures\FriendModule\FriendServiceProvider;
use Ladcms\Core\Tests\Fixtures\NeedsFriendModule\NeedsFriendServiceProvider;

/**
 * Core регистрируется последним, зависимость — после зависимого модуля (как при package discovery).
 *
 * Трейт, а не подкласс TestCase: tests/Pest.php уже привязывает каталог к Ladcms\Tests\TestCase,
 * и Pest запрещает второй TestCase-класс в файле (TestCaseAlreadyInUse).
 */
trait ReversedProviderOrder
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            NeedsFriendServiceProvider::class,
            FriendServiceProvider::class,
            DummyServiceProvider::class,
            CoreServiceProvider::class,
        ];
    }
}
