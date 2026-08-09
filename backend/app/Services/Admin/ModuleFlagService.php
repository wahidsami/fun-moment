<?php

namespace App\Services\Admin;

class ModuleFlagService
{
    private string $statusFile;

    public function __construct()
    {
        $this->statusFile = base_path('modules_statuses.json');
    }

    public function all(): array
    {
        $statusMap = $this->loadStatusMap();
        $registry = $this->registry();
        $modules = [];

        foreach ($registry as $moduleName => $meta) {
            $enabled = (bool)($statusMap[$moduleName] ?? false);
            $modules[] = $this->formatModule($moduleName, $enabled, $meta);
        }

        foreach ($statusMap as $moduleName => $enabled) {
            if (!array_key_exists($moduleName, $registry)) {
                $modules[] = $this->formatModule($moduleName, (bool) $enabled, [
                    'key' => strtolower($moduleName),
                    'label_en' => $moduleName,
                    'label_ar' => $moduleName,
                    'description_en' => 'Custom feature flag managed from the admin dashboard.',
                    'description_ar' => 'ميزة مخصصة يتم التحكم بها من لوحة التحكم.',
                    'icon' => 'settings',
                ]);
            }
        }

        usort($modules, static function (array $left, array $right) {
            return strcmp($left['module_name'], $right['module_name']);
        });

        return $modules;
    }

    public function get(string $moduleName): bool
    {
        $statusMap = $this->loadStatusMap();
        return (bool) ($statusMap[$moduleName] ?? false);
    }

    public function update(string $moduleName, bool $enabled): array
    {
        $statusMap = $this->loadStatusMap();
        $statusMap[$moduleName] = $enabled;
        $this->saveStatusMap($statusMap);

        return $this->formatModule($moduleName, $enabled, $this->registry()[$moduleName] ?? [
            'key' => strtolower($moduleName),
            'label_en' => $moduleName,
            'label_ar' => $moduleName,
            'description_en' => 'Custom feature flag managed from the admin dashboard.',
            'description_ar' => 'ميزة مخصصة يتم التحكم بها من لوحة التحكم.',
            'icon' => 'settings',
        ]);
    }

    public function resolveModuleName(string $identifier): ?string
    {
        $normalized = strtolower(trim($identifier));

        foreach ($this->registry() as $moduleName => $meta) {
            if (
                strtolower($moduleName) === $normalized ||
                strtolower($meta['key']) === $normalized
            ) {
                return $moduleName;
            }
        }

        return null;
    }

    public function payload(): array
    {
        $modules = $this->all();

        return [
            'modules' => $modules,
            'feature_flags' => collect($modules)->mapWithKeys(static function (array $module) {
                return [
                    $module['module_name'] => [
                        'enabled' => $module['enabled'],
                        'locked' => $module['locked'],
                    ],
                ];
            })->all(),
        ];
    }

    private function registry(): array
    {
        return [
            'Wallet' => [
                'key' => 'wallet',
                'label_en' => 'Wallet',
                'label_ar' => 'المحفظة',
                'description_en' => 'Unified escrow wallet, deposits, payout batches, and balance controls.',
                'description_ar' => 'المحفظة الموحدة، الإيداعات، دفعات السحب، وأدوات ضبط الأرصدة.',
                'icon' => 'wallet',
            ],
            'LiveChat' => [
                'key' => 'chat',
                'label_en' => 'Live Chat',
                'label_ar' => 'المحادثة المباشرة',
                'description_en' => 'Buyer and seller live messaging plus moderation controls.',
                'description_ar' => 'مراسلة مباشرة بين المشتري والبائع مع أدوات المراقبة.',
                'icon' => 'messages-square',
            ],
            'JobPost' => [
                'key' => 'jobs',
                'label_en' => 'Jobs',
                'label_ar' => 'الوظائف والمشاريع',
                'description_en' => 'Buyer job posts, requests, bids, and hiring workflows.',
                'description_ar' => 'طلبات المشاريع، العروض، وإجراءات التوظيف بين الأطراف.',
                'icon' => 'briefcase-business',
            ],
            'Subscription' => [
                'key' => 'subscription',
                'label_en' => 'Subscription',
                'label_ar' => 'الاشتراكات',
                'description_en' => 'Seller subscriptions, renewals, tiers, and package controls.',
                'description_ar' => 'اشتراكات البائعين، التجديد، الباقات، وأدوات التحكم بالمستويات.',
                'icon' => 'crown',
            ],
        ];
    }

    private function loadStatusMap(): array
    {
        if (!file_exists($this->statusFile)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->statusFile), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function saveStatusMap(array $statusMap): void
    {
        ksort($statusMap);
        file_put_contents(
            $this->statusFile,
            json_encode($statusMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    private function formatModule(string $moduleName, bool $enabled, array $meta): array
    {
        return [
            'module_name' => $moduleName,
            'key' => $meta['key'],
            'label_en' => $meta['label_en'],
            'label_ar' => $meta['label_ar'],
            'description_en' => $meta['description_en'],
            'description_ar' => $meta['description_ar'],
            'icon' => $meta['icon'],
            'enabled' => $enabled,
            'locked' => !$enabled,
        ];
    }
}
