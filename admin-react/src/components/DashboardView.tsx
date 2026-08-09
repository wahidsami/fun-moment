/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, OverviewStats, DashboardSummaryPayload } from '../types';
import { translations } from '../translations';
import StatsGrid from './StatsGrid';
import { motion } from 'motion/react';
import { Calendar, RefreshCw, Sparkles, CheckCircle2, AlertTriangle, RefreshCcw } from 'lucide-react';
import { useState, useEffect } from 'react';
import { LaravelAPI } from '../api';

interface DashboardViewProps {
  language: Language;
}

export default function DashboardView({ language }: DashboardViewProps) {
  const t = translations[language];
  const [stats, setStats] = useState<OverviewStats | null>(null);
  const [chartSeries, setChartSeries] = useState<DashboardSummaryPayload['chart_series']>([]);
  const [categoryDistribution, setCategoryDistribution] = useState<DashboardSummaryPayload['category_distribution']>([]);
  const [activities, setActivities] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [chartMetric, setChartMetric] = useState<'revenue' | 'orders'>('revenue');

  // Load stats from the live Laravel dashboard summary
  const loadDashboardData = async () => {
    setLoading(true);
    try {
      const summary = await LaravelAPI.getDashboardSummary();
      setStats(summary.overview_stats);
      setChartSeries(summary.chart_series ?? []);
      setCategoryDistribution(summary.category_distribution ?? []);
      setActivities(summary.activity_logs ?? []);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadDashboardData();
  }, []);

  const maxRevenue = Math.max(...chartSeries.map(d => d.revenue), 0);
  const maxOrders = Math.max(...chartSeries.map(d => d.orders), 0);

  return (
    <div className="space-y-6">
      {/* View Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-slate-800">
            {language === 'en' ? 'Marketplace Overview' : 'نظرة عامة على السوق'}
          </h2>
          <p className="text-xs text-slate-500">
            {language === 'en' ? 'Live insights and statistics synced with your Laravel/PHP backend.' : 'إحصائيات مباشرة متزامنة مع خادم Laravel/PHP الخلفي الخاص بك.'}
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={loadDashboardData}
            disabled={loading}
            className="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
          >
            <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
            <span>{t.refresh}</span>
          </button>
          <div className="flex items-center gap-1.5 rounded-lg bg-slate-100 p-1 text-xs">
            <span className="font-semibold px-2 text-slate-500 flex items-center gap-1">
              <Calendar className="h-3.5 w-3.5" />
              <span>{language === 'en' ? 'June 2026' : 'يونيو ٢٠٢٦'}</span>
            </span>
          </div>
        </div>
      </div>

      {/* Stats KPI widgets */}
      <StatsGrid language={language} stats={stats} loading={loading} />

      {/* Main Grid */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Interactive Bar Chart Card */}
        <div className="rounded-2xl border border-slate-200/60 bg-white p-6 shadow-sm lg:col-span-2">
          <div className="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <h3 className="text-sm font-bold text-slate-800 flex items-center gap-1.5">
              <span>{t.revenue_chart}</span>
              <Sparkles className="h-4 w-4 text-indigo-500 animate-pulse" />
            </h3>
            <div className="flex rounded-lg bg-slate-100 p-1 text-xs">
              <button
                onClick={() => setChartMetric('revenue')}
                className={`rounded-md px-2.5 py-1 font-semibold transition ${
                  chartMetric === 'revenue' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800'
                }`}
              >
                {language === 'en' ? 'Revenue (SAR)' : 'الإيرادات (ريال)'}
              </button>
              <button
                onClick={() => setChartMetric('orders')}
                className={`rounded-md px-2.5 py-1 font-semibold transition ${
                  chartMetric === 'orders' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800'
                }`}
              >
                {t.stats_orders}
              </button>
            </div>
          </div>

          {/* Gorgeous SVG & Tailwind-based Animated Bar Chart */}
          <div className="h-72 flex flex-col justify-between">
            <div className="flex-1 flex items-end justify-between gap-2 px-2 pt-4">
              {chartSeries.map((data, idx) => {
                const metricVal = chartMetric === 'revenue' ? data.revenue : data.orders;
                const maxVal = chartMetric === 'revenue' ? maxRevenue : maxOrders;
                const barHeightPercent = maxVal > 0 ? (metricVal / maxVal) * 80 + 10 : 10;

                return (
                  <div key={idx} className="flex-1 flex flex-col items-center group cursor-pointer">
                    <div className="relative w-full flex justify-center">
                      {/* Dynamic hover tooltip */}
                      <div className="absolute -top-10 scale-0 group-hover:scale-100 transition-all duration-150 bg-slate-900 text-white text-[10px] rounded-lg px-2 py-1 font-bold shadow-lg whitespace-nowrap z-10">
                        {chartMetric === 'revenue' ? `${data.revenue} SAR` : `${data.orders} ${language === 'en' ? 'orders' : 'طلبات'}`}
                      </div>
                    </div>

                    {/* Animated Bar */}
                    <div className="w-8 sm:w-10 bg-slate-50 rounded-t-lg overflow-hidden flex items-end h-56 border border-slate-100/50">
                      <motion.div
                        initial={{ height: 0 }}
                        animate={{ height: `${barHeightPercent}%` }}
                        transition={{ duration: 0.6, delay: idx * 0.05 }}
                        className={`w-full rounded-t-md ${
                          chartMetric === 'revenue'
                            ? 'bg-linear-to-t from-indigo-600 to-indigo-400 group-hover:from-indigo-500 group-hover:to-indigo-300'
                            : 'bg-linear-to-t from-blue-600 to-blue-400 group-hover:from-blue-500 group-hover:to-blue-300'
                        } transition-colors`}
                      />
                    </div>

                    <span className="text-[10px] font-bold text-slate-400 mt-2.5">
                      {language === 'en' ? data.labelEn : data.labelAr}
                    </span>
                  </div>
                );
              })}
            </div>
            {/* Legend indicators */}
            <div className="flex items-center gap-4 mt-6 border-t border-slate-100 pt-4 text-[10px] font-semibold text-slate-500 px-2">
              <div className="flex items-center gap-1.5">
                <span className="h-2.5 w-2.5 rounded-full bg-indigo-500" />
                <span>{language === 'en' ? 'Paid Bookings (SAR)' : 'الحجوزات المدفوعة (ريال)'}</span>
              </div>
              <div className="flex items-center gap-1.5">
                <span className="h-2.5 w-2.5 rounded-full bg-blue-500" />
                <span>{language === 'en' ? 'Completed Tasks' : 'المهام المكتملة'}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Categories Distribution Card */}
        <div className="rounded-2xl border border-slate-200/60 bg-white p-6 shadow-sm">
          <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-4 mb-4">
            {t.service_distribution}
          </h3>
          <div className="space-y-4 pt-2">
            {categoryDistribution.map((cat, idx) => (
              <div key={idx} className="space-y-1.5">
                <div className="flex items-center justify-between text-xs font-semibold text-slate-700">
                  <span className="truncate max-w-[180px]">{language === 'en' ? cat.name_en : cat.name_ar}</span>
                  <span className="text-slate-400">{cat.order_count} {language === 'en' ? 'Services' : 'خدمات'}</span>
                </div>
                <div className="h-2 w-full bg-slate-50 rounded-full overflow-hidden border border-slate-100">
                  <motion.div
                    initial={{ width: 0 }}
                    animate={{ width: `${cat.percentage}%` }}
                    transition={{ duration: 0.8, delay: idx * 0.1 }}
                    className={`h-full rounded-full ${
                      idx === 0 ? 'bg-indigo-500' : idx === 1 ? 'bg-blue-500' : idx === 2 ? 'bg-violet-500' : 'bg-emerald-500'
                    }`}
                  />
                </div>
              </div>
            ))}
          </div>

          {/* Quick API Tip Callout */}
          <div className="mt-8 rounded-xl bg-indigo-50/50 border border-indigo-100 p-3 text-xs text-indigo-800">
            <h4 className="font-bold flex items-center gap-1">
              <Sparkles className="h-3.5 w-3.5 animate-pulse" />
              <span>{language === 'en' ? 'Developer Notice' : 'ملاحظة للمطور'}</span>
            </h4>
            <p className="mt-1 text-[11px] leading-relaxed text-indigo-700">
              {language === 'en'
                ? "This dashboard is wired to a TypeScript API contract matching Laravel's database schema. Easy to swap fetch/axios once the controller endpoints are live."
                : "هذه الواجهة مبرمجة برابط برمجي متطابق مع قاعدة بيانات Laravel. يسهل استبدالها بطلب Axios مباشر بمجرد تفعيل روابط خادم PHP."}
            </p>
          </div>
        </div>
      </div>

      {/* Activity Log & Support Alerts Segment */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Recent Activity */}
        <div className="rounded-2xl border border-slate-200/60 bg-white p-6 shadow-sm lg:col-span-2">
          <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-4 mb-4">
            {t.recent_activity}
          </h3>
          <div className="divide-y divide-slate-100">
            {activities.length === 0 ? (
              <p className="text-xs text-slate-400 py-4 text-center">{t.loading}</p>
            ) : (
              activities.slice(0, 4).map((act, index) => (
                <div key={act.id || index} className="flex items-start gap-3 py-3.5 first:pt-0 last:pb-0">
                  <div className="mt-0.5 rounded-full bg-indigo-50 p-1.5 text-indigo-600">
                    <CheckCircle2 className="h-3.5 w-3.5" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-xs font-semibold text-slate-700">
                      {language === 'en' ? act.textEn : act.textAr}
                    </p>
                    <p className="text-[10px] text-slate-400 mt-0.5">{act.timestamp}</p>
                  </div>
                  <span className="text-[9px] bg-slate-100 text-slate-500 font-bold px-1.5 py-0.5 rounded uppercase">
                    Laravel
                  </span>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Platform Health */}
        <div className="rounded-2xl border border-slate-200/60 bg-white p-6 shadow-sm space-y-5">
          {/* Premium Gradient Health Widget */}
          <div className="bg-gradient-to-br from-indigo-600 to-violet-700 p-5 rounded-xl text-white shadow-lg shadow-indigo-100">
            <div className="flex justify-between items-start">
              <p className="text-indigo-100 text-[10px] font-bold uppercase tracking-wider">
                {language === 'en' ? 'System Health' : 'سلامة النظام'}
              </p>
              <div className="w-2 h-2 bg-emerald-400 rounded-full animate-pulse" />
            </div>
            <div className="mt-3 flex items-center gap-4">
              <div className="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center shrink-0">
                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                </svg>
              </div>
              <div>
                <p className="text-xl font-bold">99.98%</p>
                <p className="text-indigo-200 text-[9px]">{language === 'en' ? 'Backend Uptime (Laravel)' : 'جاهزية الخادم (لارافيل)'}</p>
              </div>
            </div>
          </div>

          <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-3">
            {language === 'en' ? 'Platform Security & Integrity' : 'أمان ونزاهة المنصة'}
          </h3>
          <div className="space-y-3.5">
            <div className="flex items-center gap-2.5 rounded-xl border border-rose-100 bg-rose-50/50 p-3">
              <AlertTriangle className="h-5 w-5 text-rose-500 shrink-0" />
              <div>
                <p className="text-xs font-bold text-rose-800">
                  {language === 'en' ? '1 High Priority Dispute' : 'نزاع واحد عالي الأولوية'}
                </p>
                <p className="text-[10px] text-rose-600 mt-0.5">
                  {language === 'en' ? 'Client Connor filed delay complaint' : 'العميل كونر سجل شكوى تأخير'}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50 p-3">
              <div className="h-5 w-5 rounded-full bg-slate-200 flex items-center justify-center text-[10px] font-bold text-slate-600">
                SSL
              </div>
              <div>
                <p className="text-xs font-bold text-slate-700">
                  {language === 'en' ? 'API Endpoint Encrypted' : 'تشفير نهاية اتصال الخدمة'}
                </p>
                <p className="text-[10px] text-slate-400 mt-0.5">TLS 1.3 Active on Cloud Run</p>
              </div>
            </div>

            <div className="flex items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50 p-3">
              <div className="h-5 w-5 rounded-full bg-slate-200 flex items-center justify-center text-[10px] font-bold text-slate-600">
                CRN
              </div>
              <div>
                <p className="text-xs font-bold text-slate-700">
                  {language === 'en' ? 'Cron Queue Manager' : 'مدير مهام الكرون الدورية'}
                </p>
                <p className="text-[10px] text-emerald-600 font-semibold mt-0.5">● Escrow Release Running</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
