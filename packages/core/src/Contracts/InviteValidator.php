<?php

declare(strict_types=1);

namespace Ladcms\Core\Contracts;

use Ladcms\Core\Models\User;

/**
 * Проверка инвайтов при `registration.invite_only`. Реализует модуль (например, `community`) и биндит в контейнер;
 * без биндинга регистрация по инвайтам закрыта.
 */
interface InviteValidator
{
    /** Действителен ли код для регистрации с этим email. */
    public function validate(string $code, string $email): bool;

    /** Помечает код использованным зарегистрированным пользователем (в транзакции регистрации). */
    public function consume(string $code, User $user): void;
}
