<?php

namespace App\Services;

use Exception;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Queue;
use App\Helpers\FlashMsg;

class SMSService
{
    public function send_sms($number, $message)
    {
        // Dispatch a closure to the queue for SMS sending
        dispatch(function () use ($number, $message) {
            try {
                $account_sid = getenv("TWILIO_SID");
                $auth_token = getenv("TWILIO_TOKEN");
                $twilio_number = getenv("TWILIO_FROM");

                $client = new Client($account_sid, $auth_token);
                $client->messages->create($number, [
                    'from' => $twilio_number,
                    'body' => $message
                ]);
            } catch (Exception $e) {
                $errorResponse = $this->handleTwilioError($e->getCode());
                return redirect()->back()->with(FlashMsg::item_delete(__($errorResponse['message'])));
            }
        });

        return redirect()->back();
    }

    private function handleTwilioError($errorCode)
{
    $errorResponses = [
        30001 => ['status' => 429, 'message' => __("Queue overflow. Please try again later.")],
        30002 => ['status' => 403, 'message' => __("Your account is suspended. Please contact support.")],
        30003 => ['status' => 400, 'message' => __("Unreachable destination handset. Please check the number.")],
        30004 => ['status' => 403, 'message' => __("Message blocked. The recipient cannot receive messages.")],
        30005 => ['status' => 400, 'message' => __("Unknown destination handset. Please check the number.")],
        30006 => ['status' => 400, 'message' => __("The destination number is a landline or unreachable.")],
        30007 => ['status' => 403, 'message' => __("Carrier violation. The message content was flagged.")],
        30008 => ['status' => 500, 'message' => __("Unknown error. Please try again later.")],
        30009 => ['status' => 400, 'message' => __("Missing segment. One or more segments of your message were not received.")],
        30010 => ['status' => 402, 'message' => __("Message price exceeds max price. The price of your message exceeds the allowed maximum.")],
        63001 => ['status' => 401, 'message' => __("Channel authentication failed. Check your credentials.")],
        63002 => ['status' => 404, 'message' => __("Channel could not find From address. Verify the channel endpoint address.")],
        63003 => ['status' => 404, 'message' => __("Channel could not find To address. The destination address is incorrect.")],
        63005 => ['status' => 400, 'message' => __("Channel did not accept the given content. Please check the content format.")],
        63006 => ['status' => 400, 'message' => __("Could not format the given content for the channel.")],
        63007 => ['status' => 404, 'message' => __("Twilio could not find a Channel with the specified From address.")],
        63008 => ['status' => 500, 'message' => __("Could not execute the request due to misconfigured channel module. Please check your configuration.")],
        63009 => ['status' => 500, 'message' => __("Channel returned an error during execution. See the specific error for more information.")],
        63010 => ['status' => 500, 'message' => __("Channels - Twilio Internal error.")],
        63012 => ['status' => 500, 'message' => __("Channel returned an internal error that prevented request completion.")],
        63013 => ['status' => 403, 'message' => __("Message send failed due to violation of Channel provider's policy.")],
        63014 => ['status' => 403, 'message' => __("Message delivery failed because it was blocked by a user action.")],

        // Common Twilio error codes
        20001 => ['status' => 400, 'message' => __("The 'To' number is not a valid phone number.")],
        20002 => ['status' => 400, 'message' => __("The 'From' number is not a valid phone number.")],
        20003 => ['status' => 400, 'message' => __("The 'To' number is not reachable.")],
        20004 => ['status' => 400, 'message' => __("The 'To' number is not SMS-capable.")],
        21211 => ['status' => 400, 'message' => __("The phone number provided is not a valid phone number.")],
        21601 => ['status' => 400, 'message' => __("The message could not be delivered to the recipient.")],
        21602 => ['status' => 400, 'message' => __("The message failed because the recipient was unable to receive it.")],
        21603 => ['status' => 400, 'message' => __("The message was rejected by the recipient's carrier.")],
        21604 => ['status' => 400, 'message' => __("The message was rejected by the recipient's phone.")],
        21605 => ['status' => 400, 'message' => __("The message was not delivered due to a network issue.")],

        // Add any additional Twilio error codes and custom responses here
        'default' => ['status' => 500, 'message' => __("An error occurred. Please try again later.")]
    ];

    return $errorResponses[$errorCode] ?? $errorResponses['default'];
}
}
