<?php

namespace App\Http\Controllers;

use App\User;
use App\Admin;
use App\Order;
use App\Report;
use App\Review;
use App\ExtraService;
use App\OrderInclude;
use App\StaticOption;
use App\SupportTicket;
use App\Mail\BasicMail;
use App\OrderAdditional;
use App\Helpers\FlashMsg;
use App\AdminNotification;
use App\ReportChatMessage;
use App\Services\SMSService;
use Illuminate\Http\Request;
use App\OrderCompleteDecline;
use App\SupportTicketMessage;
use App\Events\SupportMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Intervention\Image\Facades\Image;
use Modules\JobPost\Entities\JobRequest;
use Yajra\DataTables\Facades\DataTables;
use App\Helpers\DataTableHelpers\General;

class OrdersController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:order-list|order-status|order-view|cancel-order-list|order-success-setting', ['only' => ['index', 'apiOrders', 'apiOrderDetails']]);
        $this->middleware('permission:manage_payments', ['only' => ['apiPayments', 'apiRefundPayment']]);
        $this->middleware('permission:order-status', ['only' => ['orderStatus', 'apiUpdateOrderStatus']]);
        $this->middleware('permission:order-view', ['only' => ['orderDetails']]);
        $this->middleware('permission:cancel-order-list', ['only' => ['cancelOrders']]);
        $this->middleware('permission:order-success-setting', ['only' => ['order_success_settings']]);
    }

    public function apiOrders(): JsonResponse
    {
        $orders = Order::with(['service', 'seller', 'buyer'])
            ->orderByDesc('id')
            ->take(100)
            ->get()
            ->map(function (Order $order) {
                return $this->formatOrderPayload($order);
            })->values();

        return response()->json([
            'status' => 'success',
            'orders' => $orders,
        ]);
    }

    public function apiUpdateOrderStatus(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled',
        ]);

        $order = Order::with(['service', 'seller', 'buyer'])->findOrFail($id);
        $legacyStatus = $this->mapOrderStatusToLegacyValue($validated['status']);

        Order::where('id', $id)->update(['status' => $legacyStatus]);

        return response()->json([
            'status' => 'success',
            'message' => __('Order status updated successfully'),
            'order' => $this->formatOrderPayload($order->fresh(['service', 'seller', 'buyer'])),
        ]);
    }

    public function apiOrderDetails($id): JsonResponse
    {
        $order = Order::with(['service', 'seller', 'buyer', 'orderIncludes', 'orderAdditionals'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'order' => $this->formatOrderPayload($order),
            'includes' => $order->orderIncludes,
            'additionals' => $order->orderAdditionals,
        ]);
    }

    public function apiPayments(): JsonResponse
    {
        $orders = Order::with(['buyer', 'seller'])
            ->orderByDesc('id')
            ->take(100)
            ->get()
            ->map(function (Order $order) {
                return $this->formatPaymentPayload($order);
            })->values();

        $summary = [
            'succeeded_total' => round($orders->where('status', 'succeeded')->sum('amount'), 2),
            'refunded_total' => round($orders->where('status', 'refunded')->sum('amount'), 2),
            'pending_total' => round($orders->where('status', 'pending')->sum('amount'), 2),
            'transaction_count' => $orders->count(),
        ];

        return response()->json([
            'status' => 'success',
            'payments' => $orders,
            'summary' => $summary,
        ]);
    }

    public function apiRefundPayment(Request $request, string $txnId): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $order = $this->resolvePaymentOrder($txnId);

        if (is_null($order)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Payment transaction not found'),
            ], 404);
        }

        if ($this->normalizePaymentStatus($order->payment_status) === 'refunded') {
            return response()->json([
                'status' => 'success',
                'message' => __('Payment already refunded'),
                'payment' => $this->formatPaymentPayload($order->fresh(['buyer', 'seller'])),
            ]);
        }

        Order::where('id', $order->id)->update([
            'payment_status' => 'refunded',
            'status' => 4,
        ]);

        $updatedOrder = $order->fresh(['buyer', 'seller']);

        return response()->json([
            'status' => 'success',
            'message' => __('Refund processed successfully'),
            'payment' => $this->formatPaymentPayload($updatedOrder),
        ]);
    }

    private function formatOrderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'service_id' => $order->service_id,
            'service_title_en' => optional($order->service)->title ?? '',
            'service_title_ar' => optional($order->service)->title ?? '',
            'buyer_id' => (int) $order->buyer_id,
            'buyer_name' => optional($order->buyer)->name ?? $order->name ?? '',
            'seller_id' => (int) $order->seller_id,
            'seller_name' => optional($order->seller)->name ?? '',
            'amount' => (float) ($order->total ?? 0),
            'status' => $this->mapLegacyOrderStatus($order->status),
            'created_at' => optional($order->created_at)->toDateString(),
            'payment_status' => $this->normalizePaymentStatus($order->payment_status),
        ];
    }

    private function formatPaymentPayload(Order $order): array
    {
        $transactionId = !empty($order->transaction_id) ? (string) $order->transaction_id : 'order_' . $order->id;

        return [
            'id' => $transactionId,
            'transaction_id' => $transactionId,
            'order_id' => (int) $order->id,
            'user_name' => optional($order->buyer)->name ?? $order->name ?? '',
            'amount' => (float) ($order->total ?? 0),
            'method' => $this->mapPaymentMethod($order->payment_gateway),
            'status' => $this->mapDashboardPaymentStatus($order->payment_status),
            'created_at' => optional($order->created_at)->format('Y-m-d H:i:s'),
            'payment_gateway' => $order->payment_gateway ?? '',
            'order_status' => $this->mapLegacyOrderStatus($order->status),
        ];
    }

    private function resolvePaymentOrder(string $txnId): ?Order
    {
        $order = Order::where('transaction_id', $txnId)->first();

        if (!is_null($order)) {
            return $order;
        }

        if (preg_match('/^order_(\d+)$/', $txnId, $matches)) {
            return Order::where('id', $matches[1])->first();
        }

        if (ctype_digit($txnId)) {
            return Order::where('id', (int) $txnId)->first();
        }

        return null;
    }

    private function mapDashboardPaymentStatus($paymentStatus): string
    {
        if ($paymentStatus === 'complete') {
            return 'succeeded';
        }

        if ($paymentStatus === 'pending' || empty($paymentStatus)) {
            return 'pending';
        }

        if ($paymentStatus === 'refund' || $paymentStatus === 'refunded') {
            return 'refunded';
        }

        return 'failed';
    }

    private function mapPaymentMethod($paymentGateway): string
    {
        $gateway = strtolower((string) $paymentGateway);

        if ($gateway === '') {
            return 'credit_card';
        }

        if (in_array($gateway, ['paypal', 'mollie', 'paytm', 'stripe', 'razorpay', 'flutterwave', 'paystack', 'marcadopago', 'instamojo', 'cashfree', 'payfast', 'midtrans', 'squareup', 'cinetpay', 'paytabs', 'billplz', 'zitopay', 'kineticpay'], true)) {
            return 'credit_card';
        }

        if ($gateway === 'manual_payment' || $gateway === 'cash_on_delivery') {
            return $gateway;
        }

        if ($gateway === 'wallet' || $gateway === 'current_balance') {
            return 'wallet';
        }

        if (in_array($gateway, ['stc_pay', 'apple_pay', 'credit_card'], true)) {
            return $gateway;
        }

        return 'credit_card';
    }

    private function mapLegacyOrderStatus($status): string
    {
        $status = (int) $status;

        return match ($status) {
            0 => 'pending',
            1, 3 => 'in_progress',
            2 => 'completed',
            4 => 'cancelled',
            default => 'pending',
        };
    }

    private function mapOrderStatusToLegacyValue(string $status): int
    {
        return match ($status) {
            'pending' => 0,
            'in_progress' => 1,
            'completed' => 2,
            'cancelled' => 4,
            default => 0,
        };
    }

    private function normalizePaymentStatus($paymentStatus): string
    {
        if ($paymentStatus === 'complete') {
            return 'paid';
        }

        if ($paymentStatus === 'pending') {
            return 'unpaid';
        }

        if ($paymentStatus === 'refund' || $paymentStatus === 'refunded') {
            return 'refunded';
        }

        return 'unpaid';
    }

    public function index(Request $request)
    {

        if ($request->ajax()) {
            $data = Order::select('*')
                ->orderBy('id', 'desc')
                ->take(20)->get();

            return DataTables::of($data)
                ->addIndexColumn()

                ->addColumn('checkbox', function ($row) {
                    return General::bulkCheckbox($row->id);
                })

                ->addColumn('id', function ($row) {
                    return $row->id;
                })

                ->addColumn('name', function ($row) {
                    return $row->name;
                })

                ->addColumn('email', function ($row) {
                    return $row->email;
                })

                ->addColumn('phone', function ($row) {
                    return $row->phone;
                })

                ->addColumn('address', function ($row) {
                    return $row->address;
                })

                ->addColumn('amount', function ($row) {
                    return float_amount_with_currency_symbol($row->total);
                })

                ->addColumn('payment_status', function ($row) {
                    $payment_status = __('pending');
                    $payment_complete = __('complete');
                    $action = '';
                    if ($row->payment_status == 'pending') {
                        $action .= General::orderPaymentStatusChange(route('admin.order.change.status', $row->id), $row->payment_status);
                        return  $payment_status . $action;
                    } elseif ($row->payment_status == 'complete') {
                        return $payment_complete;
                    } else {
                        return $payment_status;
                    }
                })

                ->addColumn('seller_name', function ($row) {
                    $action = '';
                    // Check the order status
                    if ($row->status != 2 && $row->status != 3 && $row->status != 4) {
                        $action .= General::orderChangeSeller($row->id, route('admin.seller.change.order', $row->id));
                    }
                    // Retrieve the seller's name
                    $order_seller_name = $row->seller ? $row->seller->name : '';

                    return $order_seller_name . $action;
                })

                ->addColumn('status', function ($row) {
                    $action = '';
                    $admin = auth()->guard('admin')->user();
                    if ($row->status == 0) {
                        //if order status pending admin change any order status
                        if ($admin->can('pending-order-cancel')) {
                            $action .= General::pendingOrderCancel($row->id, route('admin.cancel.pending.order', $row->id), $row->status);
                            return $action;
                        }
                    } elseif ($row->status == 1) {
                        //if order status active admin change any order status
                        if ($admin->can('pending-order-cancel')) {
                            $action .= General::pendingOrderCancel($row->id, route('admin.cancel.pending.order', $row->id), $row->status);
                            return $action;
                        }
                    } else {
                        return General::orderStatus($row->status);
                    }
                })

                ->addColumn('is_order_online', function ($row) {
                    return General::orderType($row->is_order_online);
                })

                ->addColumn('action', function ($row) {
                    $action = '';
                    $admin = auth()->guard('admin')->user();
                    if ($admin->can('order-view')) {
                        $action .= General::viewIcon(route('admin.orders.details', $row->id));
                    }
                    return $action;
                })
                ->rawColumns(['checkbox', 'status', 'action', 'is_order_online', 'payment_status', 'seller_name'])
                ->make(true);
        }
        return view('backend.pages.orders.index');
    }


    public function getSellersForChange(Request $request)
    {
        $searchTerm = $request->input('q');
        $query = User::select('id', 'name');
        if ($searchTerm) {
            $query->where('name', 'LIKE', "%$searchTerm%");
        }
        $sellers = $query->take(100)->get();
        return response()->json($sellers);
    }

    //cancel pending order
    public function cancelPendingOrder(Request $request, $id = null)
    {
        Order::where('id', $id)->update(['status' => 4]);
        return redirect()->back()->with(FlashMsg::item_new('Status Update Change to Cancel'));
    }

    public function orderStatusChange(Request $request, $id = null)
    {
        $this->validate($request, [
            'status_id' => 'required',
        ]);

        $order_status = Order::select('id', 'seller_id', 'status', 'email', 'name', 'job_post_id', 'order_from_job')->where('id', $request->id)->first();
        $current_status = $order_status->status;

        $old_status = '';
        $pending = __('Pending');
        $active = __('Active');
        $completed = __('Completed');
        $delivered = __('Delivered');
        $cancel = __('Cancel');

        if ($current_status == 0) {
            $old_status = $pending;
        } elseif ($current_status == 1) {
            $old_status = $active;
        } elseif ($current_status == 2) {
            $old_status = $completed;
        } elseif ($current_status == 3) {
            $old_status = $delivered;
        } else {
            $old_status = $cancel;
        }

        $seller_email = optional($order_status->seller)->email;
        $seller_name = optional($order_status->seller)->name;

        if ($order_status->status == 0) {
            $new_status = 'Active';
        } elseif ($order_status->status == 1) {
            $new_status = 'Completed';
        } else {
            $new_status = 'Cancel';
        }

        Order::where('id', $request->id)->update(['status' => $request->status_id]);

        try {
            $order_status_change_title = __('Order Status Changed.') . $order_status->id;
            $message_status = __('Order Status Changed.') . ' ' . __('Order ID:') . $order_status->id;
            $message = str_replace(["@name", "@old_status", "@new_status", "@order_id"], [$order_status->name, $old_status, $new_status, $order_status->id], $message_status);
            Mail::to($order_status->email)->queue(new BasicMail([
                'subject' => $order_status_change_title,
                'message' => $message
            ]));

            $message = str_replace(["@name", "@old_status", "@new_status", "@order_id"], [$seller_name, $old_status, $new_status, $order_status->id], $message_status);
            Mail::to($seller_email)->queue(new BasicMail([
                'subject' => $order_status_change_title,
                'message' => $message
            ]));

            $smsService = new SMSService();
            $order_details = Order::find($request->id);
            $seller_phone = User::select('phone')->where('id', $order_details->seller_id)->first();
            $buyer_phone = User::select('phone')->where('id', $order_details->buyer_id)->first();
            $message = __('Order Status Changed.') . __(' and Order ID:') . $request->id;
            //send sms to buyer
            $buyer_phone=$buyer_phone->phone;
            $smsService->send_sms($buyer_phone,  $message);
            //send sms to seller
            $seller_phone=$seller_phone->phone;
            $smsService->send_sms($seller_phone,  $message);



            //$smsService->send_sms($number,  $message);
            //$smsService->send_sms($number,  $message);

            $admins = Admin::all();
            foreach ($admins as $admin)  // Send SMS to all super admin
            {

                if ($admin->role == "Super Admin") {
                    $seller_name = User::select('name')->where('id', $order_details->seller_id)->first();
                    $seller_name = $seller_name ? $seller_name->name : 'Unknown Seller';
                    $smsService->send_sms($admin->phone,  $message);
                    //smsService->send_sms($number,  $message);
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->with(FlashMsg::item_new($e->getMessage()));
        }

        return redirect()->back()->with(FlashMsg::item_new('Status Update Success'));
    }


    public function orderSellerChange(Request $request, $id = null)
    {

        $this->validate($request, [
            'id' => 'required',
            'seller_id' => 'required',
        ]);

        $order_id = $request->id;
        $seller_id = $request->seller_id;

        $order = Order::where('id', $order_id)->first();
        $seller_info = User::where('id', $order->seller_id)->where('user_type', 0)->first();
        $old_order_seller_email = $seller_info->email;

        if (!empty($order) && !empty($seller_id) && $order->seller_id == $seller_id) {
            return redirect()->back()->with(FlashMsg::item_delete(__('The selected seller is already assigned to this order. Please select another seller.')));
        }

        if (empty($order)) {
            return redirect()->back()->with(FlashMsg::item_new(__('Order not found')));
        }

        if (empty($seller_info)) {
            return redirect()->back()->with(FlashMsg::item_new(__('Seller not found')));
        }

        if (!empty($order) && !in_array($order->status, [2, 3, 4])) {

            // update order seller id
            $order->update([
                'seller_id' => $request->seller_id,
            ]);

            // update order complete decline info
            $order_complete_declines = OrderCompleteDecline::where('order_id', $order_id)->get();
            // Check if there are records to update
            if ($order_complete_declines->isNotEmpty()) {
                foreach ($order_complete_declines as $order_complete_decline) {
                    $order_complete_decline->update([
                        'seller_id' => $seller_id,
                    ]);
                }
            }

            // update order support ticket
            $order_support_tickets = SupportTicket::where('order_id', $order_id)->get();
            // Check if there are records to update
            if ($order_support_tickets->isNotEmpty()) {
                foreach ($order_support_tickets as $order_support_ticket) {
                    $order_support_ticket->update([
                        'seller_id' => $seller_id,
                    ]);
                }
            }

            // update order reports
            $order_reports = Report::where('order_id', $order_id)->get();
            // Check if there are records to update
            if ($order_reports->isNotEmpty()) {
                foreach ($order_reports as $order_report) {
                    $order_report->update([
                        'seller_id' => $seller_id,
                    ]);
                }
            }


            // update order report chat messages
            $order_reports_ids = $order_reports->pluck('id')->toArray();
            if (!empty($order_reports_ids)) {
                $report_chat_messages = ReportChatMessage::whereIn('report_id', $order_reports_ids)->get();

                // Check if there are records to update
                if ($report_chat_messages->isNotEmpty()) {
                    foreach ($report_chat_messages as $report_chat_message) {
                        $report_chat_message->update([
                            'seller_id' => $seller_id,
                        ]);
                    }
                }
            }

            // update order reviews
            $order_reviews = Review::where('order_id', $order_id)->get();
            // Check if there are records to update
            if ($order_reviews->isNotEmpty()) {
                foreach ($order_reviews as $order_review) {
                    $order_review->update([
                        'seller_id' => $seller_id,
                    ]);
                }
            }

            $order = Order::where('id', $order_id)->first();

            // Retrieve seller's email and name
            $seller_email = optional($order->seller)->email;
            $seller_name = optional($order->seller)->name;
            $seller_phone = optional($order->seller)->phone;

            try {
                // Mail to buyer
                $buyer_subject = __('Order Seller Changed') . ' - ' . $order->id;
                $buyer_message = __('The seller :seller_name for Order ID :order_id has been changed.', ['seller_name' => $seller_name, 'order_id' => $order->id]);

                Mail::to($order->email)->queue(new BasicMail([
                    'subject' => $buyer_subject,
                    'message' => $buyer_message,
                ]));

                // Mail to new assigned seller
                $seller_subject = __('Order Seller Changed') . ' - ' . $order->id;
                $seller_message = __('You have been assigned as the new seller for Order ID :order_id.', ['order_id' => $order->id]);
                Mail::to($seller_email)->queue(new BasicMail([
                    'subject' => $seller_subject,
                    'message' => $seller_message,
                ]));

                // Mail to old seller
                $old_seller_subject = __('Order Seller Changed') . ' - ' . $order->id;
                $old_seller_message = __('You have been replaced as the seller for Order ID :order_id. Thank you for your previous efforts on this order.', ['order_id' => $order->id]);

                Mail::to($old_order_seller_email)->queue(new BasicMail([
                    'subject' => $old_seller_subject,
                    'message' => $old_seller_message,
                ]));

                //send sms 

                $smsService = new SMSService();
                $order_details = Order::find($order_id);
                $buyer_phone = User::select('phone')->where('id', $order_details->buyer_id)->first();
                $message_for_buyer = __('The seller :seller_name for Order ID :order_id has been changed.', ['seller_name' => $seller_name, 'order_id' => $order->id]);
                $message_for_new_seller = __('You have been assigned as the new seller for Order ID :order_id.', ['order_id' => $order->id]);
                //send sms to buyer
                $buyer_phone=$buyer_phone->phone;
                $smsService->send_sms($buyer_phone,  $message_for_buyer);
                //send sms to seller
                $smsService->send_sms($seller_phone,  $message_for_new_seller);



                // $smsService->send_sms($number,  $message_for_buyer);
                //$smsService->send_sms($number,  $message_for_new_seller);

                $old_order_seller_phone = $seller_info->phone;
                $message_for_old_seller = __('You have been replaced as the seller for Order ID :order_id. Thank you for your previous efforts on this order.', ['order_id' => $order->id]);

                //send sms to seller
                $smsService->send_sms($old_order_seller_phone,  $message_for_old_seller);
                //smsService->send_sms($number,    $message_for_old_seller);
                $admins = Admin::all();
                $admin_message = __('The seller :seller_name for Order ID :order_id has been changed.', ['seller_name' => $seller_name, 'order_id' => $order->id]);
                foreach ($admins as $admin)  // Send SMS to all super admin
                {

                    if ($admin->role == "Super Admin") {

                        $smsService->send_sms($admin->phone,  $admin_message);
                        // $smsService->send_sms($number,  $admin_message);
                    }
                }
            } catch (\Exception $e) {
                return redirect()->back()->with(FlashMsg::item_delete($e->getMessage()));
            }
        }

        return redirect()->back()->with(FlashMsg::item_new(__('Seller changed successfully')));
    }


    //all cancel orders
    public function cancelOrders()
    {
        $orders = Order::where('status', 4)->latest()->get();
        return view('backend.pages.orders.cancelled', compact('orders'));
    }

    //cancel order return money
    public function cancelOrderMoneyReturn($id = null)
    {
        Order::where('id', $id)->update(['cancel_order_money_return' => 1]);
        return redirect()->back()->with(FlashMsg::item_new('Status Update Success'));
    }

    //cancel order delete
    public function cancelOrderDelete($id)
    {
        Order::find($id)->delete();
        return redirect()->back()->with(FlashMsg::item_new('Cancel Order Delete Success'));
    }

    //order complete request
    public function orderCompleteRequest(Request $request, $id = null)
    {
        if ($request->isMethod('post')) {
            Order::where('id', $id)->update(['order_complete_request' => 2, 'status' => 2]);
            return redirect()->back()->with(FlashMsg::item_new('Order Status Change to Complete'));
        }
        $orders = Order::select('id', 'total', 'updated_at', 'seller_id', 'buyer_id')->with('seller', 'buyer')
            ->where('order_complete_request', 1)
            ->latest()
            ->paginate(10);
        return view('backend.pages.orders.order-complete-request', compact('orders'));
    }

    public function orderDetails($id)
    {

        $order_details = Order::where('id', $id)->first();
        $order_includes = OrderInclude::where('order_id', $id)->get();
        $order_additionals = OrderAdditional::where('order_id', $id)->get();

        // admin notification
        $notification = AdminNotification::where('order_id', $id)->first();
        if (!empty($notification)) {
            if ($notification->status == 0) {
                AdminNotification::where('order_id', $id)->update(['status' => 1]);
            }
        }

        return view('backend.pages.orders.order-details', compact('order_details', 'order_includes', 'order_additionals'));
    }

    public function orderStatus(Request $request)
    {
        Order::where('id', $request->order_id)->update(['status' => $request->status]);
        return redirect()->back()->with(FlashMsg::item_new('Status Update Success'));
    }

    public function order_success_settings()
    {
        $showResult = StaticOption::where('option_name', 'result')->first();
        $showResult = $showResult ? $showResult->option_value : null;
        // dd($showResult);
        return view('backend.pages.orders.order-success-settings', compact("showResult"));
    }

    public function seller_buyer_report()
    {
        $reports = Report::latest()->get();
        return view('backend.pages.orders.seller-buyer-report', compact('reports'));
    }

    public function delete_report($id)
    {
        Report::find($id)->delete();
        return redirect()->back()->with(FlashMsg::item_new('Report Deleted Success'));
    }

    public function chat_to_seller(Request $request, $report_id, $seller_id)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'message' => 'required',
                'notify' => 'nullable|string',
                'attachment' => 'nullable|mimes:zip',
            ]);

            $ticket_info = ReportChatMessage::create([
                'report_id' => $report_id,
                'seller_id' => $seller_id,
                'message' => $request->message,
                'type' => 'admin',
                'notify' => $request->send_notify_mail ? 'on' : 'off',
            ]);

            if ($request->hasFile('attachment')) {
                $uploaded_file = $request->attachment;
                $file_extension = $uploaded_file->extension();
                $file_name =  pathinfo($uploaded_file->getClientOriginalName(), PATHINFO_FILENAME) . time() . '.' . $file_extension;

                // file scan start
                $file_extension = $uploaded_file->getClientOriginalExtension();
                if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $processed_image = Image::make($uploaded_file);
                    $image_default_width = $processed_image->width();
                    $image_default_height = $processed_image->height();

                    $processed_image->resize($image_default_width, $image_default_height, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $processed_image->save('assets/uploads/ticket/' . $file_name);
                } else {
                    $uploaded_file->move('assets/uploads/ticket', $file_name);
                } // file scan end

                $ticket_info->attachment = $file_name;
                $ticket_info->save();
            }

            //send mail to user
            //            event(new SupportMessage($ticket_info));
            return redirect()->back()->with(FlashMsg::item_new(__('Message Send')));
        }
        $report_details = Report::where('id', $report_id)->where('seller_id', $seller_id)->first();
        $all_messages = ReportChatMessage::where('report_id', $report_id)
            ->where('seller_id', $seller_id)
            ->get();
        $q = $request->q ?? '';
        return view('backend.pages.orders.report-chat', compact('report_details', 'all_messages', 'q'));
    }

    public function chat_to_buyer(Request $request, $report_id, $buyer_id)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'message' => 'required',
                'notify' => 'nullable|string',
                'attachment' => 'nullable|mimes:zip',
            ]);

            $ticket_info = ReportChatMessage::create([
                'report_id' => $report_id,
                'buyer_id' => $buyer_id,
                'message' => $request->message,
                'type' => 'admin',
                'notify' => $request->send_notify_mail ? 'on' : 'off',
            ]);

            if ($request->hasFile('attachment')) {
                $uploaded_file = $request->attachment;
                $file_extension = $uploaded_file->extension();
                $file_name =  pathinfo($uploaded_file->getClientOriginalName(), PATHINFO_FILENAME) . time() . '.' . $file_extension;

                // file scan start
                $file_extension = $uploaded_file->getClientOriginalExtension();
                if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $processed_image = Image::make($uploaded_file);
                    $image_default_width = $processed_image->width();
                    $image_default_height = $processed_image->height();

                    $processed_image->resize($image_default_width, $image_default_height, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $processed_image->save('assets/uploads/ticket/' . $file_name);
                } else {
                    $uploaded_file->move('assets/uploads/ticket', $file_name);
                } // file scan end

                $ticket_info->attachment = $file_name;
                $ticket_info->save();
            }

            //send mail to user
            //            event(new SupportMessage($ticket_info));
            return redirect()->back()->with(FlashMsg::item_new(__('Message Send')));
        }
        $report_details = Report::where('id', $report_id)->where('buyer_id', $buyer_id)->first();
        $all_messages = ReportChatMessage::where('report_id', $report_id)
            ->where('buyer_id', $buyer_id)
            ->get();
        $q = $request->q ?? '';
        return view('backend.pages.orders.report-chat-buyer', compact('report_details', 'all_messages', 'q'));
    }

    public function order_success_settings_update(Request $request)
    {
        $this->validate($request, [
            'success_title' => 'nullable|string',
            'success_subtitle' => 'nullable|string',
            'success_details_title' => 'nullable|string',
            'button_title' => 'nullable|string',
            'button_url' => 'nullable|string',
        ]);

        $all_fields = [
            'success_title',
            'success_subtitle',
            'success_details_title',
            'button_title',
            'button_url',
            'order_date_time_change_permission',
        ];
        foreach ($all_fields as $field) {
            update_static_option($field, $request->$field);
        }
        return redirect()->back()->with(FlashMsg::settings_update());
    }

    public function order_user_settings_update(Request $request)
    {

        $show = false;
        $result = $request->input("result");


        if ($result == "Checkbox is checked.") {
            $show = true;
        }
        $field = "result";

        update_static_option($field, $result);

        return redirect()->back()->with(FlashMsg::settings_update());
    }



    public function order_invoice_settings_update(Request $request)
    {
        $this->validate($request, [
            'bill_to_title' => 'nullable|string',
            'ship_to_title' => 'nullable|string',
            'invoice_title' => 'nullable|string',
            'invoice_no_title' => 'nullable|string'
        ]);

        $all_fields = [
            'bill_to_title',
            'ship_to_title',
            'invoice_title',
            'invoice_no_title'
        ];
        foreach ($all_fields as $field) {
            update_static_option($field, $request->$field);
        }
        return redirect()->back()->with(FlashMsg::settings_update());
    }

    public function change_payment_status($id)
    {

        $payment_status = Order::select('id', 'seller_id', 'payment_status', 'email', 'name', 'job_post_id', 'order_from_job')->where('id', $id)->first();
        $old_status = $payment_status->payment_status;
        $seller_email = optional($payment_status->seller)->email;
        $seller_name = optional($payment_status->seller)->name;
        if ($payment_status->payment_status == 'pending') {
            $new_status = 'complete';
        } else {
            $new_status = 'pending';
        }
        Order::where('id', $id)->update(['payment_status' => $new_status, 'status' => 1]);

        //if order created from job post start
        if ($payment_status->order_from_job == 'yes') {
            JobRequest::where('seller_id', $payment_status->seller_id)
                ->where('job_post_id', $payment_status->job_post_id)
                ->update(['is_hired' => 1]);
        }
        //if order created from job post end

        try {
            $message = get_static_option('admin_change_payment_status_message') ?? '';
            $message = str_replace(["@name", "@old_status", "@new_status", "@order_id"], [$payment_status->name, $old_status, $new_status, $payment_status->id], $message);
            Mail::to($payment_status->email)->queue(new BasicMail([
                'subject' => get_static_option('admin_change_payment_status_subject') ?? __('Payment Status Changed.'),
                'message' => $message
            ]));

            $message = get_static_option('admin_change_payment_status_message') ?? '';
            $message = str_replace(["@name", "@old_status", "@new_status", "@order_id"], [$seller_name, $old_status, $new_status, $payment_status->id], $message);
            Mail::to($seller_email)->queue(new BasicMail([
                'subject' => get_static_option('admin_change_payment_status_subject') ?? __('Payment Status Changed.'),
                'message' => $message
            ]));

            $smsService = new SMSService();
            $order_details = Order::find($id);
            $seller_phone = User::select('phone')->where('id', $order_details->seller_id)->first();
            $buyer_phone = User::select('phone')->where('id', $order_details->buyer_id)->first();
            $message = __('admin_change_payment_status_message') . __(' and Order ID:') . $id;
            //send sms to buyer
            $buyer_phone= $buyer_phone->phone;
            $smsService->send_sms($buyer_phone,  $message);
            //send sms to seller
            $seller_phone= $seller_phone->phone;
            $smsService->send_sms($seller_phone,  $message);



            //$smsService->send_sms($number,  $message);
            //$smsService->send_sms($number,  $message);

            $admins = Admin::all();
            foreach ($admins as $admin)  // Send SMS to all super admin
            {

                if ($admin->role == "Super Admin") {
                    $smsService->send_sms($admin->phone,  $message);
                    //smsService->send_sms($number,  $message);
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->with(FlashMsg::item_new($e->getMessage()));
        }

        return redirect()->back()->with(FlashMsg::item_new('Status Change Success'));
    }

    public function extra_orders()
    {
        $extra_services = [];
        ExtraService::chunk(100, function ($extra_q) use (&$extra_services) {
            foreach ($extra_q as $extra) {
                $extra_services[] = $extra;
            }
        });

        return view('backend.pages.orders.extra_orders', compact('extra_services'));
    }

    public function complete_payment_status($id)
    {
        $extra_order = ExtraService::select('id', 'payment_status')->where('id', $id)->first();
        $extra_order->payment_status == 'pending' ? $payment_status = 'complete' : '';
        ExtraService::where('id', $id)->update(['payment_status' => $payment_status]);
        return redirect()->back()->with(FlashMsg::item_new('Status Change Success'));
    }

    public function orderRequestDeclineHistory()
    {
        $decline_histories = OrderCompleteDecline::latest()->get();
        return view('backend.pages.orders.decline_history', compact('decline_histories'));
    }
}
