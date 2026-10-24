<?php

namespace App\Http\Controllers;

use App\Category;
use App\MediaUpload;
use App\Service;
use App\Slider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminSliderApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * List all sliders for the admin dashboard.
     */
    public function index(?Request $request = null): JsonResponse
    {
        $sliders = Slider::orderBy('order', 'asc')->orderBy('id', 'asc')->get()->map(function (Slider $slider) {
            return $this->formatSliderPayload($slider);
        });

        // Provide comprehensive lookup options for target selection in modal
        $searchService = $request ? $request->query('search_service') : null;
        $searchCategory = $request ? $request->query('search_category') : null;
        $services = $this->getEligibleServices($searchService);
        $categories = $this->getEligibleCategories($searchCategory);

        return response()->json([
            'status' => 'success',
            'sliders' => $sliders,
            'services' => $services,
            'categories' => $categories,
            'lookups' => [
                'services' => $services,
                'categories' => $categories,
            ],
            'counts' => [
                'total' => $sliders->count(),
                'active' => $sliders->where('status', 1)->count(),
                'inactive' => $sliders->where('status', 0)->count(),
                'services_count' => count($services),
                'categories_count' => count($categories),
            ],
        ]);
    }

    /**
     * Dedicated dynamic lookups endpoint with search support.
     */
    public function lookups(?Request $request = null): JsonResponse
    {
        $search = $request ? $request->query('search') : null;
        $type = $request ? $request->query('type') : null; // 'service', 'category', or null (both)

        $services = ($type === 'category') ? [] : $this->getEligibleServices($search);
        $categories = ($type === 'service') ? [] : $this->getEligibleCategories($search);

        return response()->json([
            'status' => 'success',
            'services' => $services,
            'categories' => $categories,
            'lookups' => [
                'services' => $services,
                'categories' => $categories,
            ],
            'counts' => [
                'services_count' => count($services),
                'categories_count' => count($categories),
            ],
        ]);
    }

    /**
     * Retrieve eligible marketplace services across all sellers.
     */
    public function getEligibleServices(?string $search = null): array
    {
        $query = Service::query()
            ->with([
                'seller' => function ($q) {
                    $q->select('id', 'name', 'username', 'email');
                },
                'category' => function ($q) {
                    $q->select('id', 'name', 'name_ar');
                }
            ])
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('is_service_on', 1)->orWhereNull('is_service_on');
            });

        if (!empty($search)) {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhereHas('seller', function ($sq) use ($term) {
                        $sq->where('name', 'like', "%{$term}%")
                            ->orWhere('username', 'like', "%{$term}%");
                    })
                    ->orWhereHas('category', function ($cq) use ($term) {
                        $cq->where('name', 'like', "%{$term}%")
                            ->orWhere('name_ar', 'like', "%{$term}%");
                    });

                if (is_numeric($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        return $query->orderBy('title', 'asc')
            ->get()
            ->map(function ($s) {
                $sellerName = $s->seller?->name ?: ($s->seller?->username ?: null);
                return [
                    'id' => (int) $s->id,
                    'title' => $s->title,
                    'seller_id' => $s->seller_id ? (int) $s->seller_id : null,
                    'seller_name' => $sellerName,
                    'category_id' => $s->category_id ? (int) $s->category_id : null,
                    'category_name' => $s->category?->name,
                    'category_name_ar' => $s->category?->name_ar,
                    'price' => $s->price !== null ? (float) $s->price : null,
                    'status' => (int) $s->status,
                    'is_service_on' => (int) ($s->is_service_on ?? 1),
                    'display_label' => $sellerName ? "{$s->title} ({$sellerName})" : $s->title,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Retrieve eligible customer-facing categories across marketplace.
     */
    public function getEligibleCategories(?string $search = null): array
    {
        $query = Category::query()
            ->select('id', 'name', 'name_ar', 'status', 'icon', 'sort_order')
            ->where('status', 1);

        if (!empty($search)) {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('name_ar', 'like', "%{$term}%");

                if (is_numeric($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        return $query->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($c) {
                $nameAr = $c->name_ar ?: $c->name;
                $displayLabel = ($nameAr && $nameAr !== $c->name)
                    ? "{$c->name} ({$nameAr})"
                    : $c->name;

                return [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                    'name_ar' => $nameAr,
                    'status' => (int) $c->status,
                    'display_label' => $displayLabel,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Store a new carousel slide.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'background_image' => 'required',
            'title' => 'required|string|max:191',
            'title_ar' => 'nullable|string|max:191',
            'sub_title' => 'nullable|string|max:191',
            'sub_title_ar' => 'nullable|string|max:191',
            'btn_text_en' => 'nullable|string|max:100',
            'btn_text_ar' => 'nullable|string|max:100',
            'action_type' => ['required', Rule::in(['none', 'service', 'category', 'url'])],
            'action_target' => 'nullable|string|max:500',
            'service_id' => 'nullable|integer',
            'order' => 'nullable|integer|min:0',
            'status' => 'nullable|in:0,1',
        ]);

        $this->validateActionTarget($validated['action_type'], $validated['action_target'] ?? null);

        // Derive service_id for backward compatibility if action is service
        $serviceId = null;
        if ($validated['action_type'] === 'service' && !empty($validated['action_target'])) {
            $serviceId = (int) $validated['action_target'];
        } elseif (!empty($request->service_id)) {
            $serviceId = (int) $request->service_id;
        }

        $nextOrder = $validated['order'] ?? (Slider::max('order') + 1);

        $slider = Slider::create([
            'background_image' => (string) $validated['background_image'],
            'title' => $validated['title'],
            'title_ar' => $validated['title_ar'] ?? null,
            'sub_title' => $validated['sub_title'] ?? '',
            'sub_title_ar' => $validated['sub_title_ar'] ?? null,
            'btn_text_en' => $validated['btn_text_en'] ?? null,
            'btn_text_ar' => $validated['btn_text_ar'] ?? null,
            'service_id' => $serviceId,
            'action_type' => $validated['action_type'],
            'action_target' => $validated['action_target'] ?? null,
            'order' => $nextOrder,
            'status' => isset($validated['status']) ? (int) $validated['status'] : 1,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Slide created successfully'),
            'slider' => $this->formatSliderPayload($slider),
        ], 201);
    }

    /**
     * Update an existing carousel slide.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $slider = Slider::findOrFail($id);

        $validated = $request->validate([
            'background_image' => 'nullable',
            'title' => 'required|string|max:191',
            'title_ar' => 'nullable|string|max:191',
            'sub_title' => 'nullable|string|max:191',
            'sub_title_ar' => 'nullable|string|max:191',
            'btn_text_en' => 'nullable|string|max:100',
            'btn_text_ar' => 'nullable|string|max:100',
            'action_type' => ['required', Rule::in(['none', 'service', 'category', 'url'])],
            'action_target' => 'nullable|string|max:500',
            'service_id' => 'nullable|integer',
            'order' => 'nullable|integer|min:0',
            'status' => 'nullable|in:0,1',
        ]);

        $this->validateActionTarget($validated['action_type'], $validated['action_target'] ?? null);

        $serviceId = null;
        if ($validated['action_type'] === 'service' && !empty($validated['action_target'])) {
            $serviceId = (int) $validated['action_target'];
        } elseif (!empty($request->service_id)) {
            $serviceId = (int) $request->service_id;
        }

        $slider->update([
            'background_image' => !empty($validated['background_image']) ? (string) $validated['background_image'] : $slider->background_image,
            'title' => $validated['title'],
            'title_ar' => $validated['title_ar'] ?? null,
            'sub_title' => $validated['sub_title'] ?? '',
            'sub_title_ar' => $validated['sub_title_ar'] ?? null,
            'btn_text_en' => $validated['btn_text_en'] ?? null,
            'btn_text_ar' => $validated['btn_text_ar'] ?? null,
            'service_id' => $serviceId,
            'action_type' => $validated['action_type'],
            'action_target' => $validated['action_target'] ?? null,
            'order' => isset($validated['order']) ? (int) $validated['order'] : $slider->order,
            'status' => isset($validated['status']) ? (int) $validated['status'] : $slider->status,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Slide updated successfully'),
            'slider' => $this->formatSliderPayload($slider->fresh()),
        ]);
    }

    /**
     * Toggle active/inactive status.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $slider = Slider::findOrFail($id);

        if ($request->has('status')) {
            $slider->status = (int) $request->input('status');
        } else {
            $slider->status = $slider->status === 1 ? 0 : 1;
        }

        $slider->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Slide status updated'),
            'slider' => $this->formatSliderPayload($slider),
        ]);
    }

    /**
     * Reorder slides deterministically.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ordered_ids' => 'required|array',
            'ordered_ids.*' => 'integer|exists:sliders,id',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['ordered_ids'] as $index => $id) {
                Slider::where('id', $id)->update(['order' => $index]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => __('Slides reordered successfully'),
        ]);
    }

    /**
     * Delete a slide safely (shared media files are NEVER deleted).
     */
    public function destroy($id): JsonResponse
    {
        $slider = Slider::findOrFail($id);
        $slider->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Slide deleted successfully'),
        ]);
    }

    /**
     * Validate action target according to action type.
     */
    protected function validateActionTarget(string $type, ?string $target): void
    {
        if ($type === 'none') {
            return;
        }

        if (empty($target)) {
            return;
        }

        if ($type === 'url') {
            if (!filter_var($target, FILTER_VALIDATE_URL) || (!str_starts_with($target, 'https://') && !str_starts_with($target, 'http://'))) {
                abort(422, __('The action target must be a valid HTTP or HTTPS URL'));
            }
        }

        if ($type === 'service') {
            if (!ctype_digit((string) $target) || !Service::where('id', (int) $target)->exists()) {
                abort(422, __('The selected service target does not exist'));
            }
        }

        if ($type === 'category') {
            if (!ctype_digit((string) $target) || !Category::where('id', (int) $target)->exists()) {
                abort(422, __('The selected category target does not exist'));
            }
        }
    }

    /**
     * Format slide object for clean JSON response.
     */
    protected function formatSliderPayload(Slider $slider): array
    {
        $attachment = $slider->getAttachmentMeta();
        $resolvedUrl = $slider->resolved_image_url;

        return [
            'id' => $slider->id,
            'background_image' => (string) $slider->background_image,
            'title' => $slider->title,
            'title_en' => $slider->title,
            'title_ar' => $slider->title_ar ?? '',
            'sub_title' => $slider->sub_title ?? '',
            'sub_title_en' => $slider->sub_title ?? '',
            'sub_title_ar' => $slider->sub_title_ar ?? '',
            'btn_text_en' => $slider->btn_text_en ?? '',
            'btn_text_ar' => $slider->btn_text_ar ?? '',
            'service_id' => $slider->service_id,
            'action_type' => $slider->action_type ?? ($slider->service_id ? 'service' : 'none'),
            'action_target' => $slider->action_target ?? ($slider->service_id ? (string) $slider->service_id : ''),
            'order' => $slider->order ?? 0,
            'status' => (int) ($slider->status ?? 1),
            'img_url' => $resolvedUrl,
            'attachment' => $attachment,
            'created_at' => $slider->created_at?->toISOString(),
            'updated_at' => $slider->updated_at?->toISOString(),
        ];
    }
}
