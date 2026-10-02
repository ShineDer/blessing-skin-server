<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SiteMessage extends Notification
{
    use Queueable;

    public $title;

    public $content;

    public $metadata;

    public function __construct(string $title, $content = '', array $metadata = [])
    {
        $this->title = $title;
        $this->content = $content;
        $this->metadata = $metadata;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            ...$this->metadata,
        ];
    }
}
