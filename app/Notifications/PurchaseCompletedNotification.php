<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PurchaseCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : 'en';

        return (new MailMessage)
            ->subject($locale === 'ar' ? 'تم شراء eSIM بنجاح' : 'Your eSIM purchase is complete')
            ->line($locale === 'ar'
                ? 'طلبك '.$this->order->order_number.' اكتمل وهو جاهز للتثبيت.'
                : 'Order '.$this->order->order_number.' is complete and ready to install.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'purchase_completed',
            'order_uuid' => $this->order->uuid,
            'order_number' => $this->order->order_number,
        ];
    }
}
