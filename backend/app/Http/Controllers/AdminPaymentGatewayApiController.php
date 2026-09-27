<?php

namespace App\Http\Controllers;

use App\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPaymentGatewayApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function apiGetGateways(): JsonResponse
    {
        $rawKey = (string) get_static_option('paytabs_server_key');
        $hasKey = !empty(trim($rawKey));
        $maskedKey = $hasKey ? (strlen($rawKey) > 4 ? str_repeat('•', 12) . substr($rawKey, -4) : '••••••••') : '';

        return response()->json([
            'status' => 'success',
            'gateways' => [
                'paytabs' => [
                    'enabled' => in_array(get_static_option('paytabs_gateway'), ['on', '1', 1, true], true),
                    'region' => get_static_option('paytabs_region') ?: 'SAU',
                    'profile_id' => get_static_option('paytabs_profile_id') ?: '',
                    'has_server_key' => $hasKey,
                    'masked_server_key' => $maskedKey,
                    'test_mode' => in_array(get_static_option('paytabs_test_mode'), ['on', '1', 1, true], true),
                ],
                'manual_payment' => [
                    'enabled' => in_array(get_static_option('manual_payment_gateway'), ['on', '1', 1, true], true),
                ],
                'base_currency' => get_static_option('site_global_currency') ?: 'SAR',
            ],
        ]);
    }

    public function apiUpdateGateways(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paytabs_enabled' => 'nullable|boolean',
            'paytabs_region' => 'nullable|string|max:50',
            'paytabs_profile_id' => 'nullable|string|max:100',
            'paytabs_server_key' => 'nullable|string|max:255',
            'paytabs_test_mode' => 'nullable|boolean',
            'manual_payment_enabled' => 'nullable|boolean',
        ]);

        if ($request->has('paytabs_enabled')) {
            update_static_option('paytabs_gateway', $validated['paytabs_enabled'] ? 'on' : 'off');
        }

        if ($request->has('paytabs_region')) {
            update_static_option('paytabs_region', trim($validated['paytabs_region']));
        }

        if ($request->has('paytabs_profile_id')) {
            update_static_option('paytabs_profile_id', trim($validated['paytabs_profile_id']));
        }

        if (!empty($validated['paytabs_server_key']) && !str_contains($validated['paytabs_server_key'], '•')) {
            update_static_option('paytabs_server_key', trim($validated['paytabs_server_key']));
        }

        if ($request->has('paytabs_test_mode')) {
            update_static_option('paytabs_test_mode', $validated['paytabs_test_mode'] ? 'on' : 'off');
        }

        if ($request->has('manual_payment_enabled')) {
            update_static_option('manual_payment_gateway', $validated['manual_payment_enabled'] ? 'on' : 'off');
        }

        AdminAuditLog::record([
            'action' => 'payment_gateways_update',
            'resource_type' => 'PaymentGateway',
            'resource_id' => 'paytabs',
            'details_en' => "Updated PayTabs gateway parameters",
            'details_ar' => "تم تحديث إعدادات بوابة PayTabs",
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Payment gateway settings updated successfully.'),
            'gateways' => $this->apiGetGateways()->getData()->gateways,
        ]);
    }
}
