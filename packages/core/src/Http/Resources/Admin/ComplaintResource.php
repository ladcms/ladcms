<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ladcms\Core\Models\Complaint;

/** @mixin Complaint */
class ComplaintResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Complaint $complaint */
        $complaint = $this->resource;

        return [
            'id' => $complaint->id,
            'author' => $complaint->author === null ? null : ['id' => $complaint->author->id, 'login' => $complaint->author->login],
            'target_user' => $complaint->targetUser === null ? null : ['id' => $complaint->targetUser->id, 'login' => $complaint->targetUser->login],
            'reportable' => $complaint->reportable_type === null ? null : [
                'type' => class_basename($complaint->reportable_type),
                'id' => $complaint->reportable_id,
            ],
            'reason_code' => $complaint->reason_code,
            'comment' => $complaint->comment,
            'status' => $complaint->status->value,
            'reviewer' => $complaint->reviewer === null ? null : ['id' => $complaint->reviewer->id, 'login' => $complaint->reviewer->login],
            'reviewed_at' => $complaint->reviewed_at?->toIso8601String(),
            'created_at' => $complaint->created_at?->toIso8601String(),
        ];
    }
}
