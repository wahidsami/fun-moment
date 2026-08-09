<?php

namespace App\Http\Controllers\Api;

use App\Helpers\FlashMsg;
use App\Http\Controllers\Controller;
use App\Mail\BasicMail;
use App\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Intervention\Image\Facades\Image;
use Modules\JobPost\Entities\BuyerJob;
use Modules\JobPost\Entities\JobRequest;
use Modules\JobPost\Entities\JobRequestConversation;

class SellerJobController extends Controller
{
    public function request_list()
    {
        $buyer_id = auth('sanctum')->user()->id;
        $all_job_requests = JobRequest::with('job')
            ->where('seller_id',$buyer_id)
            ->latest()
            ->paginate(10)->withQueryString()->through(function($item){
                $sellerInfo = User::with(["country","city","area"])->find($item->seller_id);
                $job_imge_details = get_attachment_image_by_id($item?->job?->image);
                $item->job_image  = empty($job_imge_details) ? null : $job_imge_details['img_url'];
                $imge_details = get_attachment_image_by_id($item->seller?->image);
                $item->seller_image = empty($imge_details) ? null : $imge_details['img_url'];
                $item->seller_country = $sellerInfo?->country?->country;
                $item->seller_city = $sellerInfo?->city?->service_city;
                $item->seller_area = $sellerInfo?->area?->service_area;
                return $item;
            });

        return response()->success([
            'all_job_requests'=>$all_job_requests,
        ]);
    }


    public function new_jobs()
    {
        $jobs = BuyerJob::whereDoesntHave('sellerViewJobs', function ($list){
            $list->where('seller_id',auth('sanctum')->user()->id);
        })->latest()->paginate(10)->withQueryString();

        $image_url=[''];
        foreach($jobs as $job){
            $image_url[]= get_attachment_image_by_id($job->image);
        }

        return response()->success([
            'jobs'=>$jobs,
            'image_url'=>$image_url,
        ]);
    }

    public function conversation(Request $request,$id)
    {
        $seller_id = auth('sanctum')->user()->id;
        $request_details = JobRequest::with('job')
            ->where('seller_id',$seller_id)
            ->where('id',$id)
            ->first();
        $all_messages = JobRequestConversation::where(['job_request_id'=>$id])->get();
        $q = $request->q ?? '';
        return response()->success([
            'request_details'=>$request_details,
            'all_messages'=>$all_messages,
            'q'=>$q,
        ]);
    }

    public function send_message(Request $request)
    {
        $request->validate([
            'job_request_id' => 'required',
            'user_type' => 'required|string|max:191',
            'message' => 'required',
            'send_notify_mail' => 'nullable|string',
            'file' => 'nullable|mimes:zip,jpg,jpeg,png,pdf,webp,xlsx, csv, xls,docx',
        ]);

        $request_info = JobRequestConversation::create([
            'job_request_id' => $request->job_request_id,
            'type' => $request->user_type,
            'message' => $request->message,
            'notify' => $request->send_notify_mail ? 'on' : 'off',
        ]);

        if ($request->hasFile('file')){
            $uploaded_file = $request->file;
            $file_extension = $uploaded_file->getClientOriginalExtension();
            $file_name =  pathinfo($uploaded_file->getClientOriginalName(),PATHINFO_FILENAME).time().'.'.$file_extension;

            // file scan start
            $file_extension = $uploaded_file->getClientOriginalExtension();
            if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $processed_image = Image::make($uploaded_file);
                $image_default_width = $processed_image->width();
                $image_default_height = $processed_image->height();

                $processed_image->resize($image_default_width, $image_default_height, function ($constraint) {
                    $constraint->aspectRatio();
                });
                $processed_image->save('assets/uploads/job-request/' . $file_name);
            }else{
                $uploaded_file->move('assets/uploads/job-request',$file_name);
            } // file scan end

            $request_info->attachment = $file_name;
            $request_info->save();
        }
        return response()->success([
            'msg'=>'Message Send Success',
        ]);
    }

    //job apply
    public function job_apply(Request $request){
        $seller_id = auth('sanctum')->user()->id;
        $user_type = auth('sanctum')->user()->user_type;

        if(Auth::guard('sanctum')->check() && $user_type === 1){
            return response()->error([
                'msg'=>'For create an offer you must register as a seller',
            ]);
        }

        if($request->isMethod('post')){
            if(Auth::guard('sanctum')->check()){
                $request->validate([
                    'seller_id'=> 'required',
                    'buyer_id'=> 'required',
                    'job_post_id'=> 'required',
                    'expected_salary'=> 'required',
                    'cover_letter'=> 'required',
                ]);
                if($request->expected_salary == '' || $request->cover_letter == ''){
                    return response()->error([
                        'msg'=>'Please enter your budget and description',
                    ]);
                }
                if($request->expected_salary > $request->job_price){
                    return response()->error([
                        'msg'=>'Your budget must less than the original price',
                    ]);
                }
                $request->validate([
                    'cover_letter'=>'required',
                ]);
                $seller_request_count = JobRequest::select('seller_id')
                    ->where('seller_id',$seller_id)
                    ->where('job_post_id',$request->job_post_id)
                    ->count();
                if($seller_request_count >=1){
                    return response()->error([
                        'msg'=>'You have already applied for this job.',
                    ]);
                }
                JobRequest::create([
                    'seller_id'=> $seller_id,
                    'buyer_id'=> $request->buyer_id,
                    'job_post_id'=> $request->job_post_id,
                    'expected_salary'=> $request->expected_salary,
                    'cover_letter'=> $request->cover_letter,
                ]);

                try {
                    // get buyer email
                    $buyer_email = User::select('id', 'email')->where('id', $request->buyer_id)->first();
                    $message_body = __('New application is created for your job').'. '.'<span class="verify-code">'.__('Your job id is').' #'.$request->job_post_id.'</span>';
                    Mail::to($buyer_email->email)->send(new BasicMail([
                        'subject' => __('New Application Created'),
                        'message' => $message_body
                    ]));
                } catch (\Exception $e) {
                    return  response()->success([
                        'msg'=>'You have successfully applied for this job',
                        'note'=>'Email Sending Failed',
                    ]);
                }
                return response()->success([
                    'msg'=>'You have successfully applied for this job.',
                ]);
            }
            return response()->error([
                'msg'=>'You must login to apply for a job.',
            ]);
        }
    }
}
