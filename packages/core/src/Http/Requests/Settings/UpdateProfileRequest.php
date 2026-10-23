<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Settings;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ladcms\Core\Actions\Users\UpdateOwnProfile;
use Ladcms\Core\Enums\Sex;

/** Собственный профиль; уникальность email и права на публичный контент проверяет `UpdateOwnProfile`. */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'sex' => ['sometimes', 'nullable', Rule::enum(Sex::class)],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before:tomorrow'],
            'about' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'signature' => ['sometimes', 'nullable', 'string', 'max:500'],
            'timezone' => ['sometimes', 'required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ];
    }

    /**
     * Только переданные поля.
     *
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        $validated = $this->validated();

        return array_intersect_key($validated, array_flip(UpdateOwnProfile::FIELDS));
    }
}
