<?php

namespace App\Http\Controllers;

use App\AdminNotification;
use App\Helpers\FlashMsg;
use Illuminate\Http\Request;

class AdminNofificationController extends Controller
{
      public function notifications(){
          $notifications = AdminNotification::all();
          return view('backend.pages.notifications.all-notification', compact('notifications'));
      }

      public function notificationDelete($id){
              AdminNotification::findOrFail($id)->delete();
          return redirect()->back()->with(FlashMsg::item_delete('Notification Deleted Success'));
      }
}
