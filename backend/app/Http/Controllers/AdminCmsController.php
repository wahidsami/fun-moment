<?php

namespace App\Http\Controllers;

use App\Blog;
use App\Category;
use App\MediaUpload;
use App\Menu;
use App\Page;
use App\Widgets;
use App\Actions\Media\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AdminCmsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage_cms', ['only' => [
            'apiInventory',
            'apiUpdateContentStatus',
            'apiStoreBlog',
            'apiUpdateBlog',
            'apiDeleteBlog',
            'apiStorePage',
            'apiUpdatePage',
            'apiDeletePage',
            'apiStoreMenu',
            'apiUpdateMenu',
            'apiDeleteMenu',
            'apiDefaultMenu',
            'apiStoreWidget',
            'apiUpdateWidget',
            'apiDeleteWidget',
            'apiReorderWidget',
            'apiUploadMedia',
            'apiDeleteMedia',
            'apiUpdateMediaAlt',
        ]]);
    }

    public function apiInventory(): JsonResponse
    {
        $blogs = Blog::with('category')
            ->orderByDesc('id')
            ->take(50)
            ->get()
            ->map(fn (Blog $blog) => $this->formatBlogPayload($blog))
            ->values();

        $pages = Page::orderByDesc('id')
            ->take(50)
            ->get()
            ->map(fn (Page $page) => $this->formatPagePayload($page))
            ->values();

        $widgets = Widgets::orderBy('widget_order')
            ->take(50)
            ->get()
            ->map(fn (Widgets $widget) => $this->formatWidgetPayload($widget))
            ->values();

        $menus = Menu::orderByDesc('id')
            ->take(50)
            ->get()
            ->map(fn (Menu $menu) => $this->formatMenuPayload($menu))
            ->values();

        $media = MediaUpload::where('type', 'admin')
            ->orderByDesc('id')
            ->take(50)
            ->get()
            ->map(fn (MediaUpload $item) => $this->formatMediaPayload($item))
            ->values();

        return response()->json([
            'status' => 'success',
            'blogs' => $blogs,
            'pages' => $pages,
            'widgets' => $widgets,
            'menus' => $menus,
            'media' => $media,
            'summary' => [
                'blogs' => $blogs->count(),
                'pages' => $pages->count(),
                'widgets' => $widgets->count(),
                'menus' => $menus->count(),
                'media' => $media->count(),
            ],
        ]);
    }

    public function apiUpdateContentStatus(Request $request, string $type, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|max:191',
        ]);

        if ($type === 'blog') {
            $item = Blog::findOrFail($id);
            $item->status = $validated['status'] === 'published' ? 'publish' : $validated['status'];
            $item->save();

            return response()->json([
                'status' => 'success',
                'message' => __('Blog status updated successfully'),
                'item' => $this->formatBlogPayload($item->fresh('category')),
            ]);
        }

        if ($type === 'page') {
            $item = Page::findOrFail($id);
            $item->status = $validated['status'] === 'published' ? 'publish' : $validated['status'];
            $item->save();

            return response()->json([
                'status' => 'success',
                'message' => __('Page status updated successfully'),
                'item' => $this->formatPagePayload($item->fresh()),
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => __('Unsupported CMS item type'),
        ], 422);
    }

    public function apiStoreBlog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title_en' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'status' => 'required|in:published,draft',
            'image' => 'nullable|string|max:2048',
            'content_en' => 'nullable|string',
            'content_ar' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        $blog = new Blog();
        $blog->title = $validated['title_en'];
        $blog->title_ar = $validated['title_ar'];
        $blog->slug = Str::slug($validated['slug']);
        $blog->blog_content = $validated['content_en'] ?? '';
        $blog->blog_content_ar = $validated['content_ar'] ?? '';
        $blog->image = $validated['image'] ?? '';
        $blog->status = $validated['status'] === 'published' ? 'publish' : 'draft';
        $blog->category_id = $request->integer('category_id') ?: optional(Category::first())->id;
        $blog->created_by = 'admin';
        $blog->admin_id = auth('admin')->id();
        $blog->tag_name = !empty($validated['tags']) ? implode(',', $validated['tags']) : '';
        $blog->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Blog created successfully'),
            'item' => $this->formatBlogPayload($blog->fresh('category')),
        ]);
    }

    public function apiUpdateBlog(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title_en' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'status' => 'required|in:published,draft',
            'image' => 'nullable|string|max:2048',
            'content_en' => 'nullable|string',
            'content_ar' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        $blog = Blog::findOrFail($id);
        $blog->title = $validated['title_en'];
        $blog->title_ar = $validated['title_ar'];
        $blog->slug = Str::slug($validated['slug']);
        $blog->blog_content = $validated['content_en'] ?? '';
        $blog->blog_content_ar = $validated['content_ar'] ?? $blog->blog_content_ar;
        $blog->image = $validated['image'] ?? $blog->image;
        $blog->status = $validated['status'] === 'published' ? 'publish' : 'draft';
        $blog->category_id = $request->integer('category_id') ?: ($blog->category_id ?? optional(Category::first())->id);
        $blog->created_by = $blog->created_by ?? 'admin';
        $blog->admin_id = $blog->admin_id ?? auth('admin')->id();
        $blog->tag_name = !empty($validated['tags']) ? implode(',', $validated['tags']) : '';
        $blog->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Blog updated successfully'),
            'item' => $this->formatBlogPayload($blog->fresh('category')),
        ]);
    }

    public function apiDeleteBlog(int $id): JsonResponse
    {
        $blog = Blog::findOrFail($id);
        $blog->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Blog deleted successfully'),
        ]);
    }

    public function apiStorePage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title_en' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'status' => 'required|in:published,draft',
            'content_en' => 'nullable|string',
            'content_ar' => 'nullable|string',
        ]);

        $page = new Page();
        $page->title = $validated['title_en'];
        $page->title_ar = $validated['title_ar'];
        $page->slug = Str::slug($validated['slug']);
        $page->page_content = $validated['content_en'] ?? '';
        $page->page_content_ar = $validated['content_ar'] ?? '';
        $page->status = $validated['status'] === 'published' ? 'publish' : 'draft';
        $page->visibility = $request->input('visibility', 'public');
        $page->page_builder_status = $request->input('page_builder_status', 'disabled');
        $page->layout = $request->input('layout');
        $page->sidebar_layout = $request->input('sidebar_layout');
        $page->page_class = $request->input('page_class');
        $page->back_to_top = $request->input('back_to_top');
        $page->navbar_variant = $request->input('navbar_variant');
        $page->footer_variant = $request->input('footer_variant');
        $page->breadcrumb_status = $request->input('breadcrumb_status');
        $page->widget_style = $request->input('widget_style');
        $page->left_column = $request->input('left_column');
        $page->right_column = $request->input('right_column');
        $page->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Page created successfully'),
            'item' => $this->formatPagePayload($page->fresh()),
        ]);
    }

    public function apiUpdatePage(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title_en' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'status' => 'required|in:published,draft',
            'content_en' => 'nullable|string',
            'content_ar' => 'nullable|string',
        ]);

        $page = Page::findOrFail($id);
        $page->title = $validated['title_en'];
        $page->title_ar = $validated['title_ar'];
        $page->slug = Str::slug($validated['slug']);
        $page->page_content = $validated['content_en'] ?? $page->page_content;
        $page->page_content_ar = $validated['content_ar'] ?? $page->page_content_ar;
        $page->status = $validated['status'] === 'published' ? 'publish' : 'draft';
        $page->visibility = $request->input('visibility', $page->visibility);
        $page->page_builder_status = $request->input('page_builder_status', $page->page_builder_status);
        $page->layout = $request->input('layout', $page->layout);
        $page->sidebar_layout = $request->input('sidebar_layout', $page->sidebar_layout);
        $page->page_class = $request->input('page_class', $page->page_class);
        $page->back_to_top = $request->input('back_to_top', $page->back_to_top);
        $page->navbar_variant = $request->input('navbar_variant', $page->navbar_variant);
        $page->footer_variant = $request->input('footer_variant', $page->footer_variant);
        $page->breadcrumb_status = $request->input('breadcrumb_status', $page->breadcrumb_status);
        $page->widget_style = $request->input('widget_style', $page->widget_style);
        $page->left_column = $request->input('left_column', $page->left_column);
        $page->right_column = $request->input('right_column', $page->right_column);
        $page->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Page updated successfully'),
            'item' => $this->formatPagePayload($page->fresh()),
        ]);
    }

    public function apiDeletePage(int $id): JsonResponse
    {
        $page = Page::findOrFail($id);
        $page->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Page deleted successfully'),
        ]);
    }

    public function apiStoreMenu(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'label_ar' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
        ]);

        $menuNode = [
            'ptype' => 'custom',
            'pname' => $validated['title'],
            'purl' => $validated['content'] ?? '',
            'menulabel' => $validated['label_ar'] ?? $validated['title'],
            'antarget' => '',
        ];

        $menu = Menu::create([
            'title' => $validated['title'],
            'title_ar' => $validated['label_ar'] ?? $validated['title'],
            'content' => json_encode([$menuNode], JSON_UNESCAPED_UNICODE),
            'status' => $validated['status'] ?? '',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Menu created successfully'),
            'item' => $this->formatMenuPayload($menu),
        ]);
    }

    public function apiUpdateMenu(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'label_ar' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
        ]);

        $menuNode = [
            'ptype' => 'custom',
            'pname' => $validated['title'],
            'purl' => $validated['content'] ?? '',
            'menulabel' => $validated['label_ar'] ?? $validated['title'],
            'antarget' => '',
        ];

        $menu = Menu::findOrFail($id);
        $menu->title = $validated['title'];
        $menu->title_ar = $validated['label_ar'] ?? $menu->title_ar ?? $validated['title'];
        $menu->content = json_encode([$menuNode], JSON_UNESCAPED_UNICODE);
        $menu->status = $validated['status'] ?? $menu->status;
        $menu->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Menu updated successfully'),
            'item' => $this->formatMenuPayload($menu),
        ]);
    }

    public function apiDeleteMenu(int $id): JsonResponse
    {
        Menu::findOrFail($id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Menu deleted successfully'),
        ]);
    }

    public function apiDefaultMenu(int $id): JsonResponse
    {
        Menu::where(['status' => 'default'])->update(['status' => '']);
        $menu = Menu::findOrFail($id);
        $menu->status = 'default';
        $menu->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Default menu updated successfully'),
            'item' => $this->formatMenuPayload($menu),
        ]);
    }

    public function apiStoreWidget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'widget_name' => 'required|string|max:255',
            'widget_order' => 'required|integer',
            'widget_location' => 'required|string|max:255',
        ]);

        $content = $request->except(['_token']);
        $widget = Widgets::create([
            'widget_name' => $validated['widget_name'],
            'widget_title_ar' => $content['widget_title_ar'] ?? '',
            'widget_order' => $validated['widget_order'],
            'widget_location' => $validated['widget_location'],
            'widget_content' => serialize($content),
            'widget_content_ar' => $content['widget_content_ar'] ?? '',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Widget created successfully'),
            'item' => $this->formatWidgetPayload($widget),
        ]);
    }

    public function apiUpdateWidget(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'widget_name' => 'required|string|max:255',
            'widget_order' => 'required|integer',
            'widget_location' => 'required|string|max:255',
        ]);

        $content = $request->except(['_token']);
        $widget = Widgets::findOrFail($id);
        $widget->update([
            'widget_name' => $validated['widget_name'],
            'widget_title_ar' => $content['widget_title_ar'] ?? ($widget->widget_title_ar ?? ''),
            'widget_order' => $validated['widget_order'],
            'widget_location' => $validated['widget_location'],
            'widget_content' => serialize($content),
            'widget_content_ar' => $content['widget_content_ar'] ?? ($widget->widget_content_ar ?? ''),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Widget updated successfully'),
            'item' => $this->formatWidgetPayload($widget->fresh()),
        ]);
    }

    public function apiDeleteWidget(int $id): JsonResponse
    {
        Widgets::findOrFail($id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Widget deleted successfully'),
        ]);
    }

    public function apiReorderWidget(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'widget_order' => 'required|integer',
        ]);

        $widget = Widgets::findOrFail($id);
        $widget->widget_order = $validated['widget_order'];
        $widget->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Widget order updated successfully'),
            'item' => $this->formatWidgetPayload($widget->fresh()),
        ]);
    }

    public function apiMediaInventory(): JsonResponse
    {
        $media = MediaUpload::where('type', 'admin')
            ->orderByDesc('id')
            ->take(100)
            ->get()
            ->map(fn (MediaUpload $item) => $this->formatMediaPayload($item))
            ->values();

        return response()->json([
            'status' => 'success',
            'media' => $media,
        ]);
    }

    public function apiUploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:jpg,jpeg,png,gif,webp|max:11000',
        ], [
            'file.max' => __('The file may not be greater than 10 Megabytes'),
        ]);

        MediaHelper::insert_media_image($request);
        $item = MediaUpload::where('type', 'admin')->orderByDesc('id')->first();

        return response()->json([
            'status' => 'success',
            'message' => __('Media uploaded successfully'),
            'item' => $item ? $this->formatMediaPayload($item) : null,
        ]);
    }

    public function apiDeleteMedia(int $id): JsonResponse
    {
        $media = MediaUpload::findOrFail($id);

        foreach (['', 'grid-', 'large-', 'semi-large-', 'thumb-'] as $prefix) {
            $path = public_path('assets/uploads/media-uploader/' . $prefix . $media->path);
            if (file_exists($path) && !is_dir($path)) {
                @unlink($path);
            }
        }

        $media->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Media deleted successfully'),
        ]);
    }

    public function apiUpdateMediaAlt(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'alt' => 'nullable|string|max:255',
        ]);

        $media = MediaUpload::findOrFail($id);
        $media->alt = $validated['alt'] ?? '';
        $media->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Media alt text updated successfully'),
            'item' => $this->formatMediaPayload($media->fresh()),
        ]);
    }

    private function formatBlogPayload(Blog $blog): array
    {
        $content = trim(strip_tags((string) $blog->blog_content));
        $contentAr = trim(strip_tags((string) ($blog->blog_content_ar ?? $blog->blog_content)));

        return [
            'id' => (int) $blog->id,
            'type' => 'blog',
            'title_en' => $blog->title ?? '',
            'title_ar' => $blog->title_ar ?? $blog->title ?? '',
            'slug' => $blog->slug ?? '',
            'category' => optional($blog->category)->name ?? __('General'),
            'tags' => array_values(array_filter(array_map('trim', explode(',', (string) $blog->tag_name)))),
            'status' => $this->normalizeBlogStatus($blog->status),
            'image' => !empty($blog->image) ? asset($blog->image) : '',
            'date' => optional($blog->created_at)->toDateString(),
            'updated_at' => optional($blog->updated_at)->toDateTimeString(),
            'content_en' => $content,
            'content_ar' => $contentAr,
        ];
    }

    private function formatPagePayload(Page $page): array
    {
        $content = trim(strip_tags((string) $page->page_content));
        $contentAr = trim(strip_tags((string) ($page->page_content_ar ?? $page->page_content)));

        return [
            'id' => (int) $page->id,
            'type' => 'page',
            'title_en' => $page->title ?? '',
            'title_ar' => $page->title_ar ?? $page->title ?? '',
            'slug' => $page->slug ?? '',
            'status' => $this->normalizeCmsStatus($page->status),
            'date' => optional($page->created_at)->toDateString(),
            'updated_at' => optional($page->updated_at)->toDateTimeString(),
            'content_en' => $content,
            'content_ar' => $contentAr,
            'blocks' => [
                [
                    'id' => 'page-' . $page->id . '-block-1',
                    'type' => 'text',
                    'title_en' => $page->title ?? '',
                    'title_ar' => $page->title_ar ?? $page->title ?? '',
                    'content_en' => $content,
                    'content_ar' => $contentAr,
                ],
            ],
        ];
    }

    private function formatWidgetPayload(Widgets $widget): array
    {
        $decoded = $this->maybeDecodeWidgetContent($widget->widget_content);
        $titleAr = $widget->widget_title_ar ?? $widget->widget_name ?? '';
        if (isset($decoded['widget_title_ar']) || isset($decoded['title_ar'])) {
            $titleAr = (string) ($decoded['widget_title_ar'] ?? $decoded['title_ar']);
        }

        return [
            'id' => (int) $widget->id,
            'type' => 'widget',
            'zone' => $widget->widget_location ?? $widget->widget_area ?? 'footer',
            'widget_name' => $widget->widget_name ?? '',
            'widget_order' => (int) ($widget->widget_order ?? 0),
            'title_en' => $widget->widget_name ?? '',
            'title_ar' => $titleAr,
            'content' => $decoded,
            'status' => 'published',
            'updated_at' => optional($widget->updated_at)->toDateTimeString(),
        ];
    }

    private function formatMenuPayload(Menu $menu): array
    {
        $decoded = $this->maybeDecodeMenuContent($menu->content);
        $menuUrl = $menu->content ?? '';
        $titleEn = $menu->title ?? '';
        $titleAr = $menu->title_ar ?? $menu->title ?? '';

        if (!empty($decoded)) {
            $node = isset($decoded[0]) && is_array($decoded[0]) ? $decoded[0] : $decoded;
            if (is_array($node)) {
                $menuUrl = (string) ($node['purl'] ?? $node['url'] ?? $menuUrl);
                $titleEn = (string) ($node['pname'] ?? $node['title_en'] ?? $titleEn);
                $titleAr = (string) ($node['menulabel'] ?? $node['title_ar'] ?? $titleAr);
            }
        }

        return [
            'id' => (int) $menu->id,
            'type' => 'menu',
            'title_en' => $titleEn,
            'title_ar' => $titleAr,
            'slug' => Str::slug($titleEn),
            'content' => $menu->content ?? '',
            'url' => $menuUrl,
            'status' => $menu->status === 'default' ? 'published' : 'draft',
            'updated_at' => optional($menu->updated_at)->toDateTimeString(),
        ];
    }

    private function formatMediaPayload(MediaUpload $item): array
    {
        $assetUrl = asset('assets/uploads/media-uploader/' . $item->path);

        return [
            'id' => (int) $item->id,
            'type' => 'media',
            'title_en' => $item->title ?? '',
            'title_ar' => $item->title ?? '',
            'slug' => $item->path ?? '',
            'status' => 'published',
            'updated_at' => optional($item->created_at)->toDateTimeString(),
            'image' => $assetUrl,
            'url' => $assetUrl,
            'size' => $item->size ?? '',
            'dimensions' => $item->dimensions ?? '',
            'alt_en' => $item->alt ?? '',
            'alt_ar' => $item->alt ?? '',
        ];
    }

    private function maybeDecodeWidgetContent($widgetContent): array
    {
        if (empty($widgetContent)) {
            return [];
        }

        if (is_array($widgetContent)) {
            return $widgetContent;
        }

        $decoded = @unserialize($widgetContent);
        if (is_array($decoded)) {
            return $decoded;
        }

        $json = json_decode($widgetContent, true);
        return is_array($json) ? $json : [];
    }

    private function maybeDecodeMenuContent($menuContent): array
    {
        if (empty($menuContent)) {
            return [];
        }

        $json = json_decode($menuContent, true);
        return is_array($json) ? $json : [];
    }

    private function normalizeBlogStatus($status): string
    {
        return in_array($status, ['publish', 'published', 'active', 1, '1'], true) ? 'published' : 'draft';
    }

    private function normalizeCmsStatus($status): string
    {
        return in_array($status, ['publish', 'published', 'active', 1, '1'], true) ? 'published' : 'draft';
    }
}
