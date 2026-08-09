/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, OverviewStats } from '../types';
import { translations } from '../translations';
import { TrendingUp, TrendingDown, DollarSign, ShoppingCart, Video, Users } from 'lucide-react';

interface StatsGridProps {
  language: Language;
  stats: OverviewStats | null;
  loading: boolean;
}

export default function StatsGrid({ language, stats, loading }: StatsGridProps) {
  const t = translations[language];

  if (loading || !stats) {
    return (
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        {[1, 2, 3, 4].map(idx => (
          <div key={idx} className="animate-pulse rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
            <div className="flex items-center justify-between">
              <div className="h-4 w-24 rounded bg-slate-200" />
              <div className="h-10 w-10 rounded-xl bg-slate-100" />
            </div>
            <div className="mt-4 h-8 w-32 rounded bg-slate-200" />
            <div className="mt-2 h-4 w-20 rounded bg-slate-100" />
          </div>
        ))}
      </div>
    );
  }

  const items = [
    {
      label: t.stats_revenue,
      value: `${stats.total_revenue.toLocaleString()} ${language === 'en' ? 'SAR' : 'ريال'}`,
      growth: stats.revenue_growth,
      icon: DollarSign,
      color: 'bg-indigo-50 text-indigo-600 border-indigo-100',
    },
    {
      label: t.stats_orders,
      value: stats.total_orders,
      growth: stats.orders_growth,
      icon: ShoppingCart,
      color: 'bg-blue-50 text-blue-600 border-blue-100',
    },
    {
      label: t.stats_services,
      value: stats.active_services,
      growth: stats.services_growth,
      icon: Video,
      color: 'bg-emerald-50 text-emerald-600 border-emerald-100',
    },
    {
      label: t.stats_users,
      value: stats.total_users,
      growth: stats.users_growth,
      icon: Users,
      color: 'bg-violet-50 text-violet-600 border-violet-100',
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
      {items.map((item, idx) => {
        const Icon = item.icon;
        const isPositive = item.growth >= 0;
        return (
          <div
            key={idx}
            className="group relative overflow-hidden rounded-2xl border border-slate-200/60 bg-white p-6 shadow-sm transition-all hover:border-slate-300 hover:shadow-md"
          >
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-slate-500">{item.label}</span>
              <div className={`flex h-10 w-10 items-center justify-center rounded-xl border ${item.color}`}>
                <Icon className="h-5 w-5" />
              </div>
            </div>

            <div className="mt-4 flex items-baseline gap-2">
              <span className="text-2xl font-bold tracking-tight text-slate-900">{item.value}</span>
            </div>

            <div className="mt-2 flex items-center gap-1">
              <span className={`inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-bold ${
                isPositive ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
              }`}>
                {isPositive ? <TrendingUp className="mr-0.5 h-3 w-3 rtl:ml-0.5" /> : <TrendingDown className="mr-0.5 h-3 w-3 rtl:ml-0.5" />}
                {isPositive ? '+' : ''}{item.growth}%
              </span>
              <span className="text-[10px] text-slate-400 font-medium">
                {t.growth_vs_last_month}
              </span>
            </div>

            {/* Subtle background decoration */}
            <div className="absolute -bottom-6 -right-6 h-12 w-12 rounded-full bg-slate-50 group-hover:scale-150 transition-transform duration-500 ease-out z-0 opacity-40" />
          </div>
        );
      })}
    </div>
  );
}
