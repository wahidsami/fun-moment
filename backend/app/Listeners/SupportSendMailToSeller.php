<?php

namespace App\Listeners;

use App\Events\SupportMessage;
use App\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Mail\SupportMail;
use App\SupportTicket;
use Illuminate\Support\Facades\Mail;
use Modules\JobPost\Entities\JobRequest;

class SupportSendMailToSeller
{

    public function __construct()
    {
        //
    }

    public function handle(SupportMessage $event)
    {

        $ticket_info = $event->message;

        // job mail
        if (!empty($ticket_info->job_request_id)){
            if ($ticket_info->notify === 'on' && $ticket_info->type === 'buyer'){
                $subject = __('your have a new message in Job Request ID').' #'.$ticket_info->job_request_id;
                $job_request_details = JobRequest::find($ticket_info->job_request_id);
                if ($ticket_info->type === 'buyer'){
                    $user_email = optional($job_request_details->seller)->email ?? '';
                }elseif($ticket_info->type === 'seller'){
                    $buyer_info = User::select('id', 'email')->findOrFail($job_request_details->buyer_id);
                    $user_email = $buyer_info->email ?? '';
                }

                $message = '<p>'.__('Hello').'<br>';
                $message .= __('you have a new message in Job ID').' #'.$job_request_details->job_post_id.'. ';
                $message .= '<div><a href="'.route('seller.job.request.conversation',$ticket_info->job_request_id).'">'.__('check messages').'</a></div>';
                $message .= '</p>';

                if (!empty($user_email)){
                    try {
                        Mail::to($user_email)->send(new SupportMail([
                            'message' => $message,
                            'subject' => $subject
                        ]));
                    }catch (\Exception $e){
                        //show error message
                    }
                }

            }
        }else{
            if ($ticket_info->notify === 'on' && $ticket_info->type === 'buyer'){
                $subject = __('your have a new message in ticket').' #'.$ticket_info->support_ticket_id;
                $ticket_details = SupportTicket::find($ticket_info->support_ticket_id);
                $user_email = optional($ticket_details->ticket_seller)->email ?? '';
                $message = '<p>'.__('Hello').'<br>';
                $message .= __('you have a new message in ticket no').' #'.$ticket_info->support_ticket_id.'. ';
                $message .= '<div><a href="'.route('seller.support.ticket.view',$ticket_info->support_ticket_id).'">'.__('check messages').'</a></div>';
                $message .= '</p>';

                if (!empty($user_email)){
                    try {
                        Mail::to($user_email)->send(new SupportMail([
                            'message' => $message,
                            'subject' => $subject
                        ]));
                    }catch (\Exception $e){
                        //show eerror message
                    }
                }
            }
        }

    }

}
