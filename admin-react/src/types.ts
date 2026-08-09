/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

export type Language = 'en' | 'ar';
export type Direction = 'ltr' | 'rtl';

export type UserRole = 'super_admin' | 'moderator' | 'financial_manager' | 'support_agent';

export interface RoleConfig {
  id: UserRole;
  nameEn: string;
  nameAr: string;
  permissions: Permission[];
  descriptionEn: string;
  descriptionAr: string;
}

export type Permission =
  | 'manage_users'
  | 'manage_services'
  | 'manage_orders'
  | 'manage_payments'
  | 'manage_support'
  | 'manage_settings'
  | 'manage_cms'
  | 'view_analytics';

export type AddonModuleKey = 'wallet' | 'chat' | 'jobs' | 'subscription';

export interface AddonModule {
  key: AddonModuleKey;
  nameEn: string;
  nameAr: string;
  descriptionEn: string;
  descriptionAr: string;
  isLocked: boolean;
  icon: string;
}

export interface FeatureFlag {
  module_name: string;
  key: AddonModuleKey | string;
  label_en: string;
  label_ar: string;
  description_en: string;
  description_ar: string;
  icon: string;
  enabled: boolean;
  locked: boolean;
}

// Data models corresponding to Laravel database fields
export interface User {
  id: number;
  name: string;
  email: string;
  role: 'buyer' | 'seller' | 'admin';
  status: 'active' | 'suspended' | 'pending';
  avatar?: string;
  phone?: string;
  country: string;
  created_at: string;
  wallet_balance?: number;
}

export interface AdminAccount {
  id: number;
  name: string;
  email: string;
  role: string;
  role_key: string;
  status: 'active' | 'suspended' | 'pending';
  description?: string | null;
  designation?: string | null;
  last_login?: string | null;
  permissions: string[];
}

export interface AdminRoleRecord {
  id: number;
  name: string;
  key: string;
  guard_name: string;
  permissions: string[];
  ui_permissions: Record<Permission, boolean>;
}

export interface Service {
  id: number;
  title_en: string;
  title_ar: string;
  category_en: string;
  category_ar: string;
  seller_id: number;
  seller_name: string;
  price: number;
  duration: string;
  status: 'active' | 'pending' | 'suspended';
  rating: number;
  sales_count: number;
  created_at: string;
  description_en: string;
  description_ar: string;
}

export interface Order {
  id: number;
  service_id: number;
  service_title_en: string;
  service_title_ar: string;
  buyer_id: number;
  buyer_name: string;
  seller_id: number;
  seller_name: string;
  amount: number;
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
  created_at: string;
  payment_status: 'paid' | 'unpaid' | 'refunded';
}

export interface Payment {
  id: string; // e.g., txn_10248239
  order_id: number;
  user_name: string;
  amount: number;
  method: 'credit_card' | 'apple_pay' | 'stc_pay' | 'paypal' | 'wallet' | 'manual_payment' | 'cash_on_delivery';
  status: 'succeeded' | 'failed' | 'pending' | 'refunded';
  created_at: string;
  transaction_id?: string;
  payment_gateway?: string;
}

export interface SupportTicket {
  id: number;
  user_name: string;
  user_email: string;
  subject_en: string;
  subject_ar: string;
  category: 'payment' | 'service' | 'technical' | 'refund';
  priority: 'low' | 'medium' | 'high';
  status: 'open' | 'in_progress' | 'resolved' | 'closed';
  created_at: string;
  last_message?: string;
}

export interface CMSContent {
  id: number;
  type: 'banner' | 'faq' | 'page' | 'blog' | 'widget' | 'menu' | 'media';
  title_en: string;
  title_ar: string;
  slug?: string;
  status: 'published' | 'draft';
  updated_at: string;
  content_en?: string;
  content_ar?: string;
  category?: string;
  tags?: string[];
  image?: string;
  url?: string;
  zone?: 'sidebar' | 'footer';
  widget_name?: string;
  widget_order?: number;
  content?: string | Record<string, unknown> | unknown[];
  size?: string;
  dimensions?: string;
  alt_en?: string;
  alt_ar?: string;
  blocks?: Array<{
    id: string;
    type: 'hero' | 'text' | 'features' | 'cta';
    title_en: string;
    title_ar: string;
    content_en: string;
    content_ar: string;
  }>;
}

export interface OverviewStats {
  total_revenue: number;
  revenue_growth: number; // percentage
  total_orders: number;
  orders_growth: number;
  active_services: number;
  services_growth: number;
  total_users: number;
  users_growth: number;
}

export interface DashboardChartSeriesPoint {
  date: string;
  labelEn: string;
  labelAr: string;
  revenue: number;
  orders: number;
}

export interface DashboardActivityLog {
  id: string;
  textEn: string;
  textAr: string;
  timestamp: string;
}

export interface DashboardSummaryPayload {
  overview_stats: OverviewStats;
  chart_series: DashboardChartSeriesPoint[];
  activity_logs: DashboardActivityLog[];
  category_distribution: Array<{
    category_id: number;
    name_en: string;
    name_ar: string;
    percentage: number;
    order_count: number;
  }>;
  system_health: {
    laravel_uptime: string;
    db_connection_status: 'healthy' | 'warning' | 'error';
    active_jobs: number;
  };
}

export interface AuditLog {
  id: string;
  timestamp: string;
  actorRole: UserRole;
  actorName: string;
  action: string; // 'delete' | 'approve' | 'reject' | 'status_change' | 'gateway_settings' | 'role_assignment' | 'language_change' | 'payout_change' | 'activate' | 'deactivate' | 'feature' | 'unfeature'
  resource: string;
  detailsEn: string;
  detailsAr: string;
  status: 'success' | 'denied' | 'pending_approval';
  ip: string;
}

export interface ChangeRecord {
  id: string;
  timestamp: string;
  resource: string;
  actorName: string;
  field: string;
  oldValue: string;
  newValue: string;
}

export interface ApprovalTask {
  id: string;
  timestamp: string;
  requestedBy: string;
  requestedByRole: UserRole;
  actionType: string;
  details: string;
  resource: string;
  payload: any;
  status: 'pending' | 'approved' | 'rejected';
}
