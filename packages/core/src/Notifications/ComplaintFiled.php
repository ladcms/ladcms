<?php

declare(strict_types=1);

namespace Ladcms\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Notifications\Concerns\RespectsPreferences;

class ComplaintFiled extends Notification
{
    use RespectsPreferences;

    public const string TYPE = 'complaints';

    public function __construct(public readonly Complaint $complaint) {}

    /** @return array{complaint_id: int, reason_code: string, author_login: ?string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'complaint_id' => $this->complaint->id,
            'reason_code' => $this->complaint->reason_code,
            'author_login' => $this->complaint->author?->login,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New complaint')
            ->line("A new complaint was filed. Reason: {$this->complaint->reason_code}");
    }
}
