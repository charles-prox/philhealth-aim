<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentReturnedNotification extends Notification
{
    use Queueable;

    public $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
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
            'tracking_number' => $this->data['tracking_number'],
            'type' => $this->data['type'],
            'remarks' => $this->data['remarks'],
            'officer_name' => $this->data['officer_name'],
            'title' => 'Document Returned',
            'message' => 'Your document <strong>(' . $this->data['tracking_number'] . ')</strong> has been returned for <strong>' . str_replace('_', ' ', $this->data['type']) . '</strong> by <strong>' . $this->data['officer_name'] . '</strong>.',
        ];
    }
}
