<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentReRoutedNotification extends Notification
{
    use Queueable;

    public $folder;
    public $newSignatoryName;

    /**
     * Create a new notification instance.
     */
    public function __construct($folder, string $newSignatoryName)
    {
        $this->folder = $folder;
        $this->newSignatoryName = $newSignatoryName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'folder_id'     => $this->folder->id,
            'pr_number'     => $this->folder->pr_number ?: $this->folder->tracking_number,
            'title'         => 'PR Tracking Update',
            'new_signatory' => $this->newSignatoryName,
            'message'       => "Your PR <strong>(" . ($this->folder->pr_number ?: $this->folder->tracking_number) . ")</strong> has been automatically re-routed to <strong>{$this->newSignatoryName}</strong> due to a change in the active office signatory matrix.",
        ];
    }
}
