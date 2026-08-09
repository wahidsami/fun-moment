<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $subject, public $order_details, public string $messageBody = "")
    {
    }

    public function build()
    {
        $subject = $this->subject;
        $order_details = $this->order_details;

        $orderMessageBody = view("mail.order-mail-body.new-order-mail",compact('order_details'))->render();

        $message_body = str_replace("@generatedOrderDetails",$orderMessageBody, $this->messageBody);

        return $this->from(get_static_option('site_global_email'), get_static_option('site_title'))
            ->subject($subject)
            ->markdown('mail.order-mail-template',compact('order_details','message_body'));
    }
}
