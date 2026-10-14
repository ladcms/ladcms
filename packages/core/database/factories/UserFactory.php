<?php

declare(strict_types=1);

namespace Ladcms\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ladcms\Core\Models\User;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** @var array<string, true> */
    private static array $issuedLogins = [];

    public function definition(): array
    {
        return [
            'login' => $this->login(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'remember_token' => str()->random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    /** Уникальный логин из [a-z0-9_], 3–32 символа (санитизация может склеить разные userName — проверяем). */
    private function login(): string
    {
        do {
            $login = (string) preg_replace('/[^a-z0-9_]/', '_', strtolower($this->faker->unique()->userName()));

            if (strlen($login) < 3) {
                $login .= '_'.strtolower(str()->random(3));
            }

            $login = substr($login, 0, 32);
        } while (isset(self::$issuedLogins[$login]));

        self::$issuedLogins[$login] = true;

        return $login;
    }
}
