/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useEffect, useMemo, useState } from 'react';
import { Language, AddonModuleKey } from '../types';
import { AddonsService } from '../services/api';
import {
  Award,
  CheckCircle2,
  Hammer,
  Lock,
  MessageSquare,
  RefreshCw,
  ShieldAlert,
  Unlock,
  Wallet,
} from 'lucide-react';

interface AddonLockedViewProps {
  moduleKey: AddonModuleKey;
  language: Language;
  isLocked: boolean;
  onToggleLock: (key: AddonModuleKey) => void;
}

const MODULE_META: Record<AddonModuleKey, {
  titleEn: string;
  titleAr: string;
  descriptionEn: string;
  descriptionAr: string;
  icon: typeof Wallet;
}> = {
  wallet: {
    titleEn: 'Wallet',
    titleAr: 'المحفظة',
    descriptionEn: 'Live escrow balances, deposits, refunds, and payout summaries from Laravel.',
    descriptionAr: 'أرصدة الضمان والإيداعات والاستردادات وملخصات السحب من Laravel.',
    icon: Wallet,
  },
  chat: {
    titleEn: 'Live Chat',
    titleAr: 'الدردشة المباشرة',
    descriptionEn: 'Moderated buyer and seller conversations, loaded from the backend.',
    descriptionAr: 'محادثات المشتري والبائع الخاضعة للمراقبة، وتُحمّل من الخادم.',
    icon: MessageSquare,
  },
  jobs: {
    titleEn: 'Jobs',
    titleAr: 'الوظائف',
    descriptionEn: 'Buyer job posts and bidding workflow from the shared database.',
    descriptionAr: 'طلبات المشاريع ومسار العروض من قاعدة البيانات المشتركة.',
    icon: Hammer,
  },
  subscription: {
    titleEn: 'Subscription',
    titleAr: 'الاشتراكات',
    descriptionEn: 'Seller tiers, limits, and active subscribers from Laravel.',
    descriptionAr: 'خطط البائعين والحدود والمشتركين الفعليين من Laravel.',
    icon: Award,
  },
};

export default function AddonLockedView({ moduleKey, language, isLocked, onToggleLock }: AddonLockedViewProps) {
  const isRtl = language === 'ar';
  const meta = MODULE_META[moduleKey];
  const Icon = meta.icon;

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [featureFlags, setFeatureFlags] = useState<any>(null);
  const [payload, setPayload] = useState<any>(null);

  const moduleState = useMemo(() => {
    if (!featureFlags?.modules || !Array.isArray(featureFlags.modules)) {
      return null;
    }

    return featureFlags.modules.find((item: any) => {
      const key = String(item?.key ?? '').toLowerCase();
      const moduleName = String(item?.module_name ?? '').toLowerCase();
      return key === moduleKey || moduleName === moduleKey;
    });
  }, [featureFlags, moduleKey]);

  const loadModuleData = async () => {
    setLoading(true);
    setError('');
    setPayload(null);

    try {
      const flags = await AddonsService.getFeatureFlags(language);
      setFeatureFlags(flags?.data ?? flags);

      if (moduleKey === 'wallet') {
        const result = await AddonsService.getWalletSummary(language);
        setPayload(result?.data ?? result);
      } else if (moduleKey === 'chat') {
        const result = await AddonsService.listChatRooms(language);
        setPayload(result?.data ?? result);
      } else if (moduleKey === 'jobs') {
        const result = await AddonsService.listJobs(language);
        setPayload(result?.data ?? result);
      } else if (moduleKey === 'subscription') {
        const result = await AddonsService.listSubscriptionTiers(language);
        setPayload(result?.data ?? result);
      }
    } catch (err) {
      console.error(err);
      setError(isRtl ? 'تعذر تحميل بيانات الإضافة من الخادم.' : 'Could not load module data from the backend.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadModuleData();
  }, [moduleKey, language]);

  const liveEnabled = typeof moduleState?.enabled === 'boolean' ? moduleState.enabled : !isLocked;
  const lockStateLabel = liveEnabled
    ? (isRtl ? 'مفعل' : 'Enabled')
    : (isRtl ? 'مقفل' : 'Locked');

  const renderDataBlock = () => {
    if (loading) {
      return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-500">
          <div className="flex items-center gap-2">
            <RefreshCw className="h-4 w-4 animate-spin" />
            <span>{isRtl ? 'جاري تحميل البيانات الحية...' : 'Loading live module data...'}</span>
          </div>
        </div>
      );
    }

    if (error) {
      return (
        <div className="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700">
          <div className="flex items-center gap-2 font-semibold">
            <ShieldAlert className="h-4 w-4" />
            <span>{error}</span>
          </div>
        </div>
      );
    }

    if (moduleKey === 'wallet') {
      const summary = payload ?? {};
      const transactions = Array.isArray(summary.recent_transactions) ? summary.recent_transactions : [];
      return (
        <div className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-3">
            <InfoCard label={isRtl ? 'الرصيد المعلق' : 'Escrow Balance'} value={summary.total_escrow_balance ?? 0} />
            <InfoCard label={isRtl ? 'إجمالي المسحوبات' : 'Total Withdrawn'} value={summary.total_withdrawn_amount ?? 0} />
            <InfoCard label={isRtl ? 'التغييرات النشطة' : 'Module Status'} value={summary.module_enabled ? (isRtl ? 'مفعل' : 'Enabled') : (isRtl ? 'مقفل' : 'Locked')} />
          </div>
          {transactions.length > 0 ? (
            <ListPanel
              title={isRtl ? 'آخر العمليات' : 'Recent Transactions'}
              items={transactions.slice(0, 5).map((txn: any) => ({
                title: txn.reference || txn.id || '',
                subtitle: `${txn.payment_gateway ?? txn.gateway ?? ''} • ${txn.payment_status ?? txn.status ?? ''}`,
                value: txn.amount ?? '',
              }))}
            />
          ) : (
            <EmptyState text={isRtl ? 'لا توجد عمليات محفظة حالية.' : 'No wallet transactions are available yet.'} />
          )}
        </div>
      );
    }

    if (moduleKey === 'chat') {
      const rooms = Array.isArray(payload) ? payload : [];
      return rooms.length > 0 ? (
        <ListPanel
          title={isRtl ? 'الغرف المباشرة' : 'Live Chat Rooms'}
          items={rooms.slice(0, 6).map((room: any) => ({
            title: room.buyer_name || room.buyer || room.id || '',
            subtitle: room.seller_name || room.seller || room.last_message || '',
            value: `${room.unread_count ?? room.unreadCount ?? 0}`,
          }))}
        />
      ) : (
        <EmptyState text={isRtl ? 'لا توجد غرف دردشة متاحة حالياً.' : 'No chat rooms are available yet.'} />
      );
    }

    if (moduleKey === 'jobs') {
      const jobs = Array.isArray(payload) ? payload : [];
      return jobs.length > 0 ? (
        <ListPanel
          title={isRtl ? 'طلبات المشاريع' : 'Job Posts'}
          items={jobs.slice(0, 6).map((job: any) => ({
            title: job.title_en || job.title || '',
            subtitle: job.buyer_name || job.client || '',
            value: `${job.budget_sar ?? job.budget ?? 0}`,
          }))}
        />
      ) : (
        <EmptyState text={isRtl ? 'لا توجد وظائف منشورة بعد.' : 'No jobs have been posted yet.'} />
      );
    }

    const tiers = Array.isArray(payload) ? payload : [];
    return tiers.length > 0 ? (
      <ListPanel
        title={isRtl ? 'خطط الاشتراك' : 'Subscription Tiers'}
        items={tiers.slice(0, 6).map((tier: any) => ({
          title: tier.name_en || tier.name || tier.id || '',
          subtitle: tier.max_listings !== undefined ? `${tier.max_listings} listings` : '',
          value: `${tier.price_monthly_sar ?? tier.price ?? 0}`,
        }))}
      />
    ) : (
      <EmptyState text={isRtl ? 'لا توجد خطط اشتراك حالياً.' : 'No subscription tiers are available yet.'} />
    );
  };

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div className="flex items-start gap-4">
          <div className="rounded-2xl bg-indigo-50 p-3 text-indigo-600">
            <Icon className="h-6 w-6" />
          </div>
          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
              {isRtl ? 'إضافة حية' : 'Live Add-on'}
            </p>
            <h2 className="text-xl font-black text-slate-900">
              {isRtl ? meta.titleAr : meta.titleEn}
            </h2>
            <p className="mt-1 max-w-2xl text-sm text-slate-500">
              {isRtl ? meta.descriptionAr : meta.descriptionEn}
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold ${liveEnabled ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
            {liveEnabled ? <CheckCircle2 className="h-4 w-4" /> : <Lock className="h-4 w-4" />}
            {lockStateLabel}
          </span>
          <button
            type="button"
            onClick={() => onToggleLock(moduleKey)}
            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
          >
            {liveEnabled ? <Unlock className="h-4 w-4" /> : <Lock className="h-4 w-4" />}
            {liveEnabled ? (isRtl ? 'قفل الإضافة' : 'Lock module') : (isRtl ? 'فتح الإضافة' : 'Unlock module')}
          </button>
          <button
            type="button"
            onClick={loadModuleData}
            className="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2 text-xs font-bold text-white transition hover:bg-slate-800"
          >
            <RefreshCw className="h-4 w-4" />
            {isRtl ? 'تحديث البيانات' : 'Refresh data'}
          </button>
        </div>
      </div>

      {!liveEnabled && (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          {isRtl
            ? 'هذه الإضافة مقفلة حالياً من الخادم. لن يتم عرض بيانات أو إجراءات إضافية حتى يتم تفعيلها.'
            : 'This add-on is currently disabled on the backend. No data or actions are exposed until it is enabled.'}
        </div>
      )}

      {renderDataBlock()}
    </div>
  );
}

function InfoCard({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{label}</p>
      <p className="mt-2 text-lg font-black text-slate-900">{value}</p>
    </div>
  );
}

function ListPanel({ title, items }: { title: string; items: Array<{ title: string; subtitle: string; value: string | number }> }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h3 className="mb-4 text-sm font-bold text-slate-800">{title}</h3>
      <div className="divide-y divide-slate-100">
        {items.map((item, index) => (
          <div key={`${item.title}-${index}`} className="flex items-center justify-between gap-4 py-3">
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-slate-800">{item.title}</p>
              <p className="truncate text-xs text-slate-500">{item.subtitle}</p>
            </div>
            <p className="shrink-0 text-sm font-black text-slate-900">{item.value}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

function EmptyState({ text }: { text: string }) {
  return (
    <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
      {text}
    </div>
  );
}
