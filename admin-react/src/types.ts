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
  seller_verified?: boolean;
  tax_number?: string;
  business_registration?: string;
  address?: string;
  seller_verification?: {
    status: number;
    is_verified: boolean;
    national_id?: string;
    address?: string;
  };
}

export interface SellerVerificationDetail {
  user_id: number;
  name: string;
  email: string;
  phone?: string;
  status: number; // 0=pending, 1=approved, 2=rejected
  is_verified: boolean;
  national_id?: string;
  address?: string;
  tax_number?: string;
  business_registration?: string;
  created_at?: string;
  updated_at?: string;
}

export interface PayoutRequestItem {
  id: number;
  seller_id: number;
  seller_name: string;
  seller_email: string;
  seller_phone?: string;
  amount: number;
  payment_receipt?: string;
  status: number; // 0=pending, 1=approved/paid, 2=rejected
  status_label: 'pending' | 'completed' | 'rejected';
  created_at: string;
  updated_at: string;
  note?: string;
}

export interface TicketMessageItem {
  id: number;
  support_ticket_id: number;
  user_id: number;
  type: 'admin' | 'user';
  message: string;
  attachment?: string;
  notify?: string;
  created_at: string;
  sender_name: string;
}

export interface PaymentGatewaySettings {
  paytabs: {
    status: boolean;
    profile_id: string;
    server_key_masked: string;
    client_key_masked: string;
    currency: string;
    sandbox_mode: boolean;
  };
  manual_payment: {
    status: boolean;
    title: string;
    description: string;
  };
}

export interface ImmutableAuditLogItem {
  id: number;
  admin_id: number;
  admin_name: string;
  action: string;
  resource: string;
  resource_id?: string;
  details?: string;
  previous_state?: any;
  new_state?: any;
  ip_address?: string;
  created_at: string;
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
  category_id?: number;
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

export interface WalletItem {
  id: number;
  user_id: number;
  user_name: string;
  user_email: string;
  user_phone: string;
  role: 'buyer' | 'seller';
  balance: number;
  pending_balance: number;
  total_earned: number;
  total_spent: number;
  status: 'active' | 'suspended';
  currency: string;
  updated_at: string;
}

export interface WalletSummary {
  total_wallets: number;
  active_wallets: number;
  frozen_wallets: number;
  total_circulation: number;
  total_pending: number;
  total_earned: number;
  total_spent: number;
  currency: string;
}

export interface WalletLedgerItem {
  id: number;
  transaction_id: string;
  entry_type: 'credit' | 'debit' | 'hold' | 'release' | 'refund' | 'adjustment' | 'payout';
  amount: number;
  balance_before: number;
  balance_after: number;
  payment_gateway: string;
  payment_status: string;
  reference_type: string;
  reference_id: string;
  description_en: string;
  description_ar: string;
  admin_name?: string;
  admin_note?: string;
  created_at: string;
}

export interface UserWalletDetail {
  user: {
    id: number;
    name: string;
    email: string;
    phone: string;
    role: 'buyer' | 'seller';
  };
  wallet: {
    id: number;
    balance: number;
    pending_balance: number;
    total_earned: number;
    total_spent: number;
    status: 'active' | 'suspended';
    currency: string;
  };
  ledger: WalletLedgerItem[];
}

export interface ChatParticipant {
  id: number;
  name: string;
  email?: string;
}

export interface ChatConversationItem {
  id: number;
  buyer: ChatParticipant;
  seller: ChatParticipant;
  last_message: string;
  last_message_at: string;
  total_messages: number;
  buyer_unread: number;
  seller_unread: number;
  status: 'active' | 'archived';
}

export interface ChatMessageItem {
  id: number;
  from_user: number;
  sender_name: string;
  sender_role: 'buyer' | 'seller';
  message: string;
  image?: string;
  image_url?: string;
  is_read: boolean;
  created_at: string;
  time_str: string;
}

export interface ChatHubSummary {
  total_conversations: number;
  total_messages: number;
  messages_today: number;
  active_conversations: number;
}

export interface ChatConversationDetail {
  conversation: {
    id: number;
    buyer: ChatParticipant;
    seller: ChatParticipant;
    status: 'active' | 'archived';
    created_at: string;
  };
  messages: ChatMessageItem[];
}

export interface JobProposalItem {
  id: number;
  job_post_id: number;
  buyer_id: number;
  seller_id: number;
  seller?: {
    id: number;
    name: string;
    email: string;
    phone?: string;
  };
  expected_salary: number;
  cover_letter: string;
  is_hired: number;
  status: number;
  created_at: string;
  conversations?: Array<{
    id: number;
    type: 'buyer' | 'seller';
    message: string;
    created_at: string;
  }>;
}

export interface JobPostItem {
  id: number;
  category_id: number;
  subcategory_id?: number;
  buyer_id: number;
  buyer?: {
    id: number;
    name: string;
    email: string;
    phone?: string;
  };
  category?: {
    id: number;
    name: string;
  };
  subcategory?: {
    id: number;
    name: string;
  };
  city?: {
    id: number;
    service_city: string;
  };
  title: string;
  slug: string;
  description: string;
  price: number;
  is_job_online: number;
  dead_line?: string;
  view: number;
  is_job_on: number;
  status: number;
  job_requests_count?: number;
  created_at: string;
}

export interface JobsSummary {
  total_jobs: number;
  open_jobs: number;
  hired_jobs: number;
  total_proposals: number;
  hired_proposals: number;
}

export interface SubscriptionPlanItem {
  id: number;
  title: string;
  type: 'monthly' | 'yearly' | 'lifetime';
  price: number;
  connect: number;
  service: number;
  job: number;
  description?: string;
  status: number;
  created_at: string;
}

export interface SellerSubscriberItem {
  id: number;
  seller_id: number;
  subscription_id?: number;
  type: string;
  price: number;
  connect: number;
  service: number;
  job: number;
  initial_connect: number;
  initial_service: number;
  initial_job: number;
  expire_date?: string;
  payment_gateway?: string;
  payment_status: string;
  status: number;
  created_at: string;
  seller?: {
    id: number;
    name: string;
    email: string;
    phone?: string;
  };
  subscription?: {
    id: number;
    title: string;
  };
}

export interface SubscriptionHistoryItem {
  id: number;
  seller_id: number;
  subscription_id?: number;
  type?: string;
  price: number;
  connect: number;
  service: number;
  job: number;
  expire_date?: string;
  payment_gateway?: string;
  payment_status: string;
  status: number;
  created_at: string;
  seller?: {
    id: number;
    name: string;
    email: string;
  };
  subscription?: {
    id: number;
    title: string;
  };
}

export interface SubscriptionSummary {
  total_plans: number;
  active_subscribers: number;
  expired_subscribers: number;
  total_revenue: number;
}



