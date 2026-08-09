import { UserRole, Permission, AuditLog, ChangeRecord, ApprovalTask } from '../types';

// ----------------------------------------------------------------------
// ROLE & PERMISSION MATRIX
// ----------------------------------------------------------------------
export const ROLE_PERMISSIONS: Record<UserRole, Permission[]> = {
  super_admin: [
    'manage_users',
    'manage_services',
    'manage_orders',
    'manage_payments',
    'manage_support',
    'manage_settings',
    'manage_cms',
    'view_analytics'
  ],
  moderator: [
    'manage_users',
    'manage_services',
    'manage_orders',
    'manage_support',
    'manage_cms',
    'view_analytics'
  ],
  financial_manager: [
    'manage_orders',
    'manage_payments',
    'view_analytics'
  ],
  support_agent: [
    'manage_support',
    'view_analytics'
  ]
};

export const ROLE_NAMES: Record<UserRole, { en: string; ar: string }> = {
  super_admin: { en: 'Super Admin', ar: 'مشرف عام النظام' },
  moderator: { en: 'Moderator', ar: 'مراقب المحتوى والخدمات' },
  financial_manager: { en: 'Financial Manager', ar: 'المدير المالي' },
  support_agent: { en: 'Support Agent', ar: 'عميل الدعم الفني' }
};

export function checkPermission(role: UserRole, permission: Permission): boolean {
  return ROLE_PERMISSIONS[role]?.includes(permission) || false;
}

// ----------------------------------------------------------------------
// ACTION GUARD RULES
// ----------------------------------------------------------------------
export type ActionGuardResult = 'allowed' | 'denied' | 'requires_approval';

export interface ActionGuardRequest {
  action: string;
  role: UserRole;
  resource: string;
  amount?: number;
}

export function evaluateActionGuard({ action, role, resource, amount }: ActionGuardRequest): ActionGuardResult {
  // Super Admin can do anything
  if (role === 'super_admin') return 'allowed';

  switch (action) {
    case 'delete':
      // Support Agent can never delete anything
      if (role === 'support_agent') return 'denied';
      // Moderator can delete listings/support tickets/CMS, but not payments or settings
      if (role === 'moderator') {
        if (resource.toLowerCase().includes('payment') || resource.toLowerCase().includes('setting') || resource.toLowerCase().includes('gateway')) {
          return 'denied';
        }
        return 'allowed';
      }
      // Financial manager can delete payments if needed, but not general users or settings
      if (role === 'financial_manager') {
        if (resource.toLowerCase().includes('payment') || resource.toLowerCase().includes('order')) {
          return 'allowed';
        }
        return 'denied';
      }
      return 'denied';

    case 'approve':
    case 'reject':
    case 'payout-related':
      if (role === 'support_agent') return 'denied';
      if (role === 'moderator') {
        // Moderator can approve services, but not financial payouts
        if (resource.toLowerCase().includes('service') || resource.toLowerCase().includes('listing')) {
          return 'allowed';
        }
        return 'denied';
      }
      if (role === 'financial_manager') {
        // Financial manager can approve payouts under 5,000 SAR. Larger ones require Super Admin approval!
        if (amount && amount > 5000) {
          return 'requires_approval';
        }
        return 'allowed';
      }
      return 'denied';

    case 'activate/deactivate':
    case 'status change':
      if (role === 'support_agent') {
        // Support agent can resolve tickets, but not suspend users or services
        if (resource.toLowerCase().includes('ticket') || resource.toLowerCase().includes('support')) {
          return 'allowed';
        }
        return 'denied';
      }
      if (role === 'moderator') return 'allowed';
      if (role === 'financial_manager') {
        // Financial manager can change order/payment status, not users/services
        if (resource.toLowerCase().includes('payment') || resource.toLowerCase().includes('order')) {
          return 'allowed';
        }
        return 'denied';
      }
      return 'denied';

    case 'feature/unfeature':
      if (role === 'moderator') return 'allowed';
      return 'denied';

    case 'gateway settings':
    case 'role assignment':
      // Strictly Super Admin
      return 'denied';

    case 'language changes':
      // Anyone can toggle viewing language, but modifying localization keys requires Super Admin or Moderator
      if (role === 'moderator') return 'allowed';
      return 'denied';

    default:
      return 'denied';
  }
}

export function getAuditLogs(): AuditLog[] {
  return [];
}

export function saveAuditLog(log: Omit<AuditLog, 'id' | 'timestamp' | 'ip'>) {
  console.warn('Audit logger is backend-gap only. No persistent audit store is configured yet.', log);
  return {
    ...log,
    id: 'AUD-00000',
    timestamp: new Date().toISOString().replace('T', ' ').substring(0, 19),
    ip: '0.0.0.0',
  };
}

export function getChangeHistory(): ChangeRecord[] {
  return [];
}

export function saveChangeRecord(record: Omit<ChangeRecord, 'id' | 'timestamp'>) {
  console.warn('Change history persistence is not wired to the backend yet.', record);
  return {
    ...record,
    id: 'CHG-00000',
    timestamp: new Date().toISOString().replace('T', ' ').substring(0, 19),
  };
}

export function getApprovalTasks(): ApprovalTask[] {
  return [];
}

export function saveApprovalTask(task: Omit<ApprovalTask, 'id' | 'timestamp' | 'status'>) {
  console.warn('Approval queue is not backed by Laravel yet.', task);
  return {
    ...task,
    id: 'APP-00000',
    timestamp: new Date().toISOString().replace('T', ' ').substring(0, 19),
    status: 'pending',
  };
}

export function updateApprovalStatus(id: string, status: 'approved' | 'rejected') {
  console.warn('Approval queue updates are not persisted because the backend audit module is missing.', { id, status });
}

export function clearLogs() {
  console.warn('Audit log reset requested, but no persistent backend audit store exists.');
}
