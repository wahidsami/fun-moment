<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BasicMail extends Mailable
{
    use Queueable, SerializesModels;
    public $data;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($args)
    {
        $this->data = $args;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $envFromAddress = env('MAIL_FROM_ADDRESS');
        $configEmail = (!empty($envFromAddress) && $envFromAddress !== 'null') ? $envFromAddress : config('mail.from.address');
        $siteEmail = get_static_option('site_global_email');
        $fromEmail = (!empty($configEmail) && $configEmail !== 'null' && $configEmail !== 'hello@example.com')
            ? $configEmail
            : (!empty($siteEmail) ? $siteEmail : 'no-reply@funmoments.sa');

        $envFromName = env('MAIL_FROM_NAME');
        $configName = (!empty($envFromName) && $envFromName !== 'null') ? $envFromName : config('mail.from.name');
        $siteTitle = get_static_option('site_title');
        $fromName = (!empty($configName) && $configName !== 'null' && $configName !== 'Example')
            ? $configName
            : (!empty($siteTitle) ? $siteTitle : config('app.name', 'Fun Moments'));

        return $this->from($fromEmail, $fromName)
            ->subject($this->data['subject'])
            ->markdown('mail.basic-mail-template');
    }
}
