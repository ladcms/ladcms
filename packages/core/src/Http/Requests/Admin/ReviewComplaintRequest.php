<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ladcms\Core\Enums\ComplaintStatus;

/** Решение по жалобе: `pending` недопустим. Авторизацию и повторное рассмотрение проверяет `ReviewComplaint`. */
class ReviewComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ComplaintStatus::class)->except(ComplaintStatus::Pending)],
        ];
    }

    public function decision(): ComplaintStatus
    {
        return ComplaintStatus::from((string) $this->validated('status'));
    }
}
