<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Channels\FirebasePushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderNotification extends Notification
{
    use Queueable;

    public $order_id;
    public $service_id;
    public $seller_id;
    public $buyer_id;
    public $order_message;
    public $notification_type;
    public $status;
    public $target_role;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        $order_id,
        $service_id,
        $seller_id,
        $buyer_id,
        $order_message,
        string $notification_type = 'order_alert',
        $status = null,
        ?string $target_role = null
    ) {
        $this->order_id = $order_id;
        $this->service_id = $service_id;
        $this->seller_id = $seller_id;
        $this->buyer_id = $buyer_id;
        $this->order_message = $order_message;
        $this->notification_type = $notification_type;
        $this->status = $status;
        $this->target_role = $target_role;
    }

    /**
     * Delivery channels: durable database notification + best-effort Firebase push.
     */
    public function via($notifiable): array
    {
        return ['database', FirebasePushChannel::class];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->line($this->order_message)
            ->action('View Order', url('/'))
            ->line('Thank you for using FUN MOMENT!');
    }

    /**
     * Structured data for database persistence and client retrieval.
     */
    public function toArray($notifiable): array
    {
        $role = $this->target_role;
        if (!$role && $notifiable) {
            $role = ($notifiable->id == $this->seller_id) ? 'seller' : 'buyer';
        }

        return [
            'order_id' => (int) $this->order_id,
            'service_id' => (int) $this->service_id,
            'seller_id' => (int) $this->seller_id,
            'buyer_id' => (int) $this->buyer_id,
            'order_message' => (string) $this->order_message,
            'notification_type' => (string) $this->notification_type,
            'status' => $this->status,
            'target_role' => $role,
        ];
    }

    /**
     * Structured payload specifically for push notification delivery.
     */
    public function toFirebase($notifiable): array
    {
        $data = $this->toArray($notifiable);

        $titles = [
            'new_booking' => __('New Booking Alert!'),
            'booking_accepted' => __('Booking Confirmed!'),
            'booking_cancelled' => __('Booking Cancelled'),
            'booking_completed' => __('Booking Completed'),
            'payment_confirmed' => __('Payment Confirmed'),
            'order_alert' => __('Booking Update'),
        ];

        $data['title'] = $titles[$this->notification_type] ?? __('Booking Update');
        $data['body'] = (string) $this->order_message;

        return $data;
    }
}
