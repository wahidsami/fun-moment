<?php

namespace App\Console\Commands;

use App\Helpers\FlashMsg;
use App\Language;
use App\Mail\BasicMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class SubscriptionExpireReminder extends Command
{
    protected $signature = 'package:subscription_expire';
    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {

        $defaultLang =  Language::where('default',1)->first();
        if (session()->has('lang')) {
            $current_lang = Language::where('slug',session()->get('lang'))->first();
            if (!empty($current_lang)){
                \Carbon\Carbon::setLocale($current_lang->slug);
                app()->setLocale($current_lang->slug);
            }else {
                session()->forget('lang');
            }
        }else{
            Carbon::setLocale($defaultLang->slug);
            app()->setLocale($defaultLang->slug);
        }

        $all_user = \App\User::with('subscribedSeller')
            ->select('id','name','email')
            ->where('user_type',0)
            ->whereHas('subscribedSeller')
            ->get();

        // if seller subscribed
        foreach ($all_user as $user) {
            $dayList = json_decode(get_static_option('package_expire_notify_mail_days')) ?? [];
            rsort($dayList);

            $expireDate = optional($user->subscribedSeller)->expire_date;
            if (!$expireDate) {
                continue; // Skip users without an expiration date
            }

            $startDate = Carbon::today();
            foreach ($dayList as $day) {
                $notificationDate = Carbon::parse($expireDate)->subDays($day);
                if ($startDate->diffInDays($notificationDate, false) >= 0) {
                    // Check if it's time to send a notification
                    $daysRemaining = $startDate->diffInDays($expireDate);
                    // if Email Notify day & seller Subscription remaining day same then send email
                    if ($day == $daysRemaining) {
                        try {
                            $subject = __('Subscription Expire Reminder');
                            $messageBody = __('Your Subscription will expire very soon. Only') . ' ' . $daysRemaining . ' ' . __('Days Left. Please subscribe to a plan before expiration');
                            Mail::to($user->email)->send(new BasicMail([
                                'subject' => $subject,
                                'message' => $messageBody
                            ]));
                        } catch (\Exception $e) {
                        }
                    }
                }
            }
        }

        return 0;
    }
}
