<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\JobPost\Entities\BuyerJob;
use Modules\JobPost\Entities\JobRequest;
use Modules\JobPost\Entities\SellerViewJob;

class JobDetailsController extends Controller
{
    public function recent_jobs()
    {
        $current_date = date('Y-m-d h:i:s');
        $recent_jobs = BuyerJob::where('status', 1)
            ->where('is_job_on', 1)
            ->where('dead_line', '>=' ,$current_date)
            ->orderBy("id", "desc")
            ->take(10)
            ->get();

        if(!empty($recent_jobs)){
            $jobs_image=[];
            foreach($recent_jobs as $job){
                $jobs_image[]= get_attachment_image_by_id($job->image);
            }
            return response()->success([
                'recent_10_jobs'=> $recent_jobs,
                'jobs_image'=> $jobs_image,
            ]);
        }else{
            return response()->error([
                'recent_jobs' => __('No Jobs found'),
            ]);
        }

    }
    
    public function jobs_all()
    {
        $current_date = date('Y-m-d h:i:s');
        $all_jobs = BuyerJob::where('status', 1)
            ->where('is_job_on', 1)
            ->where('dead_line', '>=' ,$current_date)
            ->latest()
            ->paginate(6)
            ->withQueryString()
            ->through(function($item){
                $imge_details = get_attachment_image_by_id($item->image);
                $item->image_url = empty($imge_details) ? null : $imge_details['img_url'];

                //check already hired or not
                $is_job_hired = $item->job_request->where('is_hired',1)->count() ?? 0;
                $already_applied_status = __('Apply Now');//__('Already Hired');
                $is_hired = false;
                
                if($is_job_hired >= 1 && auth("sanctum")->check()){
                     $is_hired = true;
                     $already_applied_status = __('Already Hired');
                }else{
                    if(auth("sanctum")->check()){
                        if($item?->job_request?->where('seller_id',auth('sanctum')->id())?->first()){
                            $is_hired = true;
                            $already_applied_status = __('Already Applied');
                        }elseif(\Carbon\Carbon::parse($item->dead_line)->gt(\Carbon\Carbon::now())){
                           $already_applied_status = __('Job Expired');
                        }elseif(auth("sanctum")->user()->user_type === 1){
                            $already_applied_status = __('Only Seller Can Apply');
                        }else{
                            $is_hired = false;
                            $already_applied_status = __('Apply Now');
                        }
                    }else{
                        $is_hired = false;
                        $already_applied_status = __('Login to apply');
                    }
                }
                
                $item->is_applied = [
                    'is_hired' => false,
                    'message' => $already_applied_status
                ];
                
                return $item;
            });

        return response()->success([
            'all_jobs'=>$all_jobs,
        ]);
    }

    public function job_details($id)
    {
        $current_date = date('Y-m-d h:i:s');

        $job_details = BuyerJob::with(['job_request','buyer'])->where('id',$id)->firstOrFail();
        $same_buyer_jobs = BuyerJob::where('buyer_id',$job_details->buyer_id)->where('is_job_on', 1)->where('dead_line', '>=' ,$current_date)->take(2)->get()->except($job_details->id);

        $similar_jobs = BuyerJob::where('is_job_on', 1)->where('dead_line', '>=' ,$current_date)->take(3)->inRandomOrder()->get()->except($job_details->id);

        $job_view = BuyerJob::select('view')->where('id', $job_details->id)->first();
        $view_count = $job_view->view + 1;
        BuyerJob::where('id', $job_details->id)->update([
            'view' => $view_count,
        ]);

        $seller = auth('sanctum')->user();
        if($seller && $seller->user_type == 0) {
            $seller_job_view_count = SellerViewJob::where('seller_id', $seller->id)->where('job_post_id', $job_details->id)->count();
            if ($seller_job_view_count < 1){
                SellerViewJob::create([
                    'job_post_id' => $job_details->id,
                    'seller_id' => $seller->id,
                ]);
            }
        }

        $is_job_hired = JobRequest::where('job_post_id',$job_details->id)->where('is_hired',1)->count();

        return response()->success([
            'job_details'=>$job_details,
            'same_buyer_jobs'=>$same_buyer_jobs,
            'similar_jobs'=>$similar_jobs,
            'is_job_hired'=>$is_job_hired,
        ]);
    }

}
