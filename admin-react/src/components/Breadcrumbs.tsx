/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language } from '../types';
import { translations } from '../translations';
import { ChevronRight, ChevronLeft, Home } from 'lucide-react';

interface BreadcrumbsProps {
  currentView: string;
  language: Language;
}

export default function Breadcrumbs({ currentView, language }: BreadcrumbsProps) {
  const t = translations[language];
  const isRtl = language === 'ar';

  const getBreadcrumbs = () => {
    const homeItem = { label: isRtl ? 'الرئيسية' : 'Home', view: 'dashboard' };

    switch (currentView) {
      case 'dashboard':
        return [
          { label: t.nav_dashboard, active: true }
        ];
      case 'services':
        return [
          { label: isRtl ? 'النظام الأساسي' : 'Core Platform', active: false },
          { label: t.nav_services, active: true }
        ];
      case 'users':
        return [
          { label: isRtl ? 'النظام الأساسي' : 'Core Platform', active: false },
          { label: t.nav_users, active: true }
        ];
      case 'orders':
        return [
          { label: isRtl ? 'النظام الأساسي' : 'Core Platform', active: false },
          { label: t.nav_orders, active: true }
        ];
      case 'payments':
        return [
          { label: isRtl ? 'النظام الأساسي' : 'Core Platform', active: false },
          { label: t.nav_payments, active: true }
        ];
      case 'support':
        return [
          { label: isRtl ? 'النظام الأساسي' : 'Core Platform', active: false },
          { label: t.nav_support, active: true }
        ];
      case 'cms':
        return [
          { label: isRtl ? 'النظام الأساسي' : 'Core Platform', active: false },
          { label: t.nav_cms, active: true }
        ];
      case 'localization':
        return [
          { label: isRtl ? 'إعدادات النظام' : 'System Config', active: false },
          { label: t.nav_localization, active: true }
        ];
      case 'settings':
        return [
          { label: isRtl ? 'إعدادات النظام' : 'System Config', active: false },
          { label: t.nav_settings, active: true }
        ];
      case 'wallet':
        return [
          { label: isRtl ? 'الخدمات المضافة' : 'Add-on Modules', active: false },
          { label: t.nav_wallet, active: true }
        ];
      case 'chat':
        return [
          { label: isRtl ? 'الخدمات المضافة' : 'Add-on Modules', active: false },
          { label: t.nav_chat, active: true }
        ];
      case 'jobs':
        return [
          { label: isRtl ? 'الخدمات المضافة' : 'Add-on Modules', active: false },
          { label: t.nav_jobs, active: true }
        ];
      case 'subscription':
        return [
          { label: isRtl ? 'الخدمات المضافة' : 'Add-on Modules', active: false },
          { label: t.nav_subscription, active: true }
        ];
      default:
        return [
          { label: t.nav_dashboard, active: true }
        ];
    }
  };

  const items = getBreadcrumbs();
  const Separator = isRtl ? ChevronLeft : ChevronRight;

  return (
    <nav aria-label="Breadcrumb" className="mb-6 flex items-center gap-2 text-xs font-semibold text-slate-500">
      <div className="flex items-center gap-1.5 text-slate-400 hover:text-indigo-600 transition duration-150 cursor-pointer">
        <Home className="h-3.5 w-3.5" />
        <span className="hidden sm:inline">{isRtl ? 'الرئيسية' : 'Home'}</span>
      </div>

      {items.map((item, index) => (
        <div key={index} className="flex items-center gap-2">
          <Separator className="h-3 w-3 text-slate-300" />
          <span
            className={`${
              item.active
                ? 'text-indigo-600 font-bold'
                : 'text-slate-400 font-medium'
            }`}
          >
            {item.label}
          </span>
        </div>
      ))}
    </nav>
  );
}
