/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, AddonModuleKey } from '../types';
import { translations } from '../translations';
import {
  LayoutDashboard,
  Briefcase,
  Users,
  ShoppingBag,
  CreditCard,
  LifeBuoy,
  FileText,
  Languages,
  Settings2,
  Wallet,
  MessageSquare,
  Hammer,
  Award,
  Lock,
  Unlock,
  ChevronLeft,
  ChevronRight,
  ShieldCheck,
  X
} from 'lucide-react';
import logo from '../../thelogo.png';

interface SidebarProps {
  currentView: string;
  setCurrentView: (view: string) => void;
  language: Language;
  lockedModules: Record<AddonModuleKey, boolean>;
  isCollapsed: boolean;
  onToggleCollapse: () => void;
  isMobileOpen: boolean;
  onCloseMobile: () => void;
}

export default function Sidebar({
  currentView,
  setCurrentView,
  language,
  lockedModules,
  isCollapsed,
  onToggleCollapse,
  isMobileOpen,
  onCloseMobile
}: SidebarProps) {
  const t = translations[language];
  const isRtl = language === 'ar';

  // Core platform features
  const menuItems = [
    { view: 'dashboard', label: t.nav_dashboard, icon: LayoutDashboard },
    { view: 'services', label: t.nav_services, icon: Briefcase },
    { view: 'users', label: t.nav_users, icon: Users },
    { view: 'orders', label: t.nav_orders, icon: ShoppingBag },
    { view: 'payments', label: t.nav_payments, icon: CreditCard },
    { view: 'support', label: t.nav_support, icon: LifeBuoy },
    { view: 'cms', label: t.nav_cms, icon: FileText },
    { view: 'localization', label: t.nav_localization, icon: Languages },
    { view: 'settings', label: t.nav_settings, icon: Settings2 },
    { view: 'audit', label: isRtl ? 'الأمان وسجل التدقيق' : 'Security & Audit Logs', icon: ShieldCheck },
  ];

  // Add-on modules
  const addonItems = [
    { view: 'wallet', label: t.nav_wallet, icon: Wallet, moduleKey: 'wallet' as AddonModuleKey },
    { view: 'chat', label: t.nav_chat, icon: MessageSquare, moduleKey: 'chat' as AddonModuleKey },
    { view: 'jobs', label: t.nav_jobs, icon: Hammer, moduleKey: 'jobs' as AddonModuleKey },
    { view: 'subscription', label: t.nav_subscription, icon: Award, moduleKey: 'subscription' as AddonModuleKey },
  ];

  const sidebarContent = (
    <div className="flex flex-col h-full bg-slate-900 text-white select-none">
      {/* Sidebar header (with collapse button for desktop / close button for mobile) */}
      <div className="flex h-16 items-center justify-between border-b border-slate-800 px-4 shrink-0">
        {!isCollapsed && (
          <div className="flex items-center gap-2">
            <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl border border-slate-700 bg-white shadow-sm">
              <img src={logo} alt="FUN MOMENT" className="h-full w-full object-cover" />
            </div>
            <div className="leading-tight">
              <span className="block text-xs font-black tracking-widest text-indigo-400">FUN MOMENT</span>
              <span className="rounded bg-slate-800 px-1 py-0.5 text-[8px] font-bold text-slate-400 uppercase">v2.0</span>
            </div>
          </div>
        )}
        {isCollapsed && (
          <div className="mx-auto flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl border border-slate-700 bg-white">
            <img src={logo} alt="FUN MOMENT" className="h-full w-full object-cover" />
          </div>
        )}

        {/* Collapse Toggle for Desktop */}
        <button
          onClick={onToggleCollapse}
          className="hidden md:flex h-7 w-7 items-center justify-center rounded-lg border border-slate-800 bg-slate-950/40 text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150 focus:outline-none"
          title={isCollapsed ? (isRtl ? 'توسيع' : 'Expand') : (isRtl ? 'طي' : 'Collapse')}
        >
          {isCollapsed ? (
            isRtl ? <ChevronLeft className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />
          ) : (
            isRtl ? <ChevronRight className="h-4 w-4" /> : <ChevronLeft className="h-4 w-4" />
          )}
        </button>

        {/* Close Toggle for Mobile Drawer */}
        <button
          onClick={onCloseMobile}
          className="flex md:hidden h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-800 hover:text-white focus:outline-none"
        >
          <X className="h-5 w-5" />
        </button>
      </div>

      {/* Main navigation list */}
      <div className="flex-1 overflow-y-auto p-3 space-y-6">
        {/* Core Platform Section */}
        <div>
          {!isCollapsed ? (
            <span className="text-[10px] font-bold tracking-wider text-slate-500 uppercase px-3 block">
              {isRtl ? 'النظام الأساسي' : 'Core Platform'}
            </span>
          ) : (
            <div className="border-t border-slate-800/60 my-2 mx-1" />
          )}
          <ul className="mt-2 space-y-1">
            {menuItems.map(item => {
              const Icon = item.icon;
              const isActive = currentView === item.view;
              return (
                <li key={item.view}>
                  <button
                    onClick={() => {
                      setCurrentView(item.view);
                      onCloseMobile();
                    }}
                    className={`flex w-full items-center gap-3 rounded-lg py-2.5 text-xs font-semibold transition duration-150 focus:outline-none ${
                      isCollapsed ? 'justify-center px-0' : 'px-3'
                    } ${
                      isActive
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/10'
                        : 'text-slate-400 hover:bg-slate-800/50 hover:text-white'
                    }`}
                    title={isCollapsed ? item.label : undefined}
                  >
                    <Icon className={`h-4.5 w-4.5 shrink-0 ${isActive ? 'text-white' : 'text-slate-500'}`} />
                    {!isCollapsed && <span className="truncate">{item.label}</span>}
                  </button>
                </li>
              );
            })}
          </ul>
        </div>

        {/* Add-on Modules Section */}
        <div>
          {!isCollapsed ? (
            <span className="text-[10px] font-bold tracking-wider text-slate-500 uppercase px-3 flex items-center justify-between">
              <span>{isRtl ? 'الخدمات المضافة' : 'Add-on Modules'}</span>
              <span className="bg-slate-800 text-[8px] text-slate-400 rounded px-1 py-0.5">LOCKED</span>
            </span>
          ) : (
            <div className="border-t border-slate-800/60 my-2 mx-1" />
          )}
          <ul className="mt-2 space-y-1">
            {addonItems.map(item => {
              const Icon = item.icon;
              const isLocked = lockedModules[item.moduleKey];
              const isActive = currentView === item.view;
              return (
                <li key={item.view}>
                  <button
                    onClick={() => {
                      setCurrentView(item.view);
                      onCloseMobile();
                    }}
                    className={`flex w-full items-center justify-between rounded-lg py-2.5 text-xs font-semibold transition duration-150 focus:outline-none ${
                      isCollapsed ? 'justify-center px-0' : 'px-3'
                    } ${
                      isActive
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/10'
                        : 'text-slate-400 hover:bg-slate-800/50 hover:text-white'
                    }`}
                    title={isCollapsed ? item.label : undefined}
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <Icon className={`h-4.5 w-4.5 shrink-0 ${isActive ? 'text-white' : 'text-slate-500'}`} />
                      {!isCollapsed && (
                        <span className="truncate">
                          {item.label}
                        </span>
                      )}
                    </div>
                    {!isCollapsed && (
                      isLocked ? (
                        <Lock className="h-3 w-3 text-slate-500 shrink-0 ml-1 mr-1" />
                      ) : (
                        <Unlock className="h-3 w-3 text-emerald-400 shrink-0 ml-1 mr-1" />
                      )
                    )}
                  </button>
                </li>
              );
            })}
          </ul>
        </div>
      </div>

      {/* Footer System Status Badge */}
      {!isCollapsed && (
        <div className="p-3 border-t border-slate-800 bg-slate-950/20 shrink-0">
          <div className="flex items-center gap-2 rounded-lg bg-emerald-500/5 p-2 border border-emerald-500/10">
            <span className="relative flex h-2 w-2">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <div className="min-w-0">
              <p className="text-[9px] font-bold text-emerald-400 uppercase truncate">Laravel Connected</p>
              <p className="text-[8px] text-emerald-500/60 truncate">Schema v1.2.4</p>
            </div>
          </div>
        </div>
      )}
    </div>
  );

  return (
    <>
      {/* Mobile Drawer Slide-over Backdrop */}
      {isMobileOpen && (
        <div
          onClick={onCloseMobile}
          className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs transition-opacity md:hidden"
        />
      )}

      {/* Mobile Drawer container */}
      <aside
        className={`fixed inset-y-0 z-50 flex w-64 flex-col bg-slate-900 border-r border-slate-800 text-white transition-transform duration-300 md:hidden ${
          isRtl ? 'right-0' : 'left-0'
        } ${
          isMobileOpen 
            ? 'translate-x-0' 
            : isRtl ? 'translate-x-full' : '-translate-x-full'
        }`}
      >
        {sidebarContent}
      </aside>

      {/* Desktop Persistent Sidebar */}
      <aside
        className={`hidden md:flex flex-col bg-slate-900 border-r border-slate-800 text-white sticky top-16 h-[calc(100vh-4rem)] shrink-0 transition-all duration-300 z-30 shadow-xl ${
          isCollapsed ? 'w-20' : 'w-64'
        }`}
      >
        {sidebarContent}
      </aside>
    </>
  );
}
