import React, { useState, useEffect } from 'react';
import {
  Award,
  Crown,
  Search,
  RefreshCw,
  Plus,
  Edit2,
  CheckCircle2,
  Clock,
  AlertCircle,
  Users,
  DollarSign,
  Calendar,
  X,
  SlidersHorizontal,
  Layers,
  ShieldAlert,
} from 'lucide-react';
import { LaravelAPI } from '../api';
import {
  Language,
  UserRole,
  SubscriptionPlanItem,
  SellerSubscriberItem,
  SubscriptionHistoryItem,
  SubscriptionSummary,
} from '../types';
import { checkPermission } from '../utils/auditLogger';

interface SubscriptionsViewProps {
  language: Language;
  activeRole: UserRole;
}

export default function SubscriptionsView({ language, activeRole }: SubscriptionsViewProps) {
  const isRtl = language === 'ar';
  const canManage = checkPermission(activeRole, 'manage_settings') || checkPermission(activeRole, 'manage_payments');

  const [loading, setLoading] = useState<boolean>(true);
  const [activeTab, setActiveTab] = useState<'plans' | 'subscribers' | 'history'>('plans');

  const [summary, setSummary] = useState<SubscriptionSummary | null>(null);
  const [plans, setPlans] = useState<SubscriptionPlanItem[]>([]);
  const [subscribers, setSubscribers] = useState<SellerSubscriberItem[]>([]);
  const [histories, setHistories] = useState<SubscriptionHistoryItem[]>([]);

  // Search filter
  const [search, setSearch] = useState<string>('');

  // Plan Edit/Create modal
  const [isPlanModalOpen, setIsPlanModalOpen] = useState<boolean>(false);
  const [editingPlan, setEditingPlan] = useState<SubscriptionPlanItem | null>(null);
  const [planForm, setPlanForm] = useState({
    title: '',
    type: 'monthly' as 'monthly' | 'yearly' | 'lifetime',
    price: 0,
    connect: 10,
    service: 5,
    job: 5,
    description: '',
    status: 1,
  });
  const [submittingPlan, setSubmittingPlan] = useState<boolean>(false);

  // Subscriber Adjustment modal
  const [adjustingSubscriber, setAdjustingSubscriber] = useState<SellerSubscriberItem | null>(null);
  const [adjustForm, setAdjustForm] = useState({
    connect: 0,
    service: 0,
    job: 0,
    days_to_add: 0,
    admin_note: '',
  });
  const [submittingAdjust, setSubmittingAdjust] = useState<boolean>(false);

  const fetchSubscriptionsData = async () => {
    try {
      setLoading(true);
      const data = await LaravelAPI.getSubscriptions();
      setSummary(data.metrics);
      setPlans(data.plans || []);
      setSubscribers(data.subscribers?.data || []);
      setHistories(data.recent_histories || []);
    } catch (err) {
      console.error('Failed to load subscriptions:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSubscriptionsData();
  }, []);

  const openCreatePlanModal = () => {
    setEditingPlan(null);
    setPlanForm({
      title: '',
      type: 'monthly',
      price: 99,
      connect: 20,
      service: 10,
      job: 10,
      description: '',
      status: 1,
    });
    setIsPlanModalOpen(true);
  };

  const openEditPlanModal = (plan: SubscriptionPlanItem) => {
    setEditingPlan(plan);
    setPlanForm({
      title: plan.title,
      type: plan.type,
      price: plan.price,
      connect: plan.connect,
      service: plan.service,
      job: plan.job,
      description: plan.description || '',
      status: plan.status,
    });
    setIsPlanModalOpen(true);
  };

  const handleSavePlan = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmittingPlan(true);
    try {
      if (editingPlan) {
        await LaravelAPI.updateSubscriptionPlan(editingPlan.id, planForm);
      } else {
        await LaravelAPI.createSubscriptionPlan(planForm);
      }
      setIsPlanModalOpen(false);
      fetchSubscriptionsData();
    } catch (err) {
      console.error('Failed to save subscription plan:', err);
    } finally {
      setSubmittingPlan(false);
    }
  };

  const handleTogglePlanStatus = async (id: number) => {
    try {
      await LaravelAPI.toggleSubscriptionPlanStatus(id);
      fetchSubscriptionsData();
    } catch (err) {
      console.error('Failed to toggle plan status:', err);
    }
  };

  const openAdjustSubscriberModal = (sub: SellerSubscriberItem) => {
    setAdjustingSubscriber(sub);
    setAdjustForm({
      connect: sub.connect,
      service: sub.service,
      job: sub.job,
      days_to_add: 0,
      admin_note: '',
    });
  };

  const handleSaveSubscriberAdjustment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!adjustingSubscriber) return;
    setSubmittingAdjust(true);
    try {
      await LaravelAPI.adjustSubscriber(adjustingSubscriber.seller_id, adjustForm);
      setAdjustingSubscriber(null);
      fetchSubscriptionsData();
    } catch (err) {
      console.error('Failed to adjust subscriber:', err);
    } finally {
      setSubmittingAdjust(false);
    }
  };

  const filteredSubscribers = subscribers.filter((s) => {
    if (!search) return true;
    const term = search.toLowerCase();
    return (
      s.seller?.name?.toLowerCase().includes(term) ||
      s.seller?.email?.toLowerCase().includes(term) ||
      s.subscription?.title?.toLowerCase().includes(term)
    );
  });

  return (
    <div className={`space-y-6 ${isRtl ? 'rtl' : 'ltr'}`}>
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Crown className="w-7 h-7 text-amber-500" />
            {language === 'ar' ? 'إدارة الاشتراكات والباقات' : 'Subscriptions & Monetization'}
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            {language === 'ar'
              ? 'تكوين باقات المزودين، تتبع الاشتراكات الفعالة، والتحكم بحصص العطاءات والخدمات'
              : 'Configure provider tiers, track active subscribers, and manage connect/service quotas.'}
          </p>
        </div>
        <div className="flex items-center gap-2">
          {canManage && (
            <button
              onClick={openCreatePlanModal}
              className="flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors"
            >
              <Plus className="w-4 h-4" />
              {language === 'ar' ? 'إضافة باقة جديدة' : 'New Plan'}
            </button>
          )}
          <button
            onClick={fetchSubscriptionsData}
            disabled={loading}
            className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 dark:hover:bg-slate-700 transition-colors shadow-sm"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
            {language === 'ar' ? 'تحديث' : 'Refresh'}
          </button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              {language === 'ar' ? 'إجمالي الباقات' : 'Total Tiers'}
            </p>
            <p className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
              {summary ? summary.total_plans : '—'}
            </p>
          </div>
          <div className="p-3 bg-amber-50 dark:bg-amber-900/30 rounded-xl text-amber-500">
            <Award className="w-6 h-6" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              {language === 'ar' ? 'المشتركون النشطون' : 'Active Subscribers'}
            </p>
            <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
              {summary ? summary.active_subscribers : '—'}
            </p>
          </div>
          <div className="p-3 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl text-emerald-600 dark:text-emerald-400">
            <CheckCircle2 className="w-6 h-6" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              {language === 'ar' ? 'الاشتراكات المنتهية' : 'Expired / Past Due'}
            </p>
            <p className="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">
              {summary ? summary.expired_subscribers : '—'}
            </p>
          </div>
          <div className="p-3 bg-rose-50 dark:bg-rose-900/30 rounded-xl text-rose-600 dark:text-rose-400">
            <Clock className="w-6 h-6" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              {language === 'ar' ? 'إجمالي الإيرادات' : 'Total Revenue'}
            </p>
            <p className="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
              {summary ? `${summary.total_revenue} SAR` : '—'}
            </p>
          </div>
          <div className="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl text-indigo-600 dark:text-indigo-400">
            <DollarSign className="w-6 h-6" />
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-slate-200 dark:border-slate-700 flex gap-4">
        <button
          onClick={() => setActiveTab('plans')}
          className={`pb-3 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 ${
            activeTab === 'plans'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          <Award className="w-4 h-4" />
          {language === 'ar' ? 'باقات الاشتراك المتاحة' : 'Subscription Plans'} ({plans.length})
        </button>

        <button
          onClick={() => setActiveTab('subscribers')}
          className={`pb-3 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 ${
            activeTab === 'subscribers'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          <Users className="w-4 h-4" />
          {language === 'ar' ? 'المشتركون والمزودون' : 'Active Subscribers'} ({subscribers.length})
        </button>

        <button
          onClick={() => setActiveTab('history')}
          className={`pb-3 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 ${
            activeTab === 'history'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          <Calendar className="w-4 h-4" />
          {language === 'ar' ? 'سجل العمليات والتجديد' : 'Purchase History'} ({histories.length})
        </button>
      </div>

      {/* Tab 1: Plans */}
      {activeTab === 'plans' && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {plans.map((plan) => (
            <div
              key={plan.id}
              className={`bg-white dark:bg-slate-800 rounded-xl border p-5 shadow-sm flex flex-col justify-between transition-all ${
                plan.status === 1 ? 'border-slate-200 dark:border-slate-700' : 'border-slate-200 opacity-60'
              }`}
            >
              <div>
                <div className="flex items-center justify-between mb-3">
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                    {plan.type}
                  </span>
                  <span
                    className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                      plan.status === 1
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                        : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400'
                    }`}
                  >
                    {plan.status === 1 ? (language === 'ar' ? 'نشط' : 'Active') : (language === 'ar' ? 'معطل' : 'Disabled')}
                  </span>
                </div>

                <h3 className="text-xl font-bold text-slate-900 dark:text-white">{plan.title}</h3>
                <div className="mt-2 flex items-baseline gap-1">
                  <span className="text-3xl font-extrabold text-slate-900 dark:text-white">{plan.price}</span>
                  <span className="text-xs font-semibold text-slate-400 uppercase">SAR / {plan.type}</span>
                </div>

                <p className="mt-3 text-xs text-slate-500 dark:text-slate-400 line-clamp-2">
                  {plan.description || (language === 'ar' ? 'لا يوجد وصف مضاف' : 'No description provided')}
                </p>

                <div className="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 space-y-2 text-xs text-slate-600 dark:text-slate-300">
                  <div className="flex justify-between">
                    <span className="text-slate-400">{language === 'ar' ? 'نقاط العطاءات (Connects):' : 'Bidding Connects:'}</span>
                    <strong className="text-slate-800 dark:text-slate-200">{plan.type === 'lifetime' ? 'Unlimited' : plan.connect}</strong>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400">{language === 'ar' ? 'الخدمات المسموحة:' : 'Publishable Services:'}</span>
                    <strong className="text-slate-800 dark:text-slate-200">{plan.service}</strong>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400">{language === 'ar' ? 'تقديمات الوظائف:' : 'Job Applications:'}</span>
                    <strong className="text-slate-800 dark:text-slate-200">{plan.job}</strong>
                  </div>
                </div>
              </div>

              {canManage && (
                <div className="mt-5 pt-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
                  <button
                    onClick={() => openEditPlanModal(plan)}
                    className="flex-1 py-1.5 px-3 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600 rounded-lg transition-colors flex items-center justify-center gap-1.5"
                  >
                    <Edit2 className="w-3.5 h-3.5" />
                    {language === 'ar' ? 'تعديل' : 'Edit'}
                  </button>
                  <button
                    onClick={() => handleTogglePlanStatus(plan.id)}
                    className={`py-1.5 px-3 text-xs font-medium rounded-lg transition-colors ${
                      plan.status === 1
                        ? 'text-rose-700 bg-rose-50 hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-300'
                        : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300'
                    }`}
                  >
                    {plan.status === 1 ? (language === 'ar' ? 'تعطيل' : 'Disable') : (language === 'ar' ? 'تفعيل' : 'Enable')}
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Tab 2: Subscribers */}
      {activeTab === 'subscribers' && (
        <div className="space-y-4">
          <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div className="relative w-full max-w-md">
              <Search className={`w-4 h-4 absolute ${isRtl ? 'right-3' : 'left-3'} top-1/2 -translate-y-1/2 text-slate-400`} />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder={language === 'ar' ? 'بحث باسم المزود أو بريده...' : 'Filter by seller name or email...'}
                className={`w-full ${isRtl ? 'pr-9 pl-4' : 'pl-9 pr-4'} py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500`}
              />
            </div>
          </div>

          <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead className="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 text-xs uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                  <tr>
                    <th className="px-6 py-3.5">{language === 'ar' ? 'المزود / البائع' : 'Seller / Provider'}</th>
                    <th className="px-6 py-3.5">{language === 'ar' ? 'الباقة' : 'Tier'}</th>
                    <th className="px-6 py-3.5">{language === 'ar' ? 'العطاءات المتبقية' : 'Connects'}</th>
                    <th className="px-6 py-3.5">{language === 'ar' ? 'الخدمات المتبقية' : 'Services'}</th>
                    <th className="px-6 py-3.5">{language === 'ar' ? 'الوظائف المتبقية' : 'Jobs'}</th>
                    <th className="px-6 py-3.5">{language === 'ar' ? 'تاريخ الانتهاء' : 'Expires At'}</th>
                    <th className="px-6 py-3.5">{language === 'ar' ? 'الحالة' : 'Status'}</th>
                    <th className="px-6 py-3.5 text-center">{language === 'ar' ? 'الإجراءات' : 'Actions'}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                  {filteredSubscribers.length === 0 ? (
                    <tr>
                      <td colSpan={8} className="px-6 py-12 text-center text-slate-400">
                        <Users className="w-8 h-8 mx-auto mb-2 text-slate-300 dark:text-slate-600" />
                        {language === 'ar' ? 'لا يوجد مشتركون مطابقون' : 'No subscribers found'}
                      </td>
                    </tr>
                  ) : (
                    filteredSubscribers.map((sub) => {
                      const isExpired = sub.expire_date && new Date(sub.expire_date) <= new Date();
                      return (
                        <tr key={sub.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                          <td className="px-6 py-4">
                            <div className="font-semibold text-slate-900 dark:text-white">
                              {sub.seller?.name || '—'}
                            </div>
                            <div className="text-xs text-slate-400 font-mono">
                              {sub.seller?.email || ''}
                            </div>
                          </td>
                          <td className="px-6 py-4 font-medium text-slate-800 dark:text-slate-200">
                            {sub.subscription?.title || sub.type}
                          </td>
                          <td className="px-6 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                            {sub.connect}
                          </td>
                          <td className="px-6 py-4 font-mono text-slate-700 dark:text-slate-300">
                            {sub.service}
                          </td>
                          <td className="px-6 py-4 font-mono text-slate-700 dark:text-slate-300">
                            {sub.job}
                          </td>
                          <td className="px-6 py-4 text-xs font-mono text-slate-500">
                            {sub.expire_date ? new Date(sub.expire_date).toLocaleDateString() : 'Lifetime'}
                          </td>
                          <td className="px-6 py-4">
                            {isExpired ? (
                              <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                                {language === 'ar' ? 'منتهي' : 'Expired'}
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                {language === 'ar' ? 'نشط' : 'Active'}
                              </span>
                            )}
                          </td>
                          <td className="px-6 py-4 text-center">
                            {canManage && (
                              <button
                                onClick={() => openAdjustSubscriberModal(sub)}
                                className="px-2.5 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300 rounded-lg transition-colors flex items-center gap-1 mx-auto"
                              >
                                <SlidersHorizontal className="w-3.5 h-3.5" />
                                {language === 'ar' ? 'تعديل الحصص' : 'Adjust'}
                              </button>
                            )}
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* Tab 3: History */}
      {activeTab === 'history' && (
        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
              <thead className="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 text-xs uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                <tr>
                  <th className="px-6 py-3.5">#</th>
                  <th className="px-6 py-3.5">{language === 'ar' ? 'المزود' : 'Seller'}</th>
                  <th className="px-6 py-3.5">{language === 'ar' ? 'الباقة' : 'Plan'}</th>
                  <th className="px-6 py-3.5">{language === 'ar' ? 'المبلغ' : 'Amount'}</th>
                  <th className="px-6 py-3.5">{language === 'ar' ? 'بوابة الدفع' : 'Gateway'}</th>
                  <th className="px-6 py-3.5">{language === 'ar' ? 'التاريخ' : 'Date'}</th>
                  <th className="px-6 py-3.5">{language === 'ar' ? 'الحالة' : 'Status'}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                {histories.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-6 py-12 text-center text-slate-400">
                      <Calendar className="w-8 h-8 mx-auto mb-2 text-slate-300 dark:text-slate-600" />
                      {language === 'ar' ? 'لا يوجد سجلات شراء سابقة' : 'No subscription transaction history yet'}
                    </td>
                  </tr>
                ) : (
                  histories.map((h) => (
                    <tr key={h.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                      <td className="px-6 py-4 font-mono text-xs text-slate-400">#{h.id}</td>
                      <td className="px-6 py-4 font-medium text-slate-900 dark:text-white">
                        {h.seller?.name || `Seller #${h.seller_id}`}
                      </td>
                      <td className="px-6 py-4">{h.subscription?.title || h.type}</td>
                      <td className="px-6 py-4 font-semibold text-slate-900 dark:text-white">
                        {h.price} SAR
                      </td>
                      <td className="px-6 py-4 uppercase font-mono text-xs text-slate-500">
                        {h.payment_gateway || 'wallet'}
                      </td>
                      <td className="px-6 py-4 text-xs text-slate-500">
                        {new Date(h.created_at).toLocaleDateString()}
                      </td>
                      <td className="px-6 py-4">
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                          {h.payment_status}
                        </span>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Plan Create/Edit Modal */}
      {isPlanModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
          <div className="bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-lg w-full p-6 border border-slate-200 dark:border-slate-700">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700">
              <h3 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Crown className="w-5 h-5 text-amber-500" />
                {editingPlan
                  ? language === 'ar' ? 'تعديل باقة الاشتراك' : 'Edit Subscription Tier'
                  : language === 'ar' ? 'إضافة باقة اشتراك جديدة' : 'Create New Subscription Tier'}
              </h3>
              <button
                onClick={() => setIsPlanModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSavePlan} className="mt-4 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                  {language === 'ar' ? 'اسم الباقة' : 'Plan Title'}
                </label>
                <input
                  type="text"
                  required
                  value={planForm.title}
                  onChange={(e) => setPlanForm({ ...planForm, title: e.target.value })}
                  placeholder="e.g. Professional Seller Tier"
                  className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                    {language === 'ar' ? 'الدورة الزمنية' : 'Billing Cycle'}
                  </label>
                  <select
                    value={planForm.type}
                    onChange={(e) => setPlanForm({ ...planForm, type: e.target.value as any })}
                    className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="monthly">{language === 'ar' ? 'شهري' : 'Monthly'}</option>
                    <option value="yearly">{language === 'ar' ? 'سنوي' : 'Yearly'}</option>
                    <option value="lifetime">{language === 'ar' ? 'مدى الحياة' : 'Lifetime'}</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                    {language === 'ar' ? 'السعر (ر.س)' : 'Price (SAR)'}
                  </label>
                  <input
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    value={planForm.price}
                    onChange={(e) => setPlanForm({ ...planForm, price: parseFloat(e.target.value) || 0 })}
                    className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                    {language === 'ar' ? 'النقاط' : 'Connects'}
                  </label>
                  <input
                    type="number"
                    min="0"
                    required
                    value={planForm.connect}
                    onChange={(e) => setPlanForm({ ...planForm, connect: parseInt(e.target.value) || 0 })}
                    className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                    {language === 'ar' ? 'الخدمات' : 'Services'}
                  </label>
                  <input
                    type="number"
                    min="0"
                    required
                    value={planForm.service}
                    onChange={(e) => setPlanForm({ ...planForm, service: parseInt(e.target.value) || 0 })}
                    className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                    {language === 'ar' ? 'الوظائف' : 'Jobs'}
                  </label>
                  <input
                    type="number"
                    min="0"
                    required
                    value={planForm.job}
                    onChange={(e) => setPlanForm({ ...planForm, job: parseInt(e.target.value) || 0 })}
                    className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                  {language === 'ar' ? 'الوصف' : 'Description'}
                </label>
                <textarea
                  rows={2}
                  value={planForm.description}
                  onChange={(e) => setPlanForm({ ...planForm, description: e.target.value })}
                  placeholder="Tier privileges and benefits..."
                  className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                <button
                  type="button"
                  onClick={() => setIsPlanModalOpen(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 dark:text-slate-300 transition-colors"
                >
                  {language === 'ar' ? 'إلغاء' : 'Cancel'}
                </button>
                <button
                  type="submit"
                  disabled={submittingPlan}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors flex items-center gap-2"
                >
                  {submittingPlan && <RefreshCw className="w-4 h-4 animate-spin" />}
                  {editingPlan ? (language === 'ar' ? 'تحديث الباقة' : 'Update Plan') : (language === 'ar' ? 'إنشاء الباقة' : 'Create Plan')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Adjust Subscriber Quotas Modal */}
      {adjustingSubscriber && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
          <div className="bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-700">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700">
              <h3 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <SlidersHorizontal className="w-5 h-5 text-indigo-600" />
                {language === 'ar' ? 'تعديل حصص المشترك' : 'Adjust Subscriber Quotas'}
              </h3>
              <button
                onClick={() => setAdjustingSubscriber(null)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveSubscriberAdjustment} className="mt-4 space-y-4">
              <div>
                <p className="text-xs text-slate-400 uppercase font-semibold">
                  {language === 'ar' ? 'المزود:' : 'Provider:'}
                </p>
                <p className="font-semibold text-slate-900 dark:text-white text-sm mt-0.5">
                  {adjustingSubscriber.seller?.name || `Seller #${adjustingSubscriber.seller_id}`}
                </p>
                <p className="text-xs text-slate-400 font-mono">
                  {adjustingSubscriber.seller?.email}
                </p>
              </div>

              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                    {language === 'ar' ? 'النقاط' : 'Connects'}
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={adjustForm.connect}
                    onChange={(e) => setAdjustForm({ ...adjustForm, connect: parseInt(e.target.value) || 0 })}
                    className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                    {language === 'ar' ? 'الخدمات' : 'Services'}
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={adjustForm.service}
                    onChange={(e) => setAdjustForm({ ...adjustForm, service: parseInt(e.target.value) || 0 })}
                    className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                    {language === 'ar' ? 'الوظائف' : 'Jobs'}
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={adjustForm.job}
                    onChange={(e) => setAdjustForm({ ...adjustForm, job: parseInt(e.target.value) || 0 })}
                    className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                  {language === 'ar' ? 'تمديد الصلاحية (أيام)' : 'Extend Expiration (Days)'}
                </label>
                <input
                  type="number"
                  placeholder="0 (no extension)"
                  value={adjustForm.days_to_add}
                  onChange={(e) => setAdjustForm({ ...adjustForm, days_to_add: parseInt(e.target.value) || 0 })}
                  className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                  {language === 'ar' ? 'سبب التعديل / ملاحظة التدقيق *' : 'Audit Note / Reason *'}
                </label>
                <input
                  type="text"
                  required
                  value={adjustForm.admin_note}
                  onChange={(e) => setAdjustForm({ ...adjustForm, admin_note: e.target.value })}
                  placeholder={language === 'ar' ? 'سبب المنح أو التعديل...' : 'Reason for quota adjustment (min 5 chars)...'}
                  className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                <button
                  type="button"
                  onClick={() => setAdjustingSubscriber(null)}
                  className="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 dark:text-slate-300 transition-colors"
                >
                  {language === 'ar' ? 'إلغاء' : 'Cancel'}
                </button>
                <button
                  type="submit"
                  disabled={submittingAdjust || adjustForm.admin_note.length < 5}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors flex items-center gap-2"
                >
                  {submittingAdjust && <RefreshCw className="w-4 h-4 animate-spin" />}
                  {language === 'ar' ? 'حفظ التعديلات' : 'Save Adjustments'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
