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
    public function index(): JsonResponse
    {
        $sliders = Slider::orderBy('order', 'asc')->orderBy('id', 'asc')->get()->map(function (Slider $slider) {
            return $this->formatSliderPayload($slider);
        });

        // Provide lookup options for target selection in modal
        $services = Service::select('id', 'title')->where('status', 1)->orderBy('title')->take(100)->get();
        $categories = Category::select('id', 'name')->where('status', 1)->orderBy('name')->take(100)->get();

        return response()->json([
            'status' => 'success',
            'sliders' => $sliders,
            'lookups' => [
                'services' => $services,
                'categories' => $categories,
            ],
            'counts' => [
                'total' => $sliders->count(),
                'active' => $sliders->where('status', 1)->count(),
                'inactive' => $sliders->where('status', 0)->count(),
            ],
        ]);
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
