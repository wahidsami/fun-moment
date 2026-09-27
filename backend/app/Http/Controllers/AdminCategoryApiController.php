<?php

namespace App\Http\Controllers;

use App\AdminAuditLog;
use App\Category;
use App\Subcategory;
use App\ChildCategory;
use App\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCategoryApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function apiCategories(): JsonResponse
    {
        $categories = Category::with(['subcategories.childcategories'])
            ->orderBy('id')
            ->get()
            ->map(function (Category $cat) {
                return [
                    'id' => $cat->id,
                    'nameEn' => $cat->name,
                    'nameAr' => $cat->name,
                    'slug' => $cat->slug ?: Str::slug($cat->name),
                    'status' => (int) $cat->status === 1 ? 'active' : 'inactive',
                    'servicesCount' => Service::where('category_id', $cat->id)->count(),
                    'subcategories' => $cat->subcategories->map(function (Subcategory $sub) {
                        return [
                            'id' => $sub->id,
                            'parentId' => (int) $sub->category_id,
                            'nameEn' => $sub->name,
                            'nameAr' => $sub->name,
                            'slug' => $sub->slug ?: Str::slug($sub->name),
                            'status' => (int) $sub->status === 1 ? 'active' : 'inactive',
                            'servicesCount' => Service::where('subcategory_id', $sub->id)->count(),
                            'childCategories' => $sub->childcategories->map(function (ChildCategory $child) {
                                return [
                                    'id' => $child->id,
                                    'parentId' => (int) $child->sub_category_id,
                                    'nameEn' => $child->name,
                                    'nameAr' => $child->name,
                                    'slug' => $child->slug ?: Str::slug($child->name),
                                    'status' => (int) $child->status === 1 ? 'active' : 'inactive',
                                    'servicesCount' => Service::where('child_category_id', $child->id)->count(),
                                ];
                            })->values(),
                        ];
                    })->values(),
                ];
            })->values();

        return response()->json([
            'status' => 'success',
            'categories' => $categories,
        ]);
    }

    public function apiCreateCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'level' => 'required|in:parent,sub,child',
            'name_en' => 'required|string|max:191',
            'name_ar' => 'nullable|string|max:191',
            'slug' => 'nullable|string|max:191',
            'parent_id' => 'nullable|integer',
        ]);

        $name = trim($validated['name_en']);
        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($name);

        if ($validated['level'] === 'parent') {
            if (Category::where('name', $name)->exists()) {
                return response()->json(['status' => 'error', 'message' => __('Category already exists.')], 422);
            }

            $cat = Category::create([
                'name' => $name,
                'slug' => $slug,
                'status' => 1,
            ]);

            AdminAuditLog::record([
                'action' => 'category_create',
                'resource_type' => 'Category',
                'resource_id' => (string) $cat->id,
                'details_en' => "Created root category '{$cat->name}'",
                'details_ar' => "تم إنشاء التصنيف الرئيسي '{$cat->name}'",
                'new_values' => $cat->toArray(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Category created successfully.'),
                'node' => [
                    'id' => $cat->id,
                    'nameEn' => $cat->name,
                    'nameAr' => $cat->name,
                    'slug' => $cat->slug,
                    'status' => 'active',
                    'servicesCount' => 0,
                    'subcategories' => [],
                ],
            ]);
        }

        if ($validated['level'] === 'sub') {
            if (empty($validated['parent_id'])) {
                return response()->json(['status' => 'error', 'message' => __('Parent category is required.')], 422);
            }

            $parentCat = Category::findOrFail($validated['parent_id']);
            $sub = Subcategory::create([
                'category_id' => $parentCat->id,
                'name' => $name,
                'slug' => $slug,
                'status' => 1,
            ]);

            AdminAuditLog::record([
                'action' => 'subcategory_create',
                'resource_type' => 'Subcategory',
                'resource_id' => (string) $sub->id,
                'details_en' => "Created subcategory '{$sub->name}' under category #{$parentCat->id}",
                'details_ar' => "تم إنشاء التصنيف الفرعي '{$sub->name}' تحت التصنيف #{$parentCat->id}",
                'new_values' => $sub->toArray(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Subcategory created successfully.'),
                'node' => [
                    'id' => $sub->id,
                    'parentId' => $sub->category_id,
                    'nameEn' => $sub->name,
                    'nameAr' => $sub->name,
                    'slug' => $sub->slug,
                    'status' => 'active',
                    'servicesCount' => 0,
                    'childCategories' => [],
                ],
            ]);
        }

        // Child category
        if (empty($validated['parent_id'])) {
            return response()->json(['status' => 'error', 'message' => __('Parent subcategory is required.')], 422);
        }

        $parentSub = Subcategory::findOrFail($validated['parent_id']);
        $child = ChildCategory::create([
            'category_id' => $parentSub->category_id,
            'sub_category_id' => $parentSub->id,
            'name' => $name,
            'slug' => $slug,
            'status' => 1,
        ]);

        AdminAuditLog::record([
            'action' => 'child_category_create',
            'resource_type' => 'ChildCategory',
            'resource_id' => (string) $child->id,
            'details_en' => "Created child category '{$child->name}' under subcategory #{$parentSub->id}",
            'details_ar' => "تم إنشاء التصنيف الفرعي الثالث '{$child->name}' تحت التصنيف #{$parentSub->id}",
            'new_values' => $child->toArray(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Child category created successfully.'),
            'node' => [
                'id' => $child->id,
                'parentId' => $child->sub_category_id,
                'nameEn' => $child->name,
                'nameAr' => $child->name,
                'slug' => $child->slug,
                'status' => 'active',
                'servicesCount' => 0,
            ],
        ]);
    }

    public function apiUpdateCategoryStatus(Request $request, string $level, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:active,inactive',
        ]);

        $statusValue = null;
        if (isset($validated['status'])) {
            $statusValue = $validated['status'] === 'active' ? 1 : 0;
        }

        if ($level === 'parent') {
            $cat = Category::findOrFail($id);
            $newStatus = $statusValue !== null ? $statusValue : ($cat->status == 1 ? 0 : 1);
            $cat->update(['status' => $newStatus]);
            AdminAuditLog::record([
                'action' => 'category_status',
                'resource_type' => 'Category',
                'resource_id' => (string) $id,
                'details_en' => "Changed category #{$id} status to {$newStatus}",
                'details_ar' => "تم تغيير حالة التصنيف #{$id} إلى {$newStatus}",
            ]);
            return response()->json(['status' => 'success', 'message' => __('Status updated.'), 'new_status' => $newStatus == 1 ? 'active' : 'inactive']);
        }

        if ($level === 'sub') {
            $sub = Subcategory::findOrFail($id);
            $newStatus = $statusValue !== null ? $statusValue : ($sub->status == 1 ? 0 : 1);
            $sub->update(['status' => $newStatus]);
            AdminAuditLog::record([
                'action' => 'subcategory_status',
                'resource_type' => 'Subcategory',
                'resource_id' => (string) $id,
                'details_en' => "Changed subcategory #{$id} status to {$newStatus}",
                'details_ar' => "تم تغيير حالة التصنيف الفرعي #{$id} إلى {$newStatus}",
            ]);
            return response()->json(['status' => 'success', 'message' => __('Status updated.'), 'new_status' => $newStatus == 1 ? 'active' : 'inactive']);
        }

        $child = ChildCategory::findOrFail($id);
        $newStatus = $statusValue !== null ? $statusValue : ($child->status == 1 ? 0 : 1);
        $child->update(['status' => $newStatus]);
        AdminAuditLog::record([
            'action' => 'child_category_status',
            'resource_type' => 'ChildCategory',
            'resource_id' => (string) $id,
            'details_en' => "Changed child category #{$id} status to {$newStatus}",
            'details_ar' => "تم تغيير حالة التصنيف الفرعي #{$id} إلى {$newStatus}",
        ]);
        return response()->json(['status' => 'success', 'message' => __('Status updated.'), 'new_status' => $newStatus == 1 ? 'active' : 'inactive']);
    }

    public function apiDeleteCategory(Request $request, string $level, int $id): JsonResponse
    {
        if ($level === 'parent') {
            $cat = Category::findOrFail($id);
            $serviceCount = Service::where('category_id', $id)->count();
            if ($serviceCount > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => __("Cannot delete category because it has :count services linked to it.", ['count' => $serviceCount]),
                ], 422);
            }
            $cat->delete();
            AdminAuditLog::record([
                'action' => 'category_delete',
                'resource_type' => 'Category',
                'resource_id' => (string) $id,
                'details_en' => "Deleted category #{$id} ({$cat->name})",
                'details_ar' => "تم حذف التصنيف #{$id} ({$cat->name})",
            ]);
            return response()->json(['status' => 'success', 'message' => __('Category deleted successfully.')]);
        }

        if ($level === 'sub') {
            $sub = Subcategory::findOrFail($id);
            $serviceCount = Service::where('subcategory_id', $id)->count();
            if ($serviceCount > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => __("Cannot delete subcategory because it has :count services linked to it.", ['count' => $serviceCount]),
                ], 422);
            }
            $sub->delete();
            AdminAuditLog::record([
                'action' => 'subcategory_delete',
                'resource_type' => 'Subcategory',
                'resource_id' => (string) $id,
                'details_en' => "Deleted subcategory #{$id} ({$sub->name})",
                'details_ar' => "تم حذف التصنيف الفرعي #{$id} ({$sub->name})",
            ]);
            return response()->json(['status' => 'success', 'message' => __('Subcategory deleted successfully.')]);
        }

        $child = ChildCategory::findOrFail($id);
        $serviceCount = Service::where('child_category_id', $id)->count();
        if ($serviceCount > 0) {
            return response()->json([
                'status' => 'error',
                'message' => __("Cannot delete child category because it has :count services linked to it.", ['count' => $serviceCount]),
            ], 422);
        }
        $child->delete();
        AdminAuditLog::record([
            'action' => 'child_category_delete',
            'resource_type' => 'ChildCategory',
            'resource_id' => (string) $id,
            'details_en' => "Deleted child category #{$id} ({$child->name})",
            'details_ar' => "تم حذف التصنيف الفرعي الثالث #{$id} ({$child->name})",
        ]);
        return response()->json(['status' => 'success', 'message' => __('Child category deleted successfully.')]);
    }
}
