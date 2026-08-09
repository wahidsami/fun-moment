<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Intervention\Image\Facades\Image;
use Modules\LiveChat\Entities\LiveChatMessage;

class BuyerChatController extends Controller
{
    public function liveChat()
    {
        $buyer_id = auth('sanctum')->id();
        $seller_lists = LiveChatMessage::select('seller_id')
            ->with('sellerList')
            ->distinct('seller_id')
            ->where('seller_id', '!=', null)
            ->where('buyer_id', $buyer_id)
            ->get();

        $seller_lists->map(function ($seller) {
            if (!empty($seller->sellerList->image)) {
                // Get image data
                $seller_image = get_attachment_image_by_id($seller->sellerList->image);
                if (isset($seller_image['img_url'])) {
                    $seller->seller_image = [
                        'image_url' => $seller_image['img_url'] ?? null,
                    ];
                } else {
                    $seller->seller_image = [
                        'image_url' => null,
                    ];
                }
            }

            return $seller;
        });

        if ($seller_lists->isNotEmpty()) {
            return response()->success([
                'chat_seller_lists' => $seller_lists,
            ]);
        } else {
            return response()->error('No Contacts Yet');
        }

    }

    public function postSendMessage(Request $request)
    {

        if(!$request->to_user || !$request->message) {
            return;
        }

        $message = new LiveChatMessage();

        $message->from_user = auth('sanctum')->user()->id;
        $message->to_user = $request->to_user;

        if($request->message != '' && $request->message != null && $request->message != 'null')  {
            $message->message = strip_tags($request->message);

            if($request->hasFile("image")) {
                $filename = $this->uploadImage($request);
                if(!empty($filename)) {
                    $message->image = $filename;
                }
            }
        }

        if (auth('sanctum')->user()->user_type == 0){
            $message->buyer_id = $request->to_user;
            $message->seller_id = auth('sanctum')->user()->id;
        }else{
            $message->buyer_id = auth('sanctum')->user()->id;
            $message->seller_id = $request->to_user;
        }

        $message->save();

        $profile_image =  render_image_markup_by_attachment_id(optional($message->fromUser)->image);
        // prepare the message object along with the relations to send with the response
        $message = LiveChatMessage::with(['fromUser', 'toUser'])->find($message->id);

        // fire the event
        event(new MessageSent($message));

        $all_array = $message->toArray() + ['profile_image'=>$profile_image];

        return response()->json(['state' => 1, 'message' => $all_array]);
    }

    public function allMessages(Request $request)
    {
        if (!$request->to_user) {
            return;
        }

        $messages = LiveChatMessage::where(function($query) use ($request) {
            $query->where('from_user', auth("sanctum")->user()->id)->where('to_user', $request->to_user);
        })->orWhere(function ($query) use ($request) {
            $query->where('from_user', $request->to_user)->where('to_user', auth("sanctum")->user()->id);
        })->orderBy('created_at', 'DESC')->paginate(16)->withQueryString();

        return response()->json([
            'messages' => $messages
        ]);

    }

    public function getPusherCredentials()
    {
        $pusher_app_id = get_static_option('pusher_app_id') ?? '';
        $pusher_app_key = get_static_option('pusher_app_key') ?? '';
        $pusher_app_secret = get_static_option('pusher_app_secret') ?? '';
        $pusher_app_cluster = get_static_option('pusher_app_cluster') ?? '';

        $user_info = auth('sanctum')->user();
        $user_type =  $user_info->user_type ===  1 ? 'seller_' : '';
        $seller_instanace = $user_info->user_type === 0 ? 'seller_' : '';
        $pusher_app_push_notification_auth_token =  get_static_option($user_type.'pusher_app_push_notification_auth_token');
        $pusher_app_push_notification_instanceId_seller =  get_static_option( $user_type.'pusher_app_push_notification_instanceId');
        $pusher_app_push_notification_instanceId =  get_static_option( $seller_instanace.'pusher_app_push_notification_instanceId');

        $auth_url = 'https://'.$pusher_app_push_notification_instanceId_seller.'.pushnotifications.pusher.com/publish_api/v1/instances/'.$pusher_app_push_notification_instanceId_seller.'/publishes';

        return response()->success([
            'pusher_app_id'=>$pusher_app_id,
            'pusher_app_key'=>$pusher_app_key,
            'pusher_app_secret'=>$pusher_app_secret,
            'pusher_app_cluster'=>$pusher_app_cluster,
            'pusher_app_push_notification_auth_token'=> $pusher_app_push_notification_auth_token,
            'pusher_app_push_notification_auth_url'=> $auth_url, //build by me
            'pusher_app_push_notification_instanceId'=> $pusher_app_push_notification_instanceId
        ]);
    }

    private function uploadImage($request)
    {
        $file = $request->file('image');
        $filename = md5(uniqid()) . "." . $file->getClientOriginalExtension();

        // file scan start
        $uploaded_file = $file;
        $file_extension = $uploaded_file->getClientOriginalExtension();
        if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $processed_image = Image::make($uploaded_file);
            $image_default_width = $processed_image->width();
            $image_default_height = $processed_image->height();

            $processed_image->resize($image_default_width, $image_default_height, function ($constraint) {
                $constraint->aspectRatio();
            });
            $processed_image->save('assets/uploads/chat_image/' . $filename);
        }else{
            $file->move('assets/uploads/chat_image', $filename);
        } // file scan end

        return $filename;
    }

}
