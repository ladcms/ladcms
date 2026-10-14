<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Moderation;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Enums\ComplaintStatus;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Models\User;
use Ladcms\Core\Notifications\ComplaintFiled;
use Ladcms\Core\Settings\ModerationSettings;

class FileComplaint
{
    public function __construct(private readonly ModerationSettings $settings) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $author, string $reasonCode, string $comment, ?Model $reportable = null, ?User $targetUser = null): Complaint
    {
        if (! $author->canWrite()) {
            throw new AuthorizationException('You cannot file complaints.');
        }

        $codes = collect($this->settings->violation_codes)->pluck('code')->all();
        Validator::make(
            ['reason_code' => $reasonCode, 'comment' => $comment],
            [
                'reason_code' => ['required', Rule::in($codes)],
                'comment' => ['required', 'string', 'max:2000'],
            ],
        )->validate();

        $duplicate = Complaint::query()
            ->where('user_id', $author->id)
            ->pending()
            ->when(
                $reportable instanceof Model,
                fn ($q) => $q->where('reportable_type', $reportable?->getMorphClass())->where('reportable_id', $reportable?->getKey()),
                fn ($q) => $q->whereNull('reportable_type')->where('target_user_id', $targetUser?->id),
            )
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['complaint' => 'You already have a pending complaint about this.']);
        }

        $complaint = Complaint::create([
            'user_id' => $author->id,
            'target_user_id' => $targetUser?->id,
            'reportable_type' => $reportable?->getMorphClass(),
            'reportable_id' => $reportable?->getKey(),
            'reason_code' => $reasonCode,
            'comment' => $comment,
            'status' => ComplaintStatus::Pending,
        ]);
        $complaint->setRelation('author', $author);

        Notification::send(User::permission('complaints.review')->get(), new ComplaintFiled($complaint));

        return $complaint;
    }
}
