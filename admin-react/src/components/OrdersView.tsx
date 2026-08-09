/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, Order, UserRole } from '../types';
import { translations } from '../translations';
import { useState, useEffect } from 'react';
import { LaravelAPI } from '../api';
import {
  Search,
  Info,
  ShieldAlert,
  CheckCircle2,
  ShoppingBag,
  Eye,
  RefreshCcw,
  DollarSign,
  AlertCircle,
  Clock,
  ArrowRight,
  Sparkles
} from 'lucide-react';

interface OrdersViewProps {
  language: Language;
  activeRole: UserRole;
}

// Order timeline log model
interface WorkflowLog {
  status: string;
  timestamp: string;
  actorEn: string;
  actorAr: string;
  messageEn: string;
  messageAr: string;
}

export default function OrdersView({ language, activeRole }: OrdersViewProps) {
  const t = translations[language];
  const isRtl = language === 'ar';

  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [successMessage, setSuccessMessage] = useState('');

  // Extended payment info matching gateways
  const [orderGateways] = useState<Record<number, { gateway: string; fee: number; txId: string }>>({});

  // Dynamic workflow simulation timeline logs
  const [orderTimelines, setOrderTimelines] = useState<Record<number, WorkflowLog[]>>({});

  // Permission Check
  const hasPermission = activeRole === 'super_admin' || activeRole === 'moderator' || activeRole === 'financial_manager';

  const loadOrders = async () => {
    setLoading(true);
    try {
      const data = await LaravelAPI.getOrders();
      setOrders(data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadOrders();
  }, []);

  const handleUpdateStatus = async (id: number, status: 'completed' | 'cancelled') => {
    if (!hasPermission) {
      alert(language === 'en' ? "Access Denied: You do not have permission to manage financials." : "تم رفض الوصول: لا تملك الصلاحيات الكافية لتسوية القيود المالية.");
      return;
    }

    try {
      const updated = await LaravelAPI.updateOrderStatus(id, status);
      setOrders(orders.map(o => o.id === id ? updated : o));
      if (selectedOrder && selectedOrder.id === id) {
        setSelectedOrder(updated);
      }

      // Add timeline entry
      const logEntry: WorkflowLog = {
        status,
        timestamp: new Date().toISOString().replace('T', ' ').substring(0, 16),
        actorEn: `Admin (${activeRole.replace('_', ' ')})`,
        actorAr: `الإدارة (${t[`role_${activeRole}`]})`,
        messageEn: status === 'completed' ? 'Escrow released manually by Administrator.' : 'Order cancelled and funds returned to buyer wallet.',
        messageAr: status === 'completed' ? 'تم الإفراج اليدوي عن مبلغ الضمان للبائع بواسطة الإدارة.' : 'تم إلغاء الطلب بالكامل وإعادة الرصيد لمحفظة العميل.'
      };

      const existingTimeline = orderTimelines[id] || [];
      setOrderTimelines({
        ...orderTimelines,
        [id]: [...existingTimeline, logEntry]
      });

      showSuccess(
        language === 'en' 
          ? `Order set to ${status}. Escrow ledger settled!` 
          : `تم تغيير حالة الطلب لتصبح ${status === 'completed' ? 'مكتملة' : 'ملغية'} وتسوية القيود!`
      );
    } catch (err) {
      console.error(err);
    }
  };

  const showSuccess = (msg: string) => {
    setSuccessMessage(msg);
    setTimeout(() => setSuccessMessage(''), 4000);
  };

  const filteredOrders = orders.filter(order => {
    const title = language === 'en' ? order.service_title_en : order.service_title_ar;
    const matchesSearch =
      title.toLowerCase().includes(search.toLowerCase()) ||
      order.buyer_name.toLowerCase().includes(search.toLowerCase()) ||
      order.seller_name.toLowerCase().includes(search.toLowerCase());

    const matchesStatus = statusFilter === 'all' || order.status === statusFilter;

    return matchesSearch && matchesStatus;
  });

  return (
    <div className="space-y-6">
      {/* View Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-slate-800">
            {t.orders_title}
          </h2>
          <p className="text-xs text-slate-500">
            {t.orders_subtitle}
          </p>
        </div>
      </div>

      {/* Escrow System Guarantee Indicator */}
      <div className="rounded-xl border border-blue-100 bg-blue-50/50 p-4 text-xs text-blue-800 flex items-start gap-2.5">
        <Info className="h-4.5 w-4.5 text-blue-600 shrink-0 mt-0.5" />
        <div>
          <p className="font-bold">{language === 'en' ? 'Escrow Vault Hold Guarantee' : 'نظام الضمان المالي ومراقبة الدفعات والوساطة'}</p>
          <p className="mt-1 leading-relaxed text-blue-700">
            {language === 'en'
              ? 'All client transactions are securely locked in the vault. Once the booking delivers successfully, the admin releases the escrow. For disputes, administrators can manually refund.'
              : 'تتم كافة المعاملات عبر نظام الضمان المالي الموثق. تظل المبالغ محجوزة بأمان حتى يثبت تسلم الخدمة أو يقرر المشرف رد المستحقات وإلغاء المعاملة.'}
          </p>
        </div>
      </div>

      {successMessage && (
        <div className="rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-xs text-emerald-800 font-semibold animate-fade-in flex items-center gap-1.5">
          <CheckCircle2 className="h-4 w-4 text-emerald-600" />
          <span>{successMessage}</span>
        </div>
      )}

      {/* Search and Filters */}
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
            onClick={() => setStatusFilter('all')}
            className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
              statusFilter === 'all' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
            }`}
          >
            {t.all}
          </button>
          <button
            onClick={() => setStatusFilter('pending')}
            className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
              statusFilter === 'pending' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
            }`}
          >
            {t.status_order_pending}
          </button>
          <button
            onClick={() => setStatusFilter('in_progress')}
            className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
              statusFilter === 'in_progress' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
            }`}
          >
            {t.status_order_in_progress}
          </button>
          <button
            onClick={() => setStatusFilter('completed')}
            className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
              statusFilter === 'completed' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
            }`}
          >
            {t.status_order_completed}
          </button>
        </div>
      </div>

      {/* Orders Table */}
      <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-500 rtl:text-right">
            <thead className="bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-500 uppercase border-b border-slate-100">
              <tr>
                <th className="px-6 py-4">ID</th>
                <th className="px-6 py-4">{language === 'en' ? 'Marketplace Order' : 'اسم الطلب بالسوق'}</th>
                <th className="px-6 py-4">{t.col_buyer}</th>
                <th className="px-6 py-4">{t.col_seller}</th>
                <th className="px-6 py-4">{t.col_amount}</th>
                <th className="px-6 py-4">{language === 'en' ? 'Payment Status' : 'حالة الدفع مالي'}</th>
                <th className="px-6 py-4">{t.status}</th>
                <th className="px-6 py-4 text-center">{t.actions}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
              {loading ? (
                <tr>
                  <td colSpan={8} className="py-10 text-center text-slate-400">
                    <RefreshCcw className="mx-auto h-5 w-5 animate-spin mb-2" />
                    <span>{t.loading}</span>
                  </td>
                </tr>
              ) : filteredOrders.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-10 text-center text-slate-400">
                    {language === 'en' ? 'No orders match criteria.' : 'لا توجد طلبات تطابق معايير التصفية.'}
                  </td>
                </tr>
              ) : (
                filteredOrders.map((order) => (
                  <tr key={order.id} className="hover:bg-slate-50/50 transition-colors">
                    <td className="px-6 py-4 text-slate-400 font-bold">#{order.id}</td>
                    <td className="px-6 py-4">
                      <div className="font-bold text-slate-800">
                        {language === 'en' ? order.service_title_en : order.service_title_ar}
                      </div>
                      <div className="text-[10px] text-slate-400 mt-0.5">{order.created_at}</div>
                    </td>
                    <td className="px-6 py-4 font-semibold text-slate-700">{order.buyer_name}</td>
                    <td className="px-6 py-4 font-semibold text-slate-700">{order.seller_name}</td>
                    <td className="px-6 py-4 text-slate-900 font-bold">{order.amount} SAR</td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${
                        order.payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700' : order.payment_status === 'unpaid' ? 'bg-slate-100 text-slate-600' : 'bg-rose-50 text-rose-700'
                      }`}>
                        {order.payment_status === 'paid' ? (language === 'en' ? 'Paid' : 'مدفوع') : order.payment_status === 'unpaid' ? (language === 'en' ? 'Unpaid' : 'غير مدفوع') : (language === 'en' ? 'Refunded' : 'مسترد')}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${
                        order.status === 'completed'
                          ? 'bg-emerald-50 text-emerald-700'
                          : order.status === 'in_progress'
                          ? 'bg-blue-50 text-blue-700'
                          : order.status === 'pending'
                          ? 'bg-amber-50 text-amber-700'
                          : 'bg-rose-50 text-rose-700'
                      }`}>
                        {order.status === 'completed' ? t.status_order_completed : order.status === 'in_progress' ? t.status_order_in_progress : order.status === 'pending' ? t.status_order_pending : t.status_order_cancelled}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <button
                        onClick={() => setSelectedOrder(order)}
                        className="rounded p-1 text-slate-500 hover:bg-slate-100 transition-colors"
                      >
                        <Eye className="h-4 w-4" />
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Order Details Audit Drawer Modal */}
      {selectedOrder && (() => {
        const subtotal = selectedOrder.amount;
        // 15% platform commission
        const commission = Math.round(subtotal * 0.15);
        // 15% VAT calculation
        const vat = Math.round(subtotal * 0.15);
        const payout = subtotal - commission;

        const gatewayInfo = orderGateways[selectedOrder.id] || { gateway: 'N/A', fee: 0, txId: '' };
        const timeline = orderTimelines[selectedOrder.id] || [];

        return (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-xl rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl animate-scale-in max-h-[90vh] overflow-y-auto">
              <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <span className="text-[10px] font-bold text-slate-400 uppercase">
                  {language === 'en' ? 'Detailed Financial & Order Audit' : 'التدقيق المالي والتشغيلي الكامل للطلب'} #{selectedOrder.id}
                </span>
                <button
                  onClick={() => setSelectedOrder(null)}
                  className="text-slate-400 hover:text-slate-600 text-sm font-bold"
                >
                  ✕
                </button>
              </div>

              <div className="space-y-4">
                {/* Booked Service block */}
                <div className="rounded-xl bg-slate-50 p-4 border border-slate-100">
                  <p className="text-[10px] text-slate-400 font-bold uppercase">{language === 'en' ? 'Service Booked' : 'الخدمة المحجوزة'}</p>
                  <p className="text-sm font-bold text-slate-800 mt-1">
                    {language === 'en' ? selectedOrder.service_title_en : selectedOrder.service_title_ar}
                  </p>
                </div>

                {/* Buyer / Seller details */}
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <h4 className="text-[10px] text-slate-400 font-bold uppercase">{t.col_buyer}</h4>
                    <p className="text-xs font-bold text-slate-700 mt-1">{selectedOrder.buyer_name}</p>
                    <p className="text-[9px] text-slate-400">ID: #{selectedOrder.buyer_id}</p>
                  </div>
                  <div>
                    <h4 className="text-[10px] text-slate-400 font-bold uppercase">{t.col_seller}</h4>
                    <p className="text-xs font-bold text-slate-700 mt-1">{selectedOrder.seller_name}</p>
                    <p className="text-[9px] text-slate-400">ID: #{selectedOrder.seller_id}</p>
                  </div>
                </div>

                {/* Gateways & Commission details */}
                <div className="rounded-xl border border-slate-100 p-4 space-y-3 bg-slate-50/50">
                  <h4 className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    {language === 'en' ? 'Bilingual Invoice Breakdown' : 'بيان تفصيلي ببيانات الفاتورة والرسوم'}
                  </h4>

                  <div className="space-y-1.5 text-xs">
                    <div className="flex justify-between">
                      <span className="text-slate-400 font-semibold">{language === 'en' ? 'Subtotal (Gross Escrow):' : 'مبلغ الحجز المالي الإجمالي:'}</span>
                      <span className="font-bold text-slate-800">{subtotal} SAR</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-400 font-semibold">{language === 'en' ? 'Platform Fee Commission (15%):' : 'عمولة المنصة الإدارية (15%):'}</span>
                      <span className="font-bold text-rose-600">-{commission} SAR</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-400 font-semibold">{language === 'en' ? 'Estimated VAT Taxes (15%):' : 'ضريبة القيمة المضافة المحسوبة (15%):'}</span>
                      <span className="font-bold text-slate-500">{vat} SAR</span>
                    </div>
                    <div className="flex justify-between border-t border-slate-200/60 pt-2 font-bold text-sm">
                      <span className="text-slate-800">{language === 'en' ? 'Net Seller Payout (upon Release):' : 'صافي رصيد البائع المحول:'}</span>
                      <span className="text-emerald-600">{payout} SAR</span>
                    </div>
                  </div>

                  <div className="border-t border-slate-200/60 pt-3 grid grid-cols-2 gap-4 text-[10px]">
                    <div>
                      <span className="text-slate-400 block font-bold">{language === 'en' ? 'Payment Gateway' : 'بوابة المعالجة'}</span>
                      <span className="font-bold text-slate-700">{gatewayInfo.gateway}</span>
                    </div>
                    <div>
                      <span className="text-slate-400 block font-bold">{language === 'en' ? 'Transaction UUID' : 'معرف العملية الفريد'}</span>
                      <span className="font-mono text-slate-600">{gatewayInfo.txId}</span>
                    </div>
                  </div>
                </div>

                {/* Workflow Timeline logs */}
                <div className="space-y-2">
                  <h4 className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    {language === 'en' ? 'Fulfillment Workflow Logs' : 'سجل التحركات التشغيلية وتطور المعاملة'}
                  </h4>

                  <div className="space-y-3.5 pl-2.5 rtl:pl-0 rtl:pr-2.5 border-l border-slate-200 rtl:border-l-0 rtl:border-r">
                    {timeline.map((log, idx) => (
                      <div key={idx} className="relative text-xs">
                        <span className="absolute -left-4 rtl:-right-4 top-1.5 h-2.5 w-2.5 rounded-full bg-slate-300 border border-white" />
                        <div className="flex items-center justify-between font-bold text-slate-700">
                          <span>{language === 'en' ? log.actorEn : log.actorAr}</span>
                          <span className="text-[10px] text-slate-400 font-mono font-medium">{log.timestamp}</span>
                        </div>
                        <p className="text-slate-500 mt-1 leading-relaxed">
                          {language === 'en' ? log.messageEn : log.messageAr}
                        </p>
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              {/* Actions for manual dispute intervention */}
              <div className="mt-8 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <button
                  onClick={() => setSelectedOrder(null)}
                  className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                >
                  {t.back}
                </button>

                {hasPermission && selectedOrder.status !== 'completed' && selectedOrder.status !== 'cancelled' && (
                  <>
                    <button
                      onClick={() => handleUpdateStatus(selectedOrder.id, 'cancelled')}
                      className="rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-700"
                    >
                      {language === 'en' ? 'Cancel & Refund' : 'إلغاء وإعادة المستحقات للمشتري'}
                    </button>
                    <button
                      onClick={() => handleUpdateStatus(selectedOrder.id, 'completed')}
                      className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700"
                    >
                      {language === 'en' ? 'Release Escrow' : 'صرف مبلغ الضمان مالي للبائع'}
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>
        );
      })()}
    </div>
  );
}
