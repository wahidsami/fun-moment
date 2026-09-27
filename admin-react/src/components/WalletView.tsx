/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import {
  Wallet,
  Search,
  Filter,
  RefreshCw,
  ArrowUpRight,
  ArrowDownLeft,
  Lock,
  Unlock,
  AlertCircle,
  CheckCircle2,
  FileText,
  Clock,
  User as UserIcon,
  ShieldCheck,
  X,
  CreditCard,
  Sliders,
  DollarSign
} from 'lucide-react';
import { Language, UserRole, WalletItem, WalletSummary, WalletLedgerItem, UserWalletDetail } from '../types';
import { LaravelAPI } from '../api';

interface WalletViewProps {
  language: Language;
  activeRole: UserRole;
}

export default function WalletView({ language, activeRole }: WalletViewProps) {
  const isRtl = language === 'ar';

  const [wallets, setWallets] = useState<WalletItem[]>([]);
  const [summary, setSummary] = useState<WalletSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'suspended'>('all');

  // Ledger detail drawer state
  const [selectedUserWallet, setSelectedUserWallet] = useState<UserWalletDetail | null>(null);
  const [loadingLedger, setLoadingLedger] = useState(false);

  // Adjustment modal state
  const [adjustmentTarget, setAdjustmentTarget] = useState<WalletItem | null>(null);
  const [adjustDirection, setAdjustDirection] = useState<'credit' | 'debit'>('credit');
  const [adjustAmount, setAdjustAmount] = useState('');
  const [adjustReason, setAdjustReason] = useState('');
  const [adjusting, setAdjusting] = useState(false);

  // Messages
  const [feedbackMsg, setFeedbackMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const loadWallets = async () => {
    try {
      setLoading(true);
      const res = await LaravelAPI.getWallets({
        search: search || undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
      });
      setWallets(res.wallets);
      setSummary(res.summary);
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err.message || 'Failed to load wallets' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadWallets();
  }, [statusFilter]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    loadWallets();
  };

  const handleOpenLedger = async (userId: number) => {
    try {
      setLoadingLedger(true);
      const res = await LaravelAPI.getUserWallet(userId);
      setSelectedUserWallet(res);
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err.message || 'Failed to load ledger history' });
    } finally {
      setLoadingLedger(false);
    }
  };

  const handleAdjustSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!adjustmentTarget) return;

    const amountNum = parseFloat(adjustAmount);
    if (isNaN(amountNum) || amountNum <= 0) {
      setFeedbackMsg({ type: 'error', text: isRtl ? 'يرجى إدخال مبلغ صحيح أكبر من صفر' : 'Please enter a valid amount greater than zero' });
      return;
    }
    if (!adjustReason.trim()) {
      setFeedbackMsg({ type: 'error', text: isRtl ? 'سبب التعديل إلزامي للرقابة والتدقيق' : 'Adjustment reason is required for audit compliance' });
      return;
    }

    try {
      setAdjusting(true);
      await LaravelAPI.adjustWalletBalance(adjustmentTarget.user_id, amountNum, adjustDirection, adjustReason.trim());
      setFeedbackMsg({
        type: 'success',
        text: isRtl ? 'تم تنفيذ التعديل بنجاح وتسجيل القيد في سجل التدقيق' : 'Balance adjusted successfully with immutable audit ledger entry',
      });
      setAdjustmentTarget(null);
      setAdjustAmount('');
      setAdjustReason('');
      await loadWallets();
      if (selectedUserWallet && selectedUserWallet.user.id === adjustmentTarget.user_id) {
        handleOpenLedger(adjustmentTarget.user_id);
      }
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err.message || 'Failed to adjust balance' });
    } finally {
      setAdjusting(false);
    }
  };

  const handleToggleStatus = async (item: WalletItem) => {
    const nextStatus = item.status === 'active' ? 'suspended' : 'active';
    try {
      await LaravelAPI.updateWalletStatus(item.user_id, nextStatus);
      setFeedbackMsg({
        type: 'success',
        text: isRtl ? `تم تحديث حالة المحفظة إلى ${nextStatus}` : `Wallet status updated to ${nextStatus}`,
      });
      await loadWallets();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err.message || 'Failed to update status' });
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Banner / KPIs */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <Wallet className="h-6 w-6 text-indigo-600" />
            <span>{isRtl ? 'إدارة المحافظ وسجل القيود المالية' : 'Wallet & Ledger Management'}</span>
          </h1>
          <p className="text-xs text-slate-500 mt-1">
            {isRtl
              ? 'مراقبة أرصدة المشترين والمزودين الحقيقية، حركات الضمان المعلق، والقيود المحاسبية المزدوجة'
              : 'Real-time ledger overview, escrow hold balances, and controlled accounting adjustments'}
          </p>
        </div>

        <button
          onClick={loadWallets}
          disabled={loading}
          className="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 shadow-xs transition"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
          <span>{isRtl ? 'تحديث البيانات' : 'Refresh Ledger'}</span>
        </button>
      </div>

      {/* Feedback Alert */}
      {feedbackMsg && (
        <div
          className={`p-3 rounded-lg text-xs flex items-center justify-between border ${
            feedbackMsg.type === 'success'
              ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
              : 'bg-rose-50 text-rose-800 border-rose-200'
          }`}
        >
          <div className="flex items-center gap-2">
            {feedbackMsg.type === 'success' ? (
              <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
            ) : (
              <AlertCircle className="h-4 w-4 text-rose-600 shrink-0" />
            )}
            <span>{feedbackMsg.text}</span>
          </div>
          <button onClick={() => setFeedbackMsg(null)} className="text-slate-400 hover:text-slate-600">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
          <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
            {isRtl ? 'الرصيد المتاح المتداول' : 'Total Circulation'}
          </span>
          <span className="text-2xl font-black text-slate-900 mt-1 block">
            {summary ? summary.total_circulation.toLocaleString() : '0.00'} <span className="text-xs font-bold text-slate-500">SAR</span>
          </span>
          <span className="text-[10px] text-slate-400 mt-0.5 block">{summary?.total_wallets ?? 0} {isRtl ? 'محفظة مسجلة' : 'total wallets'}</span>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
          <span className="text-[11px] font-bold text-amber-600 uppercase tracking-wider block">
            {isRtl ? 'الضمان المعلق (Escrow Hold)' : 'Pending Hold (Escrow)'}
          </span>
          <span className="text-2xl font-black text-amber-700 mt-1 block">
            {summary ? summary.total_pending.toLocaleString() : '0.00'} <span className="text-xs font-bold text-amber-600">SAR</span>
          </span>
          <span className="text-[10px] text-slate-400 mt-0.5 block">{isRtl ? 'أموال معلقة بانتظار إتمام الطلبات أو السحب' : 'Funds reserved for payouts / orders'}</span>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
          <span className="text-[11px] font-bold text-emerald-600 uppercase tracking-wider block">
            {isRtl ? 'إجمالي أرباح المزودين' : 'Lifetime Provider Earnings'}
          </span>
          <span className="text-2xl font-black text-emerald-700 mt-1 block">
            {summary ? summary.total_earned.toLocaleString() : '0.00'} <span className="text-xs font-bold text-emerald-600">SAR</span>
          </span>
          <span className="text-[10px] text-slate-400 mt-0.5 block">{isRtl ? 'أرباح الخدمات المنفذة المكتملة' : 'Completed service volume'}</span>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
          <span className="text-[11px] font-bold text-indigo-600 uppercase tracking-wider block">
            {isRtl ? 'المحافظ النشطة' : 'Active Wallets'}
          </span>
          <span className="text-2xl font-black text-indigo-700 mt-1 block">
            {summary?.active_wallets ?? 0}
          </span>
          <span className="text-[10px] text-slate-400 mt-0.5 block">{summary?.frozen_wallets ?? 0} {isRtl ? 'محفظة معلقة/مجمدة' : 'suspended'}</span>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <form onSubmit={handleSearchSubmit} className="relative w-full sm:w-80">
          <Search className={`absolute top-2.5 h-4 w-4 text-slate-400 ${isRtl ? 'right-3' : 'left-3'}`} />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={isRtl ? 'بحث بالاسم، البريد أو الهاتف...' : 'Search by name, email or phone...'}
            className={`w-full py-2 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-500 ${
              isRtl ? 'pr-9 pl-3 text-right' : 'pl-9 pr-3 text-left'
            }`}
          />
        </form>

        <div className="flex items-center gap-2 w-full sm:w-auto">
          <Filter className="h-4 w-4 text-slate-400" />
          <select
            value={statusFilter}
            onChange={(e: any) => setStatusFilter(e.target.value)}
            className="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-700 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          >
            <option value="all">{isRtl ? 'جميع الحالات' : 'All Statuses'}</option>
            <option value="active">{isRtl ? 'النشطة فقط' : 'Active Only'}</option>
            <option value="suspended">{isRtl ? 'المعلقة فقط' : 'Suspended Only'}</option>
          </select>
        </div>
      </div>

      {/* Wallets Table */}
      <div className="bg-white rounded-xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[10px]">
              <tr>
                <th className="py-3 px-4">{isRtl ? 'المستخدم' : 'User'}</th>
                <th className="py-3 px-4">{isRtl ? 'الدور' : 'Role'}</th>
                <th className="py-3 px-4">{isRtl ? 'الرصيد المتاح' : 'Available Balance'}</th>
                <th className="py-3 px-4">{isRtl ? 'الضمان المعلق' : 'Escrow Hold'}</th>
                <th className="py-3 px-4">{isRtl ? 'إجمالي الأرباح' : 'Lifetime Earned'}</th>
                <th className="py-3 px-4">{isRtl ? 'الحالة' : 'Status'}</th>
                <th className="py-3 px-4 text-right">{isRtl ? 'الإجراءات' : 'Actions'}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-slate-400">
                    <RefreshCw className="h-5 w-5 animate-spin mx-auto mb-2 text-indigo-500" />
                    <span>{isRtl ? 'جاري تحميل سجلات المحافظ...' : 'Loading wallet balances...'}</span>
                  </td>
                </tr>
              ) : wallets.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-slate-400">
                    <Wallet className="h-8 w-8 mx-auto mb-2 text-slate-300" />
                    <span>{isRtl ? 'لا توجد محافظ مطابقة' : 'No wallets found'}</span>
                  </td>
                </tr>
              ) : (
                wallets.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="py-3 px-4">
                      <div className="font-semibold text-slate-800">{item.user_name}</div>
                      <div className="text-[11px] text-slate-400">{item.user_email || item.user_phone}</div>
                    </td>
                    <td className="py-3 px-4">
                      <span
                        className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${
                          item.role === 'seller'
                            ? 'bg-purple-50 text-purple-700 border border-purple-200'
                            : 'bg-blue-50 text-blue-700 border border-blue-200'
                        }`}
                      >
                        {item.role === 'seller' ? (isRtl ? 'مزود خدمة' : 'Provider') : (isRtl ? 'عميل/مشتري' : 'Buyer')}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      <span className="font-bold text-slate-900 text-sm">
                        {item.balance.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                      </span>{' '}
                      <span className="text-[10px] font-semibold text-slate-400">{item.currency}</span>
                    </td>
                    <td className="py-3 px-4">
                      {item.pending_balance > 0 ? (
                        <span className="font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200/60 text-xs">
                          {item.pending_balance.toLocaleString(undefined, { minimumFractionDigits: 2 })} {item.currency}
                        </span>
                      ) : (
                        <span className="text-slate-400">0.00 {item.currency}</span>
                      )}
                    </td>
                    <td className="py-3 px-4 text-slate-600 font-medium">
                      {item.total_earned > 0 ? `${item.total_earned.toLocaleString()} ${item.currency}` : '—'}
                    </td>
                    <td className="py-3 px-4">
                      <span
                        className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                          item.status === 'active'
                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                            : 'bg-rose-50 text-rose-700 border border-rose-200'
                        }`}
                      >
                        <span className={`h-1.5 w-1.5 rounded-full ${item.status === 'active' ? 'bg-emerald-500' : 'bg-rose-500'}`} />
                        {item.status === 'active' ? (isRtl ? 'نشطة' : 'Active') : (isRtl ? 'مجمدة' : 'Suspended')}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => handleOpenLedger(item.user_id)}
                          className="px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 rounded border border-indigo-200 transition"
                          title={isRtl ? 'عرض سجل القيود' : 'Inspect Ledger'}
                        >
                          <FileText className="h-3.5 w-3.5 inline mr-1" />
                          <span>{isRtl ? 'السجل' : 'Ledger'}</span>
                        </button>

                        <button
                          onClick={() => {
                            setAdjustmentTarget(item);
                            setAdjustDirection('credit');
                            setAdjustAmount('');
                            setAdjustReason('');
                          }}
                          className="px-2.5 py-1 text-xs font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-100 rounded border border-slate-300 transition"
                          title={isRtl ? 'تعديل الرصيد' : 'Adjust Balance'}
                        >
                          <Sliders className="h-3.5 w-3.5 inline mr-1" />
                          <span>{isRtl ? 'تعديل' : 'Adjust'}</span>
                        </button>

                        <button
                          onClick={() => handleToggleStatus(item)}
                          className={`p-1 rounded border transition ${
                            item.status === 'active'
                              ? 'text-amber-600 hover:bg-amber-50 border-amber-200'
                              : 'text-emerald-600 hover:bg-emerald-50 border-emerald-200'
                          }`}
                          title={item.status === 'active' ? (isRtl ? 'تجميد المحفظة' : 'Suspend Wallet') : (isRtl ? 'تفعيل المحفظة' : 'Activate Wallet')}
                        >
                          {item.status === 'active' ? <Lock className="h-3.5 w-3.5" /> : <Unlock className="h-3.5 w-3.5" />}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Ledger History Drawer Modal */}
      {selectedUserWallet && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            {/* Header */}
            <div className="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
                  <FileText className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-sm">
                    {isRtl ? 'سجل العمليات والقيود المحاسبية' : 'Double-Entry Accounting Ledger'}
                  </h3>
                  <p className="text-xs text-slate-500">
                    {selectedUserWallet.user.name} ({selectedUserWallet.user.email}) —{' '}
                    <span className="font-bold text-indigo-600">
                      {selectedUserWallet.wallet.balance.toLocaleString()} {selectedUserWallet.wallet.currency}
                    </span>
                  </p>
                </div>
              </div>
              <button
                onClick={() => setSelectedUserWallet(null)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200/50"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Ledger List */}
            <div className="p-4 overflow-y-auto space-y-3 flex-1 text-xs">
              {selectedUserWallet.ledger.length === 0 ? (
                <div className="py-12 text-center text-slate-400">
                  <Clock className="h-8 w-8 mx-auto mb-2 text-slate-300" />
                  <span>{isRtl ? 'لا توجد قيود مسجلة لهذه المحفظة بعد' : 'No ledger entries for this wallet yet.'}</span>
                </div>
              ) : (
                selectedUserWallet.ledger.map((entry) => (
                  <div
                    key={entry.id}
                    className="p-3.5 rounded-xl border border-slate-200 bg-white shadow-2xs space-y-2"
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <span
                          className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${
                            entry.entry_type === 'credit'
                              ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                              : entry.entry_type === 'debit'
                              ? 'bg-rose-50 text-rose-700 border border-rose-200'
                              : entry.entry_type === 'hold'
                              ? 'bg-amber-50 text-amber-700 border border-amber-200'
                              : entry.entry_type === 'release'
                              ? 'bg-blue-50 text-blue-700 border border-blue-200'
                              : 'bg-purple-50 text-purple-700 border border-purple-200'
                          }`}
                        >
                          {entry.entry_type}
                        </span>
                        <span className="font-mono text-[10px] text-slate-400">{entry.transaction_id}</span>
                      </div>
                      <span className="font-black text-sm text-slate-900">
                        {entry.entry_type === 'credit' || entry.entry_type === 'release' ? '+' : entry.entry_type === 'debit' ? '-' : ''}
                        {entry.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })} SAR
                      </span>
                    </div>

                    <div className="flex items-center justify-between text-[11px] text-slate-500">
                      <span>{isRtl ? entry.description_ar || entry.description_en : entry.description_en}</span>
                      <span className="font-mono text-slate-400">{entry.created_at}</span>
                    </div>

                    <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400 font-mono">
                      <span>Balance: {entry.balance_before.toLocaleString()} ➔ {entry.balance_after.toLocaleString()} SAR</span>
                      {entry.admin_name && (
                        <span className="text-indigo-600 font-semibold">Admin: {entry.admin_name} ({entry.admin_note})</span>
                      )}
                    </div>
                  </div>
                ))
              )}
            </div>

            {/* Footer */}
            <div className="p-3 border-t border-slate-200 bg-slate-50 flex justify-end">
              <button
                onClick={() => setSelectedUserWallet(null)}
                className="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-100"
              >
                {isRtl ? 'إغلاق' : 'Close'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Controlled Adjustment Modal */}
      {adjustmentTarget && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div className="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
              <div className="flex items-center gap-2">
                <Sliders className="h-5 w-5 text-indigo-600" />
                <h3 className="font-bold text-slate-900 text-sm">
                  {isRtl ? 'تعديل رصيد المحفظة إدارياً' : 'Controlled Balance Adjustment'}
                </h3>
              </div>
              <button
                onClick={() => setAdjustmentTarget(null)}
                className="text-slate-400 hover:text-slate-600"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleAdjustSubmit} className="p-5 space-y-4 text-xs">
              <div className="p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-1">
                <div className="text-[11px] text-slate-500">{isRtl ? 'المستخدم المستهدف:' : 'Target Account:'}</div>
                <div className="font-bold text-slate-900">{adjustmentTarget.user_name}</div>
                <div className="text-slate-600 font-mono text-[11px]">
                  {isRtl ? 'الرصيد المتاح الحالي:' : 'Current Balance:'} {adjustmentTarget.balance.toLocaleString()} {adjustmentTarget.currency}
                </div>
              </div>

              {/* Direction selector */}
              <div>
                <label className="block font-semibold text-slate-700 mb-1.5">
                  {isRtl ? 'نوع القيد المحاسبي' : 'Adjustment Type'}
                </label>
                <div className="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    onClick={() => setAdjustDirection('credit')}
                    className={`py-2 px-3 rounded-lg font-bold border transition text-center ${
                      adjustDirection === 'credit'
                        ? 'bg-emerald-50 border-emerald-300 text-emerald-800'
                        : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'
                    }`}
                  >
                    + {isRtl ? 'إيداع / إضافة (Credit)' : 'Credit (Deposit)'}
                  </button>

                  <button
                    type="button"
                    onClick={() => setAdjustDirection('debit')}
                    className={`py-2 px-3 rounded-lg font-bold border transition text-center ${
                      adjustDirection === 'debit'
                        ? 'bg-rose-50 border-rose-300 text-rose-800'
                        : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'
                    }`}
                  >
                    - {isRtl ? 'خصم / استرداد (Debit)' : 'Debit (Deduction)'}
                  </button>
                </div>
              </div>

              {/* Amount */}
              <div>
                <label className="block font-semibold text-slate-700 mb-1">
                  {isRtl ? 'المبلغ بالريال السعودي (SAR)' : 'Amount (SAR)'}
                </label>
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  required
                  value={adjustAmount}
                  onChange={(e) => setAdjustAmount(e.target.value)}
                  placeholder="0.00"
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono font-bold"
                />
              </div>

              {/* Reason */}
              <div>
                <label className="block font-semibold text-slate-700 mb-1">
                  {isRtl ? 'السبب الإداري / المرجعية (إلزامي للرقابة المالية)' : 'Audit / Regulatory Justification (Required)'}
                </label>
                <textarea
                  rows={2}
                  required
                  value={adjustReason}
                  onChange={(e) => setAdjustReason(e.target.value)}
                  placeholder={isRtl ? 'مثال: تسوية نزاع الطلب #1052 أو تعويض رسوم الدفع' : 'e.g. Dispute settlement for Order #1052 or fee refund'}
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
              </div>

              <div className="pt-2 flex items-center justify-end gap-2 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setAdjustmentTarget(null)}
                  className="px-3 py-2 font-semibold text-slate-600 hover:text-slate-800"
                >
                  {isRtl ? 'إلغاء' : 'Cancel'}
                </button>
                <button
                  type="submit"
                  disabled={adjusting}
                  className="px-4 py-2 font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-xs transition disabled:opacity-50"
                >
                  {adjusting ? (isRtl ? 'جاري التنفيذ...' : 'Processing...') : (isRtl ? 'تأكيد القيد المحاسبي' : 'Confirm Adjustment')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
