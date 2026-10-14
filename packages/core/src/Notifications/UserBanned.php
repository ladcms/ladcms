<?php

declare(strict_types=1);

namespace Ladcms\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Ladcms\Core\Models\Ban;

class UserBanned extends Notification
{
    public function __construct(public readonly Ban $ban) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array{ban_id: int, reason_code: string, expires_at: ?string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'ban_id' => $this->ban->id,
            'reason_code' => $this->ban->reason_code,
            'expires_at' => $this->ban->expires_at?->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your account has been restricted')
            ->line("Reason: {$this->ban->reason_code}");

        if ($this->ban->comment !== null && $this->ban->comment !== '') {
            $message->line("Comment: {$this->ban->comment}");
        }

        return $message->line($this->ban->expires_at === null
            ? 'The restriction is permanent.'
            : 'The restriction expires at '.$this->ban->expires_at->toDayDateTimeString().' (UTC).');
    }
}
