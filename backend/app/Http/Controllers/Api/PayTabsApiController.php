<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Order;
use App\Services\Payment\PayTabsPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PayTabsApiController extends Controller
{
    /**
     * Initiate PayTabs checkout session for an order.
     */
    public function initiate(Request $request, PayTabsPaymentService $payTabsService): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|integer',
        ]);

        $order = Order::find($request->order_id);
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => __('Order not found.')
            ], 404);
        }

        $userId = Auth::guard('sanctum')->id();
        if ($order->buyer_id !== null && $order->buyer_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthorized action for this order.')
            ], 403);
        }

        if ($order->payment_status === 'complete') {
            return response()->json([
                'success' => false,
                'message' => __('Order is already paid.'),
                'already_paid' => true,
                'order_id' => $order->id,
            ], 400);
        }

        $returnUrl = route('api.paytabs.return', ['order_id' => $order->id]);
        $callbackUrl = route('api.paytabs.ipn');

        $result = $payTabsService->initiatePayment($order, $returnUrl, $callbackUrl);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'redirect_url' => $result['redirect_url'],
                'tran_ref' => $result['tran_ref'] ?? null,
                'order_id' => $order->id,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? __('Failed to initialize PayTabs session.')
        ], 422);
    }

    /**
     * Authenticated endpoint called by mobile app after WebView completes to verify transaction.
     */
    public function verify(Request $request, PayTabsPaymentService $payTabsService): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|integer',
            'tran_ref' => 'required|string|max:191',
        ]);

        $order = Order::find($request->order_id);
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => __('Order not found.')
            ], 404);
        }

        $userId = Auth::guard('sanctum')->id();
        if ($order->buyer_id !== null && $order->buyer_id !== $userId) {
            Log::warning('[PayTabs] Unauthorized verification attempt', [
                'auth_user_id' => $userId,
                'order_id' => $order->id,
                'order_buyer_id' => $order->buyer_id,
            ]);
            return response()->json([
                'success' => false,
                'message' => __('Unauthorized action for this order.')
            ], 403);
        }

        // Fast-path if order was already completed by IPN
        if ($order->payment_status === 'complete' && $order->transaction_id === $request->tran_ref) {
            return response()->json([
                'success' => true,
                'payment_status' => 'complete',
                'order_id' => $order->id,
                'already_verified' => true,
                'message' => __('Payment was already confirmed.')
            ]);
        }

        // Perform server-authoritative PayTabs transaction query
        $verification = $payTabsService->verifyOrderPayment($order, $request->tran_ref);

        if (!$verification['verified']) {
            return response()->json([
                'success' => false,
                'payment_status' => 'failed',
                'reason' => $verification['reason'] ?? 'UNKNOWN',
                'message' => $verification['message'] ?? __('Transaction verification failed.')
            ], 422);
        }

        // Apply state mutation idempotently
        $applyResult = $payTabsService->applySuccessfulPayment(
            $order,
            $verification['tran_ref'],
            $verification['sanitized_details']
        );

        return response()->json([
            'success' => true,
            'payment_status' => 'complete',
            'order_id' => $order->id,
            'already_completed' => $applyResult['already_completed'],
            'message' => __('Payment successfully verified.')
        ]);
    }

    /**
     * Server-to-Server IPN Webhook from PayTabs.
     */
    public function ipn(Request $request, PayTabsPaymentService $payTabsService): JsonResponse
    {
        $rawPayload = (string) $request->getContent();
        $signatureHeader = $request->header('Signature') ?: $request->input('signature');

        Log::info('[PayTabs IPN] Received webhook notification');

        // Signature validation
        if (!$payTabsService->isValidIpnSignature($rawPayload, $signatureHeader)) {
            Log::warning('[PayTabs IPN] Rejected invalid signature on webhook', [
                'ip' => $request->ip(),
                'header' => $signatureHeader
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $data = $request->all();
        $tranRef = $data['tran_ref'] ?? null;
        $cartId = $data['cart_id'] ?? null;

        if (!$tranRef || !$cartId) {
            Log::warning('[PayTabs IPN] Missing tran_ref or cart_id in payload');
            return response()->json(['error' => 'Incomplete payload'], 400);
        }

        // Extract order ID
        $orderId = (int) $cartId;
        if (strlen((string) $cartId) > 10 && ctype_digit((string) $cartId)) {
            $orderId = (int) substr((string) $cartId, 5, -5);
        }

        $order = Order::find($orderId);
        if (!$order) {
            Log::error("[PayTabs IPN] Order not found for cart_id: {$cartId}");
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Secondary server check with PayTabs query API
        $verification = $payTabsService->verifyOrderPayment($order, $tranRef);
        if ($verification['verified']) {
            $payTabsService->applySuccessfulPayment(
                $order,
                $verification['tran_ref'],
                $verification['sanitized_details']
            );
            return response()->json(['status' => 'success', 'order_id' => $order->id]);
        }

        Log::warning('[PayTabs IPN] Verification unconfirmed for order', [
            'order_id' => $order->id,
            'reason' => $verification['reason'] ?? 'NOT_VERIFIED'
        ]);

        return response()->json(['status' => 'unverified', 'reason' => $verification['reason'] ?? 'unverified']);
    }

    /**
     * Web return page after PayTabs checkout.
     */
    public function returnPage(Request $request, $order_id)
    {
        $tranRef = $request->query('tranRef') ?: $request->input('tran_ref') ?: '';
        $respStatus = $request->query('respStatus') ?: $request->input('respStatus') ?: '';
        $respMessage = $request->query('respMessage') ?: $request->input('respMessage') ?: '';

        return response(
            '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Payment Result</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0b0716; color: #fff; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
                    .card { background: #160e29; border: 1px solid #332252; padding: 32px; border-radius: 16px; max-width: 380px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
                    .status-icon { font-size: 48px; margin-bottom: 16px; }
                    h2 { margin: 0 0 12px 0; color: #f43f5e; font-size: 20px; }
                    p { color: #a19dae; font-size: 14px; margin: 0 0 20px 0; }
                    .details { background: #0b0716; border-radius: 8px; padding: 12px; font-family: monospace; font-size: 12px; color: #d1cddb; word-break: break-all; margin-bottom: 20px; }
                    .btn { background: linear-gradient(135deg, #e11d48, #be123c); color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: bold; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class="card">
                    <div class="status-icon">' . ($respStatus === 'A' ? '✅' : 'ℹ️') . '</div>
                    <h2>' . ($respStatus === 'A' ? 'Payment Authorized' : 'Processing Payment') . '</h2>
                    <p>' . htmlspecialchars($respMessage ?: 'Please wait while the app confirms your order...') . '</p>
                    <div class="details">
                        Order #: ' . (int) $order_id . '<br>
                        Ref: <span id="tranRef">' . htmlspecialchars($tranRef) . '</span><br>
                        Status: <span id="respStatus">' . htmlspecialchars($respStatus) . '</span>
                    </div>
                </div>
            </body>
            </html>',
            200,
            ['Content-Type' => 'text/html']
        );
    }
}
