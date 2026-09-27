<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AdminAuditLog extends Model
{
    use HasFactory;

    protected $table = 'admin_audit_logs';

    protected $fillable = [
        'admin_id',
        'admin_name',
        'admin_email',
        'action',
        'resource_type',
        'resource_id',
        'details_en',
        'details_ar',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'status',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Record an administrative mutation in the immutable audit log.
     */
    public static function record(array $data): ?self
    {
        try {
            $admin = Auth::guard('admin')->user();

            $payload = array_merge([
                'admin_id' => optional($admin)->id,
                'admin_name' => optional($admin)->name ?? optional($admin)->username ?? 'System',
                'admin_email' => optional($admin)->email,
                'ip_address' => Request::ip() ?? '127.0.0.1',
                'user_agent' => substr(Request::userAgent() ?? 'API', 0, 500),
                'status' => 'success',
            ], $data);

            return self::create($payload);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to record admin audit log: ' . $e->getMessage());
            return null;
        }
    }
}
