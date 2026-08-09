/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, Payment, UserRole } from '../types';
import { translations } from '../translations';
import { useState, useEffect } from 'react';
import { LaravelAPI } from '../api';
import { Search, ShieldAlert, CheckCircle2, RotateCcw, RefreshCcw } from 'lucide-react';

interface PaymentsViewProps {
  language: Language;
  activeRole: UserRole;
}

export default function PaymentsView({ language, activeRole }: PaymentsViewProps) {
  const t = translations[language];
  const [payments, setPayments] = useState<Payment[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [methodFilter, setMethodFilter] = useState('all');
  const [successMsg, setSuccessMsg] = useState('');

  // Financial Manager & Super Admin can manage payments
  const hasPermission = activeRole === 'super_admin' || activeRole === 'financial_manager';

  const loadPayments = async () => {
    setLoading(true);
    try {
      const data = await LaravelAPI.getPayments();
      setPayments(data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadPayments();
  }, []);

  const handleRefund = async (txnId: string) => {
    if (!hasPermission) {
      alert(language === 'en' ? "Access Denied: Your simulated role does not have financial ledger permissions." : "تم رفض الوصول: دورك الحالي لا يمتلك صلاحية تسوية القيود المالية.");
      return;
    }
    const confirmRefund = window.confirm(language === 'en' ? `Are you sure you want to refund transaction ${txnId}?` : `هل أنت متأكد من استرداد مبلغ المعاملة رقم ${txnId}؟`);
    if (!confirmRefund) return;

    try {
      const updated = await LaravelAPI.refundPayment(txnId);
      setPayments(current => current.map(p => p.id === txnId ? updated : p));
      setSuccessMsg(language === 'en' ? `Transaction ${txnId} refunded successfully!` : `تمت تسوية عملية الاسترداد للمدفوعة بنجاح!`);
      setTimeout(() => setSuccessMsg(''), 3000);
    } catch (err) {
      console.error(err);
    }
  };

  const succeededPayments = payments.filter(pay => pay.status === 'succeeded');
  const refundedPayments = payments.filter(pay => pay.status === 'refunded');
  const pendingPayments = payments.filter(pay => pay.status === 'pending');

  const gatewayInflows = succeededPayments.reduce((sum, pay) => sum + pay.amount, 0);
  const refundOutflows = refundedPayments.reduce((sum, pay) => sum + pay.amount, 0);
  const escrowReserves = pendingPayments.reduce((sum, pay) => sum + pay.amount, 0);

  const filteredPayments = payments.filter(pay => {
    const matchesSearch =
      pay.id.toLowerCase().includes(search.toLowerCase()) ||
      pay.user_name.toLowerCase().includes(search.toLowerCase()) ||
      pay.amount.toString().includes(search);

    const matchesMethod = methodFilter === 'all' || pay.method === methodFilter;

    return matchesSearch && matchesMethod;
  });

  return (
    <div className="space-y-6">
      {/* View Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-slate-800">
            {t.payments_title}
          </h2>
          <p className="text-xs text-slate-500">
            {t.payments_subtitle}
          </p>
        </div>
      </div>

      {/* Role restriction callout */}
      {!hasPermission && (
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 flex items-start gap-2.5">
          <ShieldAlert className="h-4.5 w-4.5 text-amber-600 shrink-0" />
          <div>
            <p className="font-bold">{language === 'en' ? 'Limited Role Access (Read-Only Mode)' : 'صلاحيات محدودة لهذا الدور (عرض فقط)'}</p>
            <p className="mt-1 leading-relaxed text-amber-700">
              {language === 'en'
                ? `You are logged in as a ${t[`role_${activeRole}`]}. Only Super Admins and Financial Managers can process gateway refunds and reconcile payment ledger.`
                : `لقد قمت بتسجيل الدخول بصفة ${t[`role_${activeRole}`]}. يمتلك مدير النظام العام والمدير المالي فقط الصلاحية للقيام بتسوية عمليات الاسترداد أو تسوية السجل المالي.`}
            </p>
          </div>
        </div>
      )}

      {successMsg && (
        <div className="rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-xs text-emerald-800 font-semibold animate-fade-in flex items-center gap-1.5">
          <CheckCircle2 className="h-4 w-4 text-emerald-600" />
          <span>{successMsg}</span>
        </div>
      )}

      {/* Stats Cards Row inside Payments */}
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <div className="rounded-xl border border-slate-200/60 bg-white p-4">
          <p className="text-[10px] font-bold text-slate-400 uppercase">{language === 'en' ? 'Gateway Inflow' : 'التدفق المالي عبر البوابات'}</p>
          <p className="text-lg font-bold text-slate-800 mt-1">{gatewayInflows.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} SAR</p>
          <p className="text-[9px] text-emerald-600 font-medium mt-1">● {succeededPayments.length} {language === 'en' ? 'succeeded settlements' : 'عمليات تسوية ناجحة'}</p>
        </div>
        <div className="rounded-xl border border-slate-200/60 bg-white p-4">
          <p className="text-[10px] font-bold text-slate-400 uppercase">{language === 'en' ? 'Refund Outflow' : 'إجمالي المبالغ المستردة'}</p>
          <p className="text-lg font-bold text-slate-800 mt-1">{refundOutflows.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} SAR</p>
          <p className="text-[9px] text-slate-400 font-medium mt-1">{refundedPayments.length} {language === 'en' ? 'refunded transaction(s)' : 'عملية/عمليات مستردة'}</p>
        </div>
        <div className="rounded-xl border border-slate-200/60 bg-white p-4">
          <p className="text-[10px] font-bold text-slate-400 uppercase">{language === 'en' ? 'Escrow Reserves' : 'احتياطي الضمان المالي الموقوف'}</p>
          <p className="text-lg font-bold text-slate-800 mt-1">{escrowReserves.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} SAR</p>
          <p className="text-[9px] text-blue-600 font-semibold mt-1">● {language === 'en' ? 'Pending settlement' : 'في انتظار التسوية'}</p>
        </div>
      </div>

      {/* Controls */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-200 bg-white p-4">
        <div className="relative flex-1">
          <Search className="absolute top-2.5 left-3 h-4 w-4 text-slate-400 rtl:right-3 rtl:left-auto" />
          <input
            type="text"
            placeholder={t.searchPlaceholder}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-xs outline-hidden focus:border-indigo-500 focus:bg-white rtl:pr-9 rtl:pl-3"
          />
        </div>

        <div className="flex items-center gap-2">
          <span className="text-xs text-slate-400 font-semibold">{t.filter}:</span>
          <button
            onClick={() => setMethodFilter('all')}
            className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
              methodFilter === 'all' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
            }`}
          >
            {t.all}
          </button>
          <button
            onClick={() => setMethodFilter('credit_card')}
            className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
              methodFilter === 'credit_card' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
            }`}
          >
            {language === 'en' ? 'Credit Card' : 'بطاقة ائتمان'}
          </button>
          <button
            onClick={() => setMethodFilter('stc_pay')}
            className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
              methodFilter === 'stc_pay' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
            }`}
          >
            STC Pay
          </button>
          <button
            onClick={() => setMethodFilter('apple_pay')}
            className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
              methodFilter === 'apple_pay' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
            }`}
          >
            Apple Pay
          </button>
        </div>
      </div>

      {/* Transaction Table */}
      <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-500 rtl:text-right">
            <thead className="bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-500 uppercase border-b border-slate-100">
              <tr>
                <th className="px-6 py-4">{t.col_txn}</th>
                <th className="px-6 py-4">{language === 'en' ? 'Related Order' : 'رقم الطلب'}</th>
                <th className="px-6 py-4">{language === 'en' ? 'Payer Name' : 'اسم دافع العملية'}</th>
                <th className="px-6 py-4">{t.col_amount}</th>
                <th className="px-6 py-4">{t.col_method}</th>
                <th className="px-6 py-4">{t.status}</th>
                <th className="px-6 py-4 text-center">{t.actions}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
              {loading ? (
                <tr>
                  <td colSpan={7} className="py-10 text-center text-slate-400">
                    <RefreshCcw className="mx-auto h-5 w-5 animate-spin mb-2" />
                    <span>{t.loading}</span>
                  </td>
                </tr>
              ) : filteredPayments.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-10 text-center text-slate-400">
                    {language === 'en' ? 'No transactions match parameters.' : 'لا توجد عمليات دفع تطابق خيارات التصفية.'}
                  </td>
                </tr>
              ) : (
                filteredPayments.map((pay) => (
                  <tr key={pay.id} className="hover:bg-slate-50/50 transition-colors">
                    <td className="px-6 py-4 text-slate-900 font-mono font-bold">{pay.id}</td>
                    <td className="px-6 py-4 text-slate-400 font-bold">#{pay.order_id}</td>
                    <td className="px-6 py-4 font-semibold text-slate-800">{pay.user_name}</td>
                    <td className="px-6 py-4 text-slate-900 font-bold">{pay.amount} SAR</td>
                    <td className="px-6 py-4 font-semibold text-slate-500 uppercase">
                      {pay.method === 'credit_card' ? 'Visa/Mada' : pay.method.replace('_', ' ')}
                    </td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${
                        pay.status === 'succeeded'
                          ? 'bg-emerald-50 text-emerald-700'
                          : pay.status === 'pending'
                          ? 'bg-amber-50 text-amber-700'
                          : pay.status === 'refunded'
                          ? 'bg-blue-50 text-blue-700'
                          : 'bg-rose-50 text-rose-700'
                      }`}>
                        {pay.status === 'succeeded' ? t.status_pay_succeeded : pay.status === 'pending' ? t.status_pay_pending : pay.status === 'refunded' ? t.status_pay_refunded : t.status_pay_failed}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      {hasPermission && pay.status === 'succeeded' ? (
                        <button
                          onClick={() => handleRefund(pay.id)}
                          className="flex items-center gap-1 rounded bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700 transition hover:bg-rose-100 mx-auto"
                        >
                          <RotateCcw className="h-3 w-3" />
                          <span>{language === 'en' ? 'Refund' : 'استرداد الرصيد'}</span>
                        </button>
                      ) : (
                        <span className="text-[10px] text-slate-400 italic">—</span>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
