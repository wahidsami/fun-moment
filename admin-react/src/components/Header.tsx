/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, UserRole } from '../types';
import { translations } from '../translations';
import { Globe, ShieldAlert, Bell, LogOut, ChevronDown, Check, Menu } from 'lucide-react';
import { useState } from 'react';
import logo from '../../thelogo.png';

interface HeaderProps {
  language: Language;
  setLanguage: (lang: Language) => void;
  activeRole: UserRole;
  setActiveRole: (role: UserRole) => void;
  onToggleMobileSidebar: () => void;
  onLogout: () => void;
}

export default function Header({
  language,
  setLanguage,
  activeRole,
  setActiveRole,
  onToggleMobileSidebar,
  onLogout
}: HeaderProps) {
  const t = translations[language];
  const [showRoleDropdown, setShowRoleDropdown] = useState(false);
  const [showNotifications, setShowNotifications] = useState(false);

  const rolesList: { role: UserRole; name: string; iconColor: string }[] = [
    { role: 'super_admin', name: t.role_super_admin, iconColor: 'text-red-500 bg-red-50' },
    { role: 'moderator', name: t.role_moderator, iconColor: 'text-emerald-500 bg-emerald-50' },
    { role: 'financial_manager', name: t.role_financial_manager, iconColor: 'text-blue-500 bg-blue-50' },
    { role: 'support_agent', name: t.role_support_agent, iconColor: 'text-amber-500 bg-amber-50' }
  ];

  const toggleLanguage = () => {
    setLanguage(language === 'en' ? 'ar' : 'en');
  };

  return (
    <header className="sticky top-0 z-40 flex h-16 w-full items-center justify-between border-b border-slate-200 bg-white px-6 shadow-sm">
      {/* Title & Brand */}
      <div className="flex items-center gap-3">
        {/* Mobile Menu Trigger */}
        <button
          onClick={onToggleMobileSidebar}
          className="md:hidden flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 focus:outline-none"
          title={language === 'en' ? 'Toggle Sidebar' : 'تبديل القائمة'}
        >
          <Menu className="h-5 w-5" />
        </button>

        <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shrink-0">
          <img src={logo} alt="FUN MOMENT" className="h-full w-full object-cover" />
        </div>
        <div>
          <h1 className="text-lg font-bold tracking-tight text-slate-800">
            {t.brandName} <span className="text-indigo-600 font-medium text-sm ml-1 mr-1">/ {t.adminDashboard}</span>
          </h1>
        </div>
      </div>

      {/* Controls & Badges */}
      <div className="flex items-center gap-4">
        {/* Role Switcher */}
        <div className="relative">
          <button
            onClick={() => setShowRoleDropdown(!showRoleDropdown)}
            className="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none"
          >
            <ShieldAlert className="h-4 w-4 text-indigo-600" />
            <span>{t.activeRole}: {rolesList.find(r => r.role === activeRole)?.name}</span>
            <ChevronDown className="h-3 w-3 text-slate-400" />
          </button>

          {showRoleDropdown && (
            <div className={`absolute ${language === 'en' ? 'right-0' : 'left-0'} mt-2 w-64 rounded-xl border border-slate-100 bg-white p-2 shadow-xl ring-1 ring-black/5 z-50`}>
              <div className="px-3 py-2 text-xs font-semibold text-slate-400 border-b border-slate-50 mb-1">
                {t.switchRole} (RBAC)
              </div>
              {rolesList.map(item => (
                <button
                  key={item.role}
                  onClick={() => {
                    setActiveRole(item.role);
                    setShowRoleDropdown(false);
                  }}
                  className={`flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-xs text-slate-700 transition hover:bg-slate-50 ${language === 'ar' ? 'text-right' : 'text-left'}`}
                >
                  <div className="flex items-center gap-2.5">
                    <span className={`inline-flex h-2 w-2 rounded-full ${item.role === 'super_admin' ? 'bg-red-500' : item.role === 'moderator' ? 'bg-emerald-500' : item.role === 'financial_manager' ? 'bg-blue-500' : 'bg-amber-500'}`} />
                    <span className="font-medium">{item.name}</span>
                  </div>
                  {activeRole === item.role && (
                    <Check className="h-4 w-4 text-indigo-600" />
                  )}
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Language Toggle */}
        <button
          onClick={toggleLanguage}
          className="flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none"
        >
          <Globe className="h-4 w-4 text-slate-500" />
          <span>{language === 'en' ? t.arabic : t.english}</span>
        </button>

        {/* Notifications Icon with Indicator */}
        <div className="relative">
          <button
            onClick={() => setShowNotifications(!showNotifications)}
            className="relative flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
          >
            <Bell className="h-4.5 w-4.5" />
            <span className="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-indigo-600 ring-2 ring-white" />
          </button>

          {showNotifications && (
            <div className={`absolute ${language === 'en' ? 'right-0' : 'left-0'} mt-2 w-80 rounded-xl border border-slate-100 bg-white p-3 shadow-xl ring-1 ring-black/5 z-50`}>
              <div className="flex items-center justify-between border-b border-slate-50 pb-2 mb-2">
                <span className="text-xs font-bold text-slate-800">{language === 'en' ? 'Notifications' : 'الإشعارات'}</span>
                <span className="text-[10px] text-indigo-600 font-semibold">{language === 'en' ? '3 New' : '٣ جديدة'}</span>
              </div>
              <div className="space-y-2.5">
                <div className="rounded-lg bg-indigo-50/50 p-2 text-xs transition hover:bg-indigo-50">
                  <p className="font-semibold text-slate-800">{language === 'en' ? 'New dispute ticket #501' : 'تذكرة نزاع جديدة رقم #501'}</p>
                  <p className="text-[10px] text-slate-400 mt-0.5">2 mins ago</p>
                </div>
                <div className="p-2 text-xs hover:bg-slate-50 rounded-lg">
                  <p className="font-medium text-slate-700">{language === 'en' ? 'Ahmad uploaded VR service' : 'أحمد قام برفع خدمة نظارات الواقع الافتراضي'}</p>
                  <p className="text-[10px] text-slate-400 mt-0.5">1 hour ago</p>
                </div>
                <div className="p-2 text-xs hover:bg-slate-50 rounded-lg">
                  <p className="font-medium text-slate-700">{language === 'en' ? 'STC Pay gateway configuration update' : 'تم تحديث تهيئة بوابة الدفع STC Pay'}</p>
                  <p className="text-[10px] text-slate-400 mt-0.5">Yesterday</p>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Admin Avatar */}
        <div className="flex items-center gap-2 border-l border-slate-200 pl-4 rtl:border-r rtl:border-l-0 rtl:pr-4 rtl:pl-0">
          <div className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 font-bold text-slate-600 text-xs">
            JD
          </div>
          <div className="hidden md:block">
            <p className="text-xs font-bold text-slate-800">John Doe</p>
            <p className="text-[10px] text-slate-400 font-medium">Laravel Admin App</p>
          </div>
        </div>

        <button
          onClick={onLogout}
          className="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 hover:text-rose-800"
        >
          <LogOut className="h-4 w-4" />
          <span>{t.logout}</span>
        </button>
      </div>
    </header>
  );
}
