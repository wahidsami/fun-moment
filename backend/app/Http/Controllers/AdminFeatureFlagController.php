<?php

namespace App\Http\Controllers;

use App\Services\Admin\ModuleFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFeatureFlagController extends Controller
{
    public function __construct(private readonly ModuleFlagService $moduleFlagService)
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message_en' => 'Feature flags loaded successfully.',
            'message_ar' => 'تم تحميل مفاتيح الميزات بنجاح.',
            'data' => $this->moduleFlagService->payload(),
            'meta' => [
                'can_manage' => $this->isSuperAdmin(),
            ],
        ]);
    }

    public function update(Request $request, string $module): JsonResponse
    {
        if (!$this->isSuperAdmin()) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $moduleName = $this->moduleFlagService->resolveModuleName($module);
        if ($moduleName === null) {
            return response()->json([
                'success' => false,
                'error_code' => 'MODULE_NOT_FOUND',
                'message_en' => 'The requested module could not be found.',
                'message_ar' => 'تعذر العثور على الإضافة المطلوبة.',
            ], 404);
        }

        $updatedModule = $this->moduleFlagService->update($moduleName, (bool) $validated['enabled']);

        return response()->json([
            'success' => true,
            'message_en' => sprintf('%s module updated successfully.', $moduleName),
            'message_ar' => sprintf('تم تحديث إضافة %s بنجاح.', $moduleName),
            'data' => [
                'module' => $updatedModule,
                'modules' => $this->moduleFlagService->all(),
            ],
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        if (!$this->isSuperAdmin()) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.module' => ['required', 'string'],
            'modules.*.enabled' => ['required', 'boolean'],
        ]);

        foreach ($validated['modules'] as $item) {
            $moduleName = $this->moduleFlagService->resolveModuleName($item['module']);
            if ($moduleName === null) {
                continue;
            }

            $this->moduleFlagService->update($moduleName, (bool) $item['enabled']);
        }

        return response()->json([
            'success' => true,
            'message_en' => 'Feature flags updated successfully.',
            'message_ar' => 'تم تحديث مفاتيح الميزات بنجاح.',
            'data' => $this->moduleFlagService->payload(),
        ]);
    }

    private function isSuperAdmin(): bool
    {
        $admin = auth('admin')->user();

        return $admin && method_exists($admin, 'hasRole') && $admin->hasRole('Super Admin');
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => 'FORBIDDEN',
            'message_en' => 'You are not allowed to manage feature flags.',
            'message_ar' => 'غير مسموح لك بإدارة مفاتيح الميزات.',
        ], 403);
    }
}
