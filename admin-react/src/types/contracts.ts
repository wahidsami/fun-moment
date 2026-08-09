/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, UserRole } from '../types';

/**
 * ==========================================
 * GLOBAL API RESPONSE & ERROR CONTRACTS
 * ==========================================
 */

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message_en: string;
  message_ar: string;
  meta?: {
    timestamp: string;
    api_version: string;
    execution_time_ms: number;
    locale: Language;
    direction: 'ltr' | 'rtl';
  };
}

export interface ApiError {
  success: false;
  error_code: string;
  message_en: string;
  message_ar: string;
  errors?: Record<string, string[]>; // Laravel validation structure
  stack?: string; // Appears only in development
}

export interface PaginationParams {
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_direction?: 'asc' | 'desc';
}

export interface PaginatedData<T> {
  items: T[];
  pagination: {
    total_records: number;
    current_page: number;
    per_page: number;
    last_page: number;
    has_more: boolean;
  };
}

/**
 * ==========================================
 * AUTH & USER CONTRACTS
 * ==========================================
 */

export interface AuthSession {
  token: string;
  token_type: 'Bearer';
  expires_at: string;
  user: {
    id: number;
    name: string;
    email: string;
    role: UserRole | 'buyer' | 'seller';
    permissions: string[];
    avatar_url: string | null;
  };
}

/**
 * ==========================================
 * ADMIN DASHBOARD SUMMARY
 * ==========================================
 */

export interface DashboardSummary {
  revenue: {
    total_sar: number;
    growth_percentage: number;
    chart_series: { date: string; amount: number }[];
  };
  orders: {
    total_count: number;
    growth_percentage: number;
    pending_count: number;
    completed_count: number;
  };
  services: {
    total_count: number;
    growth_percentage: number;
    pending_approval: number;
  };
  users: {
    total_count: number;
    growth_percentage: number;
    sellers_count: number;
    buyers_count: number;
  };
  category_distribution: {
    category_id: number;
    name_en: string;
    name_ar: string;
    percentage: number;
    order_count: number;
  }[];
  system_health: {
    laravel_uptime: string;
    db_connection_status: 'healthy' | 'warning' | 'error';
    active_jobs: number;
  };
}

/**
 * ==========================================
 * ORDERS
 * ==========================================
 */

export type OrderStatus = 'pending' | 'in_progress' | 'completed' | 'cancelled';
export type PaymentStatus = 'paid' | 'unpaid' | 'refunded';

export interface OrderListItem {
  id: number;
  service_title_en: string;
  service_title_ar: string;
  buyer_name: string;
  seller_name: string;
  amount: number;
  status: OrderStatus;
  payment_status: PaymentStatus;
  created_at: string;
}

export interface OrderDetail extends OrderListItem {
  service_id: number;
  buyer_id: number;
  buyer_phone: string;
  buyer_email: string;
  seller_id: number;
  seller_phone: string;
  seller_email: string;
  payment_method: string;
  transaction_reference: string | null;
  notes_en: string | null;
  notes_ar: string | null;
  logs: {
    action_en: string;
    action_ar: string;
    created_at: string;
    actor_name: string;
  }[];
}

export interface OrderUpdatePayload {
  status: OrderStatus;
  payment_status?: PaymentStatus;
  internal_notes?: string;
}

export interface OrderFilters extends PaginationParams {
  status?: OrderStatus;
  payment_status?: PaymentStatus;
  search_query?: string;
  start_date?: string;
  end_date?: string;
}

/**
 * ==========================================
 * SERVICES
 * ==========================================
 */

export type ServiceStatus = 'active' | 'pending' | 'suspended';

export interface ServiceListItem {
  id: number;
  title_en: string;
  title_ar: string;
  category_name_en: string;
  category_name_ar: string;
  seller_name: string;
  price: number;
  duration: string;
  status: ServiceStatus;
  rating: number;
  sales_count: number;
  created_at: string;
}

export interface ServiceDetail extends ServiceListItem {
  seller_id: number;
  description_en: string;
  description_ar: string;
  gallery_urls: string[];
  terms_en: string | null;
  terms_ar: string | null;
  max_capacity: number;
  subcategory_name_en: string;
  subcategory_name_ar: string;
  child_category_name_en: string | null;
  child_category_name_ar: string | null;
}

export interface ServiceCreateUpdatePayload {
  title_en: string;
  title_ar: string;
  category_id: number;
  subcategory_id: number;
  child_category_id?: number;
  price: number;
  duration: string;
  description_en: string;
  description_ar: string;
  gallery_urls?: string[];
  terms_en?: string;
  terms_ar?: string;
  max_capacity?: number;
}

export interface ServiceFilters extends PaginationParams {
  status?: ServiceStatus;
  category_id?: number;
  search_query?: string;
  min_price?: number;
  max_price?: number;
}

/**
 * ==========================================
 * SELLERS & BUYERS
 * ==========================================
 */

export type UserStatus = 'active' | 'suspended' | 'pending';

export interface SellerListItem {
  id: number;
  store_name_en: string;
  store_name_ar: string;
  owner_name: string;
  email: string;
  phone: string;
  status: UserStatus;
  rating: number;
  total_sales: number;
  created_at: string;
}

export interface SellerDetail extends SellerListItem {
  commercial_register: string | null;
  vat_number: string | null;
  bank_name: string;
  iban: string;
  commission_rate: number; // percentage
  wallet_balance: number;
}

export interface SellerCreateUpdatePayload {
  store_name_en: string;
  store_name_ar: string;
  owner_name: string;
  email: string;
  phone: string;
  commission_rate: number;
  status?: UserStatus;
}

export interface BuyerListItem {
  id: number;
  name: string;
  email: string;
  phone: string;
  status: UserStatus;
  total_bookings: number;
  total_spent: number;
  created_at: string;
}

export interface BuyerDetail extends BuyerListItem {
  wallet_balance: number;
  last_login_at: string | null;
  preferred_language: Language;
}

export interface BuyerCreateUpdatePayload {
  name: string;
  email: string;
  phone: string;
  status?: UserStatus;
}

export interface UserFilters extends PaginationParams {
  status?: UserStatus;
  search_query?: string;
}

/**
 * ==========================================
 * CATEGORIES, SUBCATEGORIES, CHILD CATEGORIES
 * ==========================================
 */

export interface CategoryListItem {
  id: number;
  name_en: string;
  name_ar: string;
  slug: string;
  status: 'active' | 'inactive';
  icon_class: string;
  services_count: number;
}

export interface CategoryDetail extends CategoryListItem {
  description_en: string | null;
  description_ar: string | null;
  subcategories: SubcategoryListItem[];
}

export interface CategoryCreateUpdatePayload {
  name_en: string;
  name_ar: string;
  slug: string;
  icon_class: string;
  description_en?: string;
  description_ar?: string;
  status: 'active' | 'inactive';
}

export interface SubcategoryListItem {
  id: number;
  category_id: number;
  name_en: string;
  name_ar: string;
  slug: string;
  status: 'active' | 'inactive';
  services_count: number;
}

export interface SubcategoryDetail extends SubcategoryListItem {
  child_categories: ChildCategoryListItem[];
}

export interface SubcategoryCreateUpdatePayload {
  category_id: number;
  name_en: string;
  name_ar: string;
  slug: string;
  status: 'active' | 'inactive';
}

export interface ChildCategoryListItem {
  id: number;
  subcategory_id: number;
  name_en: string;
  name_ar: string;
  slug: string;
  status: 'active' | 'inactive';
  services_count: number;
}

export interface ChildCategoryCreateUpdatePayload {
  subcategory_id: number;
  name_en: string;
  name_ar: string;
  slug: string;
  status: 'active' | 'inactive';
}

/**
 * ==========================================
 * COUNTRIES, CITIES, AREAS
 * ==========================================
 */

export interface CountryListItem {
  id: number;
  name_en: string;
  name_ar: string;
  code: string; // e.g. SA
  phone_code: string; // e.g. +966
  currency_en: string; // e.g. SAR
  currency_ar: string; // e.g. ريال
  status: 'active' | 'inactive';
}

export interface CountryCreateUpdatePayload {
  name_en: string;
  name_ar: string;
  code: string;
  phone_code: string;
  currency_en: string;
  currency_ar: string;
  status: 'active' | 'inactive';
}

export interface CityListItem {
  id: number;
  country_id: number;
  name_en: string;
  name_ar: string;
  status: 'active' | 'inactive';
}

export interface CityCreateUpdatePayload {
  country_id: number;
  name_en: string;
  name_ar: string;
  status: 'active' | 'inactive';
}

export interface AreaListItem {
  id: number;
  city_id: number;
  name_en: string;
  name_ar: string;
  status: 'active' | 'inactive';
}

export interface AreaCreateUpdatePayload {
  city_id: number;
  name_en: string;
  name_ar: string;
  status: 'active' | 'inactive';
}

/**
 * ==========================================
 * BLOGS
 * ==========================================
 */

export interface BlogListItem {
  id: number;
  title_en: string;
  title_ar: string;
  slug: string;
  status: 'published' | 'draft';
  author_name: string;
  published_at: string | null;
  view_count: number;
}

export interface BlogDetail extends BlogListItem {
  content_en: string;
  content_ar: string;
  featured_image_url: string | null;
  meta_title_en: string | null;
  meta_title_ar: string | null;
  meta_desc_en: string | null;
  meta_desc_ar: string | null;
}

export interface BlogCreateUpdatePayload {
  title_en: string;
  title_ar: string;
  slug: string;
  content_en: string;
  content_ar: string;
  featured_image_url?: string;
  status: 'published' | 'draft';
  meta_title_en?: string;
  meta_title_ar?: string;
  meta_desc_en?: string;
  meta_desc_ar?: string;
}

/**
 * ==========================================
 * PAGES
 * ==========================================
 */

export interface PageListItem {
  id: number;
  title_en: string;
  title_ar: string;
  slug: string;
  status: 'published' | 'draft';
  updated_at: string;
}

export interface PageDetail extends PageListItem {
  content_en: string;
  content_ar: string;
  meta_title_en: string | null;
  meta_title_ar: string | null;
  meta_desc_en: string | null;
  meta_desc_ar: string | null;
}

export interface PageCreateUpdatePayload {
  title_en: string;
  title_ar: string;
  slug: string;
  content_en: string;
  content_ar: string;
  status: 'published' | 'draft';
  meta_title_en?: string;
  meta_title_ar?: string;
  meta_desc_en?: string;
  meta_desc_ar?: string;
}

/**
 * ==========================================
 * MENUS & WIDGETS
 * ==========================================
 */

export interface MenuItem {
  id: number;
  label_en: string;
  label_ar: string;
  url: string;
  order: number;
  parent_id: number | null;
}

export interface MenuListItem {
  id: number;
  location: 'header' | 'footer_primary' | 'footer_secondary' | 'sidebar_quick';
  status: 'active' | 'inactive';
  items: MenuItem[];
}

export interface MenuCreateUpdatePayload {
  location: string;
  status: 'active' | 'inactive';
  items: Omit<MenuItem, 'id'>[];
}

export interface WidgetListItem {
  id: number;
  title_en: string;
  title_ar: string;
  type: 'html' | 'featured_services' | 'contact_info' | 'newsletter';
  placement: 'footer_col_1' | 'footer_col_2' | 'sidebar_blog' | 'homepage_hero';
  status: 'active' | 'inactive';
  content_json: Record<string, any>;
}

export interface WidgetCreateUpdatePayload {
  title_en: string;
  title_ar: string;
  type: string;
  placement: string;
  status: 'active' | 'inactive';
  content_json: Record<string, any>;
}

/**
 * ==========================================
 * NOTIFICATIONS
 * ==========================================
 */

export interface NotificationListItem {
  id: string; // UUID
  title_en: string;
  title_ar: string;
  message_en: string;
  message_ar: string;
  type: 'system' | 'booking' | 'payment' | 'chat';
  is_read: boolean;
  created_at: string;
}

/**
 * ==========================================
 * REPORTS
 * ==========================================
 */

export interface SalesReport {
  summary: {
    gross_volume: number;
    platform_earnings: number;
    seller_payouts: number;
    vat_collected: number;
    orders_count: number;
  };
  daily_breakdown: {
    date: string;
    gross_sales: number;
    orders_count: number;
  }[];
}

export interface ActivityLogReport {
  id: number;
  actor_name: string;
  actor_role: string;
  action_en: string;
  action_ar: string;
  ip_address: string;
  created_at: string;
}

/**
 * ==========================================
 * SUPPORT TICKETS
 * ==========================================
 */

export interface SupportMessage {
  id: number;
  sender_name: string;
  sender_role: 'buyer' | 'seller' | 'admin' | 'agent';
  message: string;
  created_at: string;
}

export interface SupportTicketListItem {
  id: number;
  user_name: string;
  user_email: string;
  subject_en: string;
  subject_ar: string;
  category: 'payment' | 'service' | 'technical' | 'refund';
  priority: 'low' | 'medium' | 'high';
  status: 'open' | 'in_progress' | 'resolved' | 'closed';
  created_at: string;
}

export interface SupportTicketDetail extends SupportTicketListItem {
  messages: SupportMessage[];
}

export interface SupportTicketUpdatePayload {
  status?: 'open' | 'in_progress' | 'resolved' | 'closed';
  priority?: 'low' | 'medium' | 'high';
  internal_note?: string;
}

/**
 * ==========================================
 * PAYMENT GATEWAYS
 * ==========================================
 */

export interface PaymentGatewayListItem {
  id: string; // e.g. "stc_pay", "apple_pay", "paytabs"
  name_en: string;
  name_ar: string;
  is_active: boolean;
  mode: 'sandbox' | 'live';
  supported_currencies: string[];
}

export interface PaymentGatewayDetail extends PaymentGatewayListItem {
  merchant_id: string | null;
  api_key: string | null;
  secret_key: string | null;
  webhook_url: string;
}

export interface PaymentGatewayUpdatePayload {
  is_active: boolean;
  mode: 'sandbox' | 'live';
  merchant_id?: string;
  api_key?: string;
  secret_key?: string;
}

/**
 * ==========================================
 * GENERAL SETTINGS
 * ==========================================
 */

export interface GeneralSettings {
  app_name_en: string;
  app_name_ar: string;
  support_email: string;
  support_phone: string;
  vat_percentage: number;
  maintenance_mode: boolean;
  allowed_file_types: string[]; // e.g. ["jpg", "png", "pdf"]
  max_upload_size_mb: number;
}

export interface GeneralSettingsUpdatePayload {
  app_name_en: string;
  app_name_ar: string;
  support_email: string;
  support_phone: string;
  vat_percentage: number;
  maintenance_mode: boolean;
  max_upload_size_mb: number;
}

/**
 * ==========================================
 * LOCALIZATION
 * ==========================================
 */

export interface LocalizationConfig {
  default_locale: Language;
  supported_locales: Language[];
  default_currency: string;
  sar_usd_rate: number;
  timezone: string;
}

/**
 * ==========================================
 * EMAIL TEMPLATES
 * ==========================================
 */

export interface EmailTemplateListItem {
  id: string; // e.g. "welcome_user", "order_receipt"
  name_en: string;
  name_ar: string;
  subject_en: string;
  subject_ar: string;
  trigger_event: string;
}

export interface EmailTemplateDetail extends EmailTemplateListItem {
  body_en: string; // HTML format with dynamic tokens
  body_ar: string; // HTML format with dynamic tokens
  variables: string[]; // e.g. ["{{user_name}}", "{{order_id}}"]
}

export interface EmailTemplateUpdatePayload {
  subject_en: string;
  subject_ar: string;
  body_en: string;
  body_ar: string;
}

/**
 * ==========================================
 * ADD-ONS: WALLET, CHAT, JOBS, SUBSCRIPTION
 * ==========================================
 */

// Wallet Module Contract
export interface WalletTransaction {
  id: number;
  user_id: number;
  user_name: string;
  type: 'deposit' | 'withdrawal' | 'booking_payment' | 'booking_escrow' | 'payout';
  amount: number;
  currency: 'SAR';
  status: 'completed' | 'pending' | 'failed' | 'cancelled';
  created_at: string;
  description_en: string;
  description_ar: string;
}

export interface WalletSummary {
  module_enabled: boolean;
  total_escrow_balance: number;
  total_withdrawn_amount: number;
  recent_transactions: WalletTransaction[];
}

// Live Chat Module Contract
export interface ChatRoomListItem {
  id: string; // UUID
  buyer_name: string;
  seller_name: string;
  last_message: string;
  last_message_at: string;
  unread_count: number;
  is_flagged: boolean;
}

export interface ChatMessage {
  id: string;
  sender_name: string;
  message_text: string;
  created_at: string;
}

// Jobs/Bidding Module Contract
export interface JobListItem {
  id: number;
  buyer_name: string;
  title_en: string;
  title_ar: string;
  budget_sar: number;
  status: 'active' | 'completed' | 'cancelled';
  bids_count: number;
  created_at: string;
}

// Subscription Module Contract
export interface SubscriptionTierListItem {
  id: string; // e.g. "silver", "gold"
  name_en: string;
  name_ar: string;
  price_monthly_sar: number;
  max_listings: number;
  features_en: string[];
  features_ar: string[];
  subscribers_count: number;
}

/**
 * ==========================================
 * FEATURE FLAGS / MODULE CONTROL
 * ==========================================
 */

export interface FeatureFlagContract {
  module_name: string;
  key: string;
  label_en: string;
  label_ar: string;
  description_en: string;
  description_ar: string;
  icon: string;
  enabled: boolean;
  locked: boolean;
}

export interface FeatureFlagsPayload {
  modules: FeatureFlagContract[];
  feature_flags: Record<string, { enabled: boolean; locked: boolean }>;
}
