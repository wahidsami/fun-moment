<?php

namespace App\Http\Controllers;

use App\Admin;
use App\AdminNotice;
use App\Helpers\FlashMsg;
use Illuminate\Http\Request;

class AdminNoticeController extends Controller
{
    public function all_notice(){
        $notices = AdminNotice::all();
        return view('backend.pages.notice.all-notice', compact('notices'));
    }
    public function add_notice_page(){
        return view('backend.pages.notice.add-notice');
    }

    public function add_notice(Request $request) {

        $this->validate($request, [
            'title' => 'required',
            'notice_type' => 'required',
            'notice_for' => 'required',
            'expire_date' => 'required',
        ]);


        AdminNotice::create([
            'title' => $request->title,
            'description' => $request->description,
            'notice_type' => $request->notice_type,
            'notice_for' => $request->notice_for,
            'expire_date' => $request->expire_date,
            'status' => $request->status,
        ]);

        return redirect()->back()->with(FlashMsg::item_new('Notice Created Successfully'));
    }


        public function notice_edit($id=null){
             $notice =   AdminNotice::find($id);
          return view('backend.pages.notice.edit-notice', compact('notice'));
        }

        public function notice_update(Request $request){

                $this->validate($request, [
                    'title' => 'required',
                    'notice_type' => 'required',
                    'notice_for' => 'required',
                    'expire_date' => 'required',
                ]);

             $notice = AdminNotice::find($request->notice_id);;
            if ($notice) {
                $notice->title = $request->title;
                $notice->description = $request->description;
                $notice->notice_type = $request->notice_type;
                $notice->notice_for = $request->notice_for;
                $notice->expire_date = $request->expire_date;
                $notice->status = $request->status;
                $notice->save();
                return redirect()->route('admin.all.notice')->with(FlashMsg::item_new('Notice Update Success'));
            } else {
                return redirect()->back()->with('error', 'Notice not found');
            }

        }


    public function change_status($id){
        $notice = AdminNotice::select('status')->where('id',$id)->first();
        if($notice->status==1){
            $status = 0;
        }else{
            $status = 1;
        }
        AdminNotice::where('id',$id)->update(['status'=>$status]);
        return redirect()->back()->with(FlashMsg::item_new('Status Change Success'));
    }

    public function new_notice_delete($id){
        AdminNotice::findOrFail($id)->delete();
        return redirect()->back()->with(FlashMsg::item_delete('Notice Deleted Success'));
    }
}
