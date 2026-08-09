<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str;


class Demo
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $not_allow_path = [
          'admin-home',
          'seller',
          'buyer',
        ];
        $allow_path = [
            // 'admin-home/media-upload/alt',
            // 'admin-home/media-upload',
            // 'admin-home/media-upload/loadmore',
            'seller/logout',
            'buyer/send',
            'seller/send',
            'broadcasting/auth',
            'seller/get-dependent-subcategory',
            'buyer/jobpost/subcategory/get',
            'seller/get-child-category-by-subcategory',
            // 'seller/profile-edit',
            // 'admin-home/country/edit-country',
            // 'admin-home/widgets/update',
            //'buyer/jobpost/subcategory/get',
            // 'admin-home/languages/words/update/en_GB',
            // 'admin-home/update-order',
            // 'admin-home/new',
            // 'admin-home/update-order',
            // 'admin-home/get-admin-markup',
            // 'admin-home/update',
            // 'admin-home/general-settings/database-upgrade'
            // 'admin-home/admin/notice/add',
            // 'admin-home/admin/notice-status/1',
            // 'admin-home/email-template/from-admin/new/order/admin/seller/buyer'
            // 'admin-home/category/add-new-category',
            // 'admin-home/category/edit-category',
            // 'admin-home/subcategory/add-new-subcategory',
            // 'buyer/jobpost/edit-job',
            // 'buyer/jobpost/add-job',
            ];
        $contains = Str::contains($request->path(), $not_allow_path);
        if($request->isMethod('POST') || $request->isMethod('PUT')) {

            if($contains && !in_array($request->path(),$allow_path)){
                if ($request->ajax()){
                    return response()->json(['type' => 'warning' , 'msg' => 'This is demonstration purpose only, you may not able to change few settings, once your purchase this script you will get access to all settings.']);
                }
                toastr_warning('This is demonstration purpose only, you may not able to change few settings, once your purchase this script you will get access to all settings.');
                return redirect()->back()->with(['type' => 'warning' , 'msg' => 'This is demonstration purpose only, you may not able to change few settings, once your purchase this script you will get access to all settings.']);
            }

        }

        return $next($request);
    }
}
