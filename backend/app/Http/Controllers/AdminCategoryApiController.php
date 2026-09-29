<?php

namespace App\Http\Controllers;

use App\AdminAuditLog;
use App\Category;
use App\Subcategory;
use App\ChildCategory;
use App\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminCategoryApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Resolve media ID to absolute public URL using existing Media Vault helper.
     */
    protected function resolveMediaUrl($mediaId): ?string
    {
        if (empty($mediaId)) {
            return null;
        }

        if (is_numeric($mediaId)) {
            $attachment = get_attachment_image_by_id((int) $mediaId);
            if (!empty($attachment['img_url'])) {
                return $attachment['img_url'];
            }
        }

        if (is_string($mediaId) && (str_starts_with($mediaId, 'http://') || str_starts_with($mediaId, 'https://'))) {
            return $mediaId;
        }

        return null;
    }

    public function apiCategories(): JsonResponse
    {
        $categories = Category::with(['subcategories.childcategories'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (Category $cat) {
                return [
                    'id' => $cat->id,
                    'nameEn' => $cat->name,
                    'nameAr' => $cat->name,
                    'slug' => $cat->slug ?: Str::slug($cat->name),
                    'status' => (int) $cat->status === 1 ? 'active' : 'inactive',
                    'sortOrder' => (int) ($cat->sort_order ?? 0),
                    'icon' => $cat->icon,
                    'image' => $cat->image,
                    'imageUrl' => $this->resolveMediaUrl($cat->image),
                    'mobileIcon' => $cat->mobile_icon,
                    'mobileIconUrl' => $this->resolveMediaUrl($cat->mobile_icon),
                    'servicesCount' => Service::where('category_id', $cat->id)->count(),
                    'subcategories' => $cat->subcategories->map(function (Subcategory $sub) {
                        return [
                            'id' => $sub->id,
                            'parentId' => (int) $sub->category_id,
                            'nameEn' => $sub->name,
                            'nameAr' => $sub->name,
                            'slug' => $sub->slug ?: Str::slug($sub->name),
                            'status' => (int) $sub->status === 1 ? 'active' : 'inactive',
                            'image' => $sub->image,
                            'imageUrl' => $this->resolveMediaUrl($sub->image),
                            'servicesCount' => Service::where('subcategory_id', $sub->id)->count(),
                            'childCategories' => $sub->childcategories->map(function (ChildCategory $child) {
                                return [
                                    'id' => $child->id,
                                    'parentId' => (int) $child->sub_category_id,
                                    'nameEn' => $child->name,
                                    'nameAr' => $child->name,
                                    'slug' => $child->slug ?: Str::slug($child->name),
                                    'status' => (int) $child->status === 1 ? 'active' : 'inactive',
                                    'image' => $child->image,
                                    'imageUrl' => $this->resolveMediaUrl($child->image),
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
            'status' => 'nullable|in:active,inactive,0,1',
            'sort_order' => 'nullable|integer',
            'mobile_icon' => 'nullable|integer|exists:media_uploads,id',
            'image' => 'nullable|integer|exists:media_uploads,id',
            'icon' => 'nullable|string|max:191',
        ]);

        $name = trim($validated['name_en']);
        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($name);
        $status = 1;
        if (isset($validated['status'])) {
            $status = ($validated['status'] === 'inactive' || $validated['status'] === '0' || $validated['status'] === 0) ? 0 : 1;
        }

        if ($validated['level'] === 'parent') {
            if (Category::where('name', $name)->exists()) {
                return response()->json(['status' => 'error', 'message' => __('Category already exists.')], 422);
            }

            $cat = Category::create([
                'name' => $name,
                'slug' => $slug,
                'status' => $status,
                'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : 0,
                'mobile_icon' => $validated['mobile_icon'] ?? null,
                'image' => $validated['image'] ?? null,
                'icon' => !empty($validated['icon']) ? trim($validated['icon']) : null,
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
                    'status' => (int) $cat->status === 1 ? 'active' : 'inactive',
                    'sortOrder' => (int) ($cat->sort_order ?? 0),
                    'icon' => $cat->icon,
                    'image' => $cat->image,
                    'imageUrl' => $this->resolveMediaUrl($cat->image),
                    'mobileIcon' => $cat->mobile_icon,
                    'mobileIconUrl' => $this->resolveMediaUrl($cat->mobile_icon),
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
                'status' => $status,
                'image' => $validated['image'] ?? null,
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
                    'status' => (int) $sub->status === 1 ? 'active' : 'inactive',
                    'image' => $sub->image,
                    'imageUrl' => $this->resolveMediaUrl($sub->image),
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
            'status' => $status,
            'image' => $validated['image'] ?? null,
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
                'status' => (int) $child->status === 1 ? 'active' : 'inactive',
                'image' => $child->image,
                'imageUrl' => $this->resolveMediaUrl($child->image),
                'servicesCount' => 0,
            ],
        ]);
    }

    public function apiUpdateCategory(Request $request, string $level, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name_en' => 'required|string|max:191',
            'name_ar' => 'nullable|string|max:191',
            'slug' => 'nullable|string|max:191',
            'parent_id' => 'nullable|integer',
            'status' => 'nullable|in:active,inactive,0,1',
            'sort_order' => 'nullable|integer',
            'mobile_icon' => 'nullable|integer|exists:media_uploads,id',
            'image' => 'nullable|integer|exists:media_uploads,id',
            'icon' => 'nullable|string|max:191',
        ]);

        $name = trim($validated['name_en']);
        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($name);

        if ($level === 'parent') {
            $cat = Category::findOrFail($id);

            // Check name uniqueness if changed
            if ($cat->name !== $name && Category::where('name', $name)->where('id', '!=', $id)->exists()) {
                return response()->json(['status' => 'error', 'message' => __('Category name already in use.')], 422);
            }

            $updateData = [
                'name' => $name,
                'slug' => $slug,
            ];

            if ($request->has('status')) {
                $updateData['status'] = ($validated['status'] === 'inactive' || $validated['status'] === '0' || $validated['status'] === 0) ? 0 : 1;
            }

            if ($request->has('sort_order')) {
                $updateData['sort_order'] = (int) $validated['sort_order'];
            }

            // Explicit assignment or removal of mobile_icon
            if ($request->has('mobile_icon')) {
                $updateData['mobile_icon'] = !empty($validated['mobile_icon']) ? (int) $validated['mobile_icon'] : null;
            }

            // Explicit assignment or removal of web image
            if ($request->has('image')) {
                $updateData['image'] = !empty($validated['image']) ? (int) $validated['image'] : null;
            }

            // Explicit assignment or removal of icon
            if ($request->has('icon')) {
                $updateData['icon'] = !empty($validated['icon']) ? trim($validated['icon']) : null;
            }

            $cat->update($updateData);

            AdminAuditLog::record([
                'action' => 'category_update',
                'resource_type' => 'Category',
                'resource_id' => (string) $cat->id,
                'details_en' => "Updated category #{$cat->id} ({$cat->name})",
                'details_ar' => "تم تعديل التصنيف الرئيسي #{$cat->id} ({$cat->name})",
                'new_values' => $cat->toArray(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Category updated successfully.'),
                'node' => [
                    'id' => $cat->id,
                    'nameEn' => $cat->name,
                    'nameAr' => $cat->name,
                    'slug' => $cat->slug,
                    'status' => (int) $cat->status === 1 ? 'active' : 'inactive',
                    'sortOrder' => (int) ($cat->sort_order ?? 0),
                    'icon' => $cat->icon,
                    'image' => $cat->image,
                    'imageUrl' => $this->resolveMediaUrl($cat->image),
                    'mobileIcon' => $cat->mobile_icon,
                    'mobileIconUrl' => $this->resolveMediaUrl($cat->mobile_icon),
                    'servicesCount' => Service::where('category_id', $cat->id)->count(),
                ],
            ]);
        }

        if ($level === 'sub') {
            $sub = Subcategory::findOrFail($id);

            $updateData = [
                'name' => $name,
                'slug' => $slug,
            ];

            if (!empty($validated['parent_id'])) {
                Category::findOrFail($validated['parent_id']); // Ensure valid parent
                $updateData['category_id'] = (int) $validated['parent_id'];
            }

            if ($request->has('status')) {
                $updateData['status'] = ($validated['status'] === 'inactive' || $validated['status'] === '0' || $validated['status'] === 0) ? 0 : 1;
            }

            if ($request->has('image')) {
                $updateData['image'] = !empty($validated['image']) ? (int) $validated['image'] : null;
            }

            $sub->update($updateData);

            AdminAuditLog::record([
                'action' => 'subcategory_update',
                'resource_type' => 'Subcategory',
                'resource_id' => (string) $sub->id,
                'details_en' => "Updated subcategory #{$sub->id} ({$sub->name})",
                'details_ar' => "تم تعديل التصنيف الفرعي #{$sub->id} ({$sub->name})",
                'new_values' => $sub->toArray(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Subcategory updated successfully.'),
                'node' => [
                    'id' => $sub->id,
                    'parentId' => $sub->category_id,
                    'nameEn' => $sub->name,
                    'nameAr' => $sub->name,
                    'slug' => $sub->slug,
                    'status' => (int) $sub->status === 1 ? 'active' : 'inactive',
                    'image' => $sub->image,
                    'imageUrl' => $this->resolveMediaUrl($sub->image),
                    'servicesCount' => Service::where('subcategory_id', $sub->id)->count(),
                ],
            ]);
        }

        // Child category
        $child = ChildCategory::findOrFail($id);

        $updateData = [
            'name' => $name,
            'slug' => $slug,
        ];

        if (!empty($validated['parent_id'])) {
            $parentSub = Subcategory::findOrFail($validated['parent_id']);
            $updateData['category_id'] = $parentSub->category_id;
            $updateData['sub_category_id'] = $parentSub->id;
        }

        if ($request->has('status')) {
            $updateData['status'] = ($validated['status'] === 'inactive' || $validated['status'] === '0' || $validated['status'] === 0) ? 0 : 1;
        }

        if ($request->has('image')) {
            $updateData['image'] = !empty($validated['image']) ? (int) $validated['image'] : null;
        }

        $child->update($updateData);

        AdminAuditLog::record([
            'action' => 'child_category_update',
            'resource_type' => 'ChildCategory',
            'resource_id' => (string) $child->id,
            'details_en' => "Updated child category #{$child->id} ({$child->name})",
            'details_ar' => "تم تعديل التصنيف الفرعي الثالث #{$child->id} ({$child->name})",
            'new_values' => $child->toArray(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Child category updated successfully.'),
            'node' => [
                'id' => $child->id,
                'parentId' => $child->sub_category_id,
                'nameEn' => $child->name,
                'nameAr' => $child->name,
                'slug' => $child->slug,
                'status' => (int) $child->status === 1 ? 'active' : 'inactive',
                'image' => $child->image,
                'imageUrl' => $this->resolveMediaUrl($child->image),
                'servicesCount' => Service::where('child_category_id', $child->id)->count(),
            ],
        ]);
    }

    public function apiReorderCategories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => 'nullable|array',
            'orders.*.id' => 'required_with:orders|integer|exists:categories,id',
            'orders.*.sort_order' => 'required_with:orders|integer',
            'ordered_ids' => 'nullable|array',
            'ordered_ids.*' => 'integer|exists:categories,id',
        ]);

        if (empty($validated['orders']) && empty($validated['ordered_ids'])) {
            return response()->json(['status' => 'error', 'message' => __('Orders or ordered_ids required.')], 422);
        }

        DB::transaction(function () use ($validated) {
            if (!empty($validated['ordered_ids'])) {
                foreach ($validated['ordered_ids'] as $index => $catId) {
                    Category::where('id', $catId)->update(['sort_order' => $index]);
                }
            } elseif (!empty($validated['orders'])) {
                foreach ($validated['orders'] as $item) {
                    Category::where('id', $item['id'])->update(['sort_order' => (int) $item['sort_order']]);
                }
            }
        });

        AdminAuditLog::record([
            'action' => 'category_reorder',
            'resource_type' => 'Category',
            'resource_id' => 'batch',
            'details_en' => "Reordered root categories",
            'details_ar' => "تمت إعادة ترتيب التصنيفات الرئيسية",
            'new_values' => $validated,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Categories reordered successfully.'),
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

            // Safety check 1: child / subcategory dependencies
            $subCount = Subcategory::where('category_id', $id)->count();
            if ($subCount > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => __("Cannot delete category because it has :count subcategories linked to it. Please remove or reassign subcategories first.", ['count' => $subCount]),
                ], 422);
            }

            // Safety check 2: attached services
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

            // Safety check 1: child categories dependencies
            $childCount = ChildCategory::where('sub_category_id', $id)->count();
            if ($childCount > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => __("Cannot delete subcategory because it has :count child categories linked to it. Please remove or reassign child categories first.", ['count' => $childCount]),
                ], 422);
            }

            // Safety check 2: attached services
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

        // Safety check: attached services
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
