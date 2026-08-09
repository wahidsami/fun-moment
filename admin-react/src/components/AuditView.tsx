/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useEffect, useMemo, useState } from 'react';
import { Language, UserRole, Permission } from '../types';
import { LaravelAPI } from '../api';
import { ROLE_NAMES, ROLE_PERMISSIONS, checkPermission } from '../utils/auditLogger';
import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  History,
  RefreshCw,
  Shield,
  ShieldAlert,
  ShieldCheck,
  Terminal,
} from 'lucide-react';

interface AuditViewProps {
  language: Language;
  activeRole: UserRole;
  onLogUpdated?: () => void;
}

type BackendLog = {
  id?: string;
  timestamp?: string;
  actorRole?: UserRole;
  actorName?: string;
  action?: string;
  resource?: string;
  detailsEn?: string;
  detailsAr?: string;
  status?: 'success' | 'denied' | 'pending_approval';
  ip?: string;
};

export default function AuditView({ language, activeRole, onLogUpdated }: AuditViewProps) {
  const isRtl = language === 'ar';

  const [activeTab, setActiveTab] = useState<'matrix' | 'logs' | 'history' | 'approvals'>('matrix');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'success' | 'denied' | 'pending_approval'>('all');
  const [logs, setLogs] = useState<BackendLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const loadLogs = async () => {
    setLoading(true);
    setError('');

    try {
      const items = await LaravelAPI.getActivityLogs();
      setLogs(Array.isArray(items) ? items : []);
      onLogUpdated?.();
    } catch (err) {
      console.error(err);
      setLogs([]);
      setError(isRtl ? 'تعذر تحميل سجل النشاط من الخادم.' : 'Could not load the activity feed from the backend.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadLogs();
  }, []);

  const filteredLogs = useMemo(() => {
    return logs.filter(log => {
      const details = isRtl ? (log.detailsAr ?? '') : (log.detailsEn ?? '');
      const matchesSearch =
        (log.actorName ?? '').toLowerCase().includes(searchQuery.toLowerCase()) ||
        (log.resource ?? '').toLowerCase().includes(searchQuery.toLowerCase()) ||
        (details ?? '').toLowerCase().includes(searchQuery.toLowerCase());
      const matchesStatus = statusFilter === 'all' || log.status === statusFilter;
      return matchesSearch && matchesStatus;
    });
  }, [logs, searchQuery, statusFilter, isRtl]);

  const permissionList: { key: Permission; labelEn: string; labelAr: string }[] = [
    { key: 'manage_users', labelEn: 'User management', labelAr: 'إدارة المستخدمين' },
    { key: 'manage_services', labelEn: 'Service moderation', labelAr: 'مراجعة الخدمات' },
    { key: 'manage_orders', labelEn: 'Order control', labelAr: 'التحكم في الطلبات' },
    { key: 'manage_payments', labelEn: 'Payment controls', labelAr: 'التحكم في المدفوعات' },
    { key: 'manage_support', labelEn: 'Support tickets', labelAr: 'تذاكر الدعم' },
    { key: 'manage_cms', labelEn: 'CMS management', labelAr: 'إدارة المحتوى' },
    { key: 'manage_settings', labelEn: 'Platform settings', labelAr: 'إعدادات المنصة' },
    { key: 'view_analytics', labelEn: 'Analytics', labelAr: 'التحليلات' },
  ];

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-indigo-200 bg-linear-to-r from-indigo-50/60 to-white p-5 shadow-xs flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div className="flex items-start gap-4">
          <div className="rounded-2xl bg-indigo-600 p-3 text-white">
            <Shield className="h-6 w-6" />
          </div>
          <div>
            <h2 className="text-base font-black text-slate-800 tracking-tight">
              {isRtl ? 'مركز الأمان والتدقيق' : 'Security & Audit Center'}
            </h2>
            <p className="mt-1 max-w-2xl text-xs leading-6 text-slate-500">
              {isRtl
                ? 'يعرض هذا القسم سجل النشاط القادم من Laravel. لا يوجد حالياً مخزن تدقيق مستقل أو طابور موافقات خلفي، لذلك تظهر أقسام التاريخ والموافقات كمساحات فارغة حقيقية.'
                : 'This view uses the Laravel activity feed only. There is no independent backend audit store or approval queue yet, so history and approvals are shown as true empty states.'}
            </p>
          </div>
        </div>

        <button
          onClick={loadLogs}
          className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
        >
          <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          <span>{isRtl ? 'تحديث' : 'Refresh'}</span>
        </button>
      </div>

      {error && (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
          <div className="flex items-center gap-2 font-semibold">
            <ShieldAlert className="h-4 w-4" />
            <span>{error}</span>
          </div>
        </div>
      )}

      <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="flex gap-4 overflow-x-auto border-b border-slate-100 pb-px text-xs font-bold">
          <button
            onClick={() => setActiveTab('matrix')}
            className={`pb-3 ${activeTab === 'matrix' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500'}`}
          >
            {isRtl ? 'مصفوفة الصلاحيات' : 'Permission Matrix'}
          </button>
          <button
            onClick={() => setActiveTab('logs')}
            className={`pb-3 ${activeTab === 'logs' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500'}`}
          >
            {isRtl ? 'سجل النشاط' : 'Activity Log'}
          </button>
          <button
            onClick={() => setActiveTab('history')}
            className={`pb-3 ${activeTab === 'history' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500'}`}
          >
            {isRtl ? 'التاريخ' : 'History'}
          </button>
          <button
            onClick={() => setActiveTab('approvals')}
            className={`pb-3 ${activeTab === 'approvals' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500'}`}
          >
            {isRtl ? 'الموافقات' : 'Approvals'}
          </button>
        </div>

        {activeTab === 'matrix' && (
          <div className="mt-4 grid gap-4 lg:grid-cols-2">
            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <h3 className="text-sm font-bold text-slate-800">{isRtl ? 'الدور الحالي' : 'Current Role'}</h3>
              <p className="mt-2 text-sm text-slate-600">{ROLE_NAMES[activeRole]?.[language === 'en' ? 'en' : 'ar'] ?? activeRole}</p>
              <div className="mt-3 flex flex-wrap gap-2">
                {ROLE_PERMISSIONS[activeRole].map(perm => (
                  <span key={perm} className="rounded-full bg-white px-2 py-1 text-[10px] font-bold text-slate-500">
                    {perm}
                  </span>
                ))}
              </div>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-4">
              <h3 className="text-sm font-bold text-slate-800">{isRtl ? 'مسارات الصلاحية' : 'Permission scope'}</h3>
              <div className="mt-3 space-y-2">
                {permissionList.map(item => {
                  const allowed = checkPermission(activeRole, item.key);
                  return (
                    <div key={item.key} className="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2">
                      <div>
                        <p className="text-sm font-semibold text-slate-800">{isRtl ? item.labelAr : item.labelEn}</p>
                        <p className="text-[10px] text-slate-400">{item.key}</p>
                      </div>
                      <span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-[10px] font-bold ${allowed ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
                        {allowed ? <ShieldCheck className="h-3 w-3" /> : <ShieldAlert className="h-3 w-3" />}
                        {allowed ? (isRtl ? 'مسموح' : 'Allowed') : (isRtl ? 'مرفوض' : 'Denied')}
                      </span>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        )}

        {activeTab === 'logs' && (
          <div className="mt-4 space-y-4">
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <input
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder={isRtl ? 'ابحث في السجل...' : 'Search activity...'}
                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 md:max-w-md"
              />
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value as typeof statusFilter)}
                className="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500"
              >
                <option value="all">{isRtl ? 'الكل' : 'All'}</option>
                <option value="success">{isRtl ? 'نجاح' : 'Success'}</option>
                <option value="denied">{isRtl ? 'مرفوض' : 'Denied'}</option>
                <option value="pending_approval">{isRtl ? 'بانتظار الموافقة' : 'Pending approval'}</option>
              </select>
            </div>

            {loading ? (
              <div className="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                <RefreshCw className="mx-auto mb-2 h-4 w-4 animate-spin" />
                {isRtl ? 'جاري التحميل...' : 'Loading...'}
              </div>
            ) : filteredLogs.length > 0 ? (
              <div className="space-y-3">
                {filteredLogs.map((log, index) => (
                  <div key={log.id || index} className="rounded-2xl border border-slate-200 p-4">
                    <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="rounded bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-500">{log.id || `LOG-${index + 1}`}</span>
                          <span className="text-sm font-bold text-slate-800">{log.actorName || '-'}</span>
                          <span className="text-[10px] text-slate-400">{log.actorRole || '-'}</span>
                        </div>
                        <p className="mt-2 text-sm text-slate-700">
                          {isRtl ? log.detailsAr || log.detailsEn || '' : log.detailsEn || log.detailsAr || ''}
                        </p>
                        <p className="mt-1 text-[10px] text-slate-400">
                          {log.resource || '-'} {log.action ? `• ${log.action}` : ''}
                        </p>
                      </div>
                      <div className="flex shrink-0 flex-col items-start gap-1 md:items-end">
                        <span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-[10px] font-bold ${
                          log.status === 'success' ? 'bg-emerald-50 text-emerald-700'
                          : log.status === 'denied' ? 'bg-rose-50 text-rose-700'
                          : 'bg-amber-50 text-amber-700'
                        }`}>
                          {log.status === 'success' ? <CheckCircle2 className="h-3 w-3" /> : log.status === 'denied' ? <ShieldAlert className="h-3 w-3" /> : <Clock className="h-3 w-3" />}
                          {log.status || 'unknown'}
                        </span>
                        <span className="text-[10px] text-slate-400">{log.timestamp || ''}</span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                {isRtl ? 'لا توجد سجلات نشاط حقيقية حالياً.' : 'No real activity records are available yet.'}
              </div>
            )}
          </div>
        )}

        {activeTab === 'history' && (
          <div className="mt-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
            <div className="flex items-center justify-center gap-2 font-semibold text-slate-700">
              <History className="h-4 w-4" />
              <span>{isRtl ? 'لا يوجد سجل تغييرات خلفي حتى الآن.' : 'No backend change-history store is available yet.'}</span>
            </div>
          </div>
        )}

        {activeTab === 'approvals' && (
          <div className="mt-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
            <div className="flex items-center justify-center gap-2 font-semibold text-slate-700">
              <AlertTriangle className="h-4 w-4" />
              <span>{isRtl ? 'لا يوجد طابور موافقات خلفي حقيقي بعد.' : 'No real backend approval queue exists yet.'}</span>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
