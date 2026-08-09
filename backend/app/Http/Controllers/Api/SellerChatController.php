<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Intervention\Image\Facades\Image;
use Modules\LiveChat\Entities\LiveChatMessage;

class SellerChatController extends Controller
{
    public function liveChat()
    {
        $seller_id = auth('sanctum')->id();
        $buyer_lists = LiveChatMessage::select('buyer_id')
            ->with('buyerList')
            ->whereHas('buyerList')
            ->distinct('buyer_id')
            ->where('buyer_id','!=',NULL)
            ->where('seller_id', $seller_id)
            ->get();   

        $buyer_image=[];
        foreach($buyer_lists as $buyer){
              $buyer_image[]= get_attachment_image_by_id(optional($buyer->buyerList)->image);
        }
        if($buyer_lists){
            return response()->success([
                'chat_buyer_lists'=>$buyer_lists,
                'buyer_image'=>$buyer_image,
            ]);
        }
        return response()->error([
            'chat_buyer_lists'=>__('No Contacts Yet'),
        ]);
    }



    public function postSendMessage(Request $request)
    {
        if(!$request->to_user || !$request->message) {
            return;
        }

        $message = new LiveChatMessage();

        $seller_id = auth('sanctum')->id();
        $message->from_user = $seller_id;
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

        $message->seller_id = $seller_id;
        $message->buyer_id = $request->to_user;
        $message->save();

        $send_image_url =  $message->image;
        $profile_image =  render_image_markup_by_attachment_id(optional($message->fromUser)->image);

        // prepare the message object along with the relations to send with the response
        $message = LiveChatMessage::with(['fromUser', 'toUser'])->find($message->id);

        // fire the event
        \event(new MessageSent($message));

        $all_array = $message->toArray() + ['profile_image'=>$profile_image];

        return response()->json([
            'state' => 1,
            'message' => $all_array,
            'from_user' => $seller_id,
            'to_user' => $request->to_user,
            'message' => $request->message,
            'image_url' => $send_image_url,
        ]);
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
