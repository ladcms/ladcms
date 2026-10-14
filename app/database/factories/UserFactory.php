<?php

namespace Database\Factories;

use App\Models\User;
use Ladcms\Core\Database\Factories\UserFactory as CoreUserFactory;

/**
 * Фабрика приложения: поля домена (в т.ч. уникальный `login`) — из фабрики ядра, сверху — 2FA kit-а.
 */
class UserFactory extends CoreUserFactory
{
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ...parent::definition(),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
