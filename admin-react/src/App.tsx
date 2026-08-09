/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, useEffect } from 'react';
import { Language, UserRole, AddonModuleKey, Permission } from './types';
import Header from './components/Header';
import Sidebar from './components/Sidebar';
import Breadcrumbs from './components/Breadcrumbs';
import DashboardView from './components/DashboardView';
import ServicesView from './components/ServicesView';
import UsersView from './components/UsersView';
import OrdersView from './components/OrdersView';
import PaymentsView from './components/PaymentsView';
import SupportView from './components/SupportView';
import CMSView from './components/CMSView';
import LocalizationView from './components/LocalizationView';
import SettingsView from './components/SettingsView';
import AddonLockedView from './components/AddonLockedView';
import AuditView from './components/AuditView';
import AdminLoginScreen from './components/AdminLoginScreen';
import { AddonsService } from './services/api';
import { checkPermission, saveAuditLog, ROLE_NAMES } from './utils/auditLogger';
import { Lock } from 'lucide-react';

// Route Blocked Fallback view with automatic audit logging
function RouteBlockedView({ view, role, language }: { view: string; role: UserRole; language: Language }) {
  const isRtl = language === 'ar';
  
  useEffect(() => {
    const viewName = view.toUpperCase();
    saveAuditLog({
      actorRole: role,
      actorName: 'Current Active Admin',
      action: 'view_attempt',
      resource: `${viewName} View Dashboard`,
      detailsEn: `Blocked unauthorized route access attempt to ${view} screen.`,
      detailsAr: `تم حظر محاولة وصول غير مصرح بها لتبويب ${viewName}.`,
      status: 'denied'
    });
  }, [view, role]);

  return (
    <div className="rounded-2xl border border-rose-100 bg-white p-12 text-center max-w-xl mx-auto shadow-xs my-10 space-y-6">
      <div className="mx-auto h-16 w-16 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center animate-pulse">
        <Lock className="h-8 w-8" />
      </div>
      
      <div className="space-y-2">
        <h3 className="text-lg font-black text-rose-900 tracking-tight">
          {isRtl ? 'عذراً، الوصول إلى هذا القسم مقيد!' : 'Security Intercept: Route Restricted'}
        </h3>
        <p className="text-[10px] font-bold text-rose-500 uppercase tracking-widest font-mono">
          POLICY_GUARD_ROUTE_RESTRICTION
        </p>
      </div>

      <p className="text-xs text-slate-500 leading-relaxed max-w-md mx-auto">
        {isRtl
          ? `حسابك الحالي المُمثَّل بصفة "${ROLE_NAMES[role]?.ar || role}" لا يمتلك الصلاحيات الكافية لاستعراض تبويب "${view}". تم رصد وتسجيل هذه المحاولة في سجلات الأمان بنجاح.`
          : `Your currently active simulation role "${ROLE_NAMES[role]?.en || role}" does not possess the required policies to access the "${view}" workspace. This access attempt has been logged under security audit protocols.`}
      </p>

      <div className="rounded-xl bg-slate-50 border border-slate-200/60 p-3.5 text-xs text-slate-600 font-medium">
        {isRtl
          ? 'تنويه: يمكنك التغيير إلى دور "مشرف عام النظام" (Super Admin) من القائمة المنسدلة في الأعلى لاستكشاف كافة الصفحات.'
          : 'Tip: You can switch your Simulated Role to "Super Admin" in the top-header dropdown to unlock this view immediately.'}
      </div>
    </div>
  );
}

export default function App() {
  const [language, setLanguage] = useState<Language>('en');
  const [activeRole, setActiveRole] = useState<UserRole>('super_admin');
  const [currentView, setCurrentView] = useState<string>('dashboard');
  const [authState, setAuthState] = useState<'checking' | 'authenticated' | 'unauthenticated'>('checking');

  // Sidebar collapsible state
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState<boolean>(false);
  // Mobile drawer state
  const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState<boolean>(false);

  // Addon modules lock/unlock states
  const [lockedModules, setLockedModules] = useState<Record<AddonModuleKey, boolean>>({
    wallet: true,
    chat: true,
    jobs: true,
    subscription: true,
  });

  // Automatically sync document dir and lang attributes for perfect screen reader and CSS rendering
  useEffect(() => {
    const dir = language === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.dir = dir;
    document.documentElement.lang = language;
  }, [language]);

  useEffect(() => {
    let cancelled = false;

    const verifyAdminSession = async () => {
      try {
        const response = await fetch('/admin-home/auth-check', {
          method: 'GET',
          credentials: 'include',
          headers: { Accept: 'application/json' },
        });

        const contentType = response.headers.get('content-type') || '';
        if (response.ok && contentType.includes('application/json')) {
          const payload = await response.json();
          if (!cancelled && payload?.success && payload?.data?.authenticated) {
            setAuthState('authenticated');
            return;
          }
        }
      } catch {
        // fall through to login gate
      }

      if (!cancelled) {
        setAuthState('unauthenticated');
      }
    };

    verifyAdminSession();

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    let cancelled = false;

    const syncFeatureFlags = async () => {
      try {
        const response = await AddonsService.getFeatureFlags(language);
        if (cancelled || !response?.success || !response.data?.modules) {
          return;
        }

        const nextStates = response.data.modules.reduce((acc, module) => {
          if (module.key in acc) {
            acc[module.key as AddonModuleKey] = module.locked;
          }
          return acc;
        }, {
          wallet: true,
          chat: true,
          jobs: true,
          subscription: true,
        } as Record<AddonModuleKey, boolean>);

        setLockedModules(nextStates);
      } catch {
        // Keep the local fallback state if the admin feature-flag endpoint is unavailable.
      }
    };

    syncFeatureFlags();

    return () => {
      cancelled = true;
    };
  }, [language]);

  const handleToggleLock = (key: AddonModuleKey) => {
    const previousLocked = lockedModules[key];
    const nextLocked = !previousLocked;

    setLockedModules(prev => ({
      ...prev,
      [key]: nextLocked
    }));

    AddonsService.updateFeatureFlag(key, !nextLocked, language).catch(() => {
      setLockedModules(prev => ({
        ...prev,
        [key]: previousLocked
      }));
    });
  };

  const handleAuthenticated = () => {
    setAuthState('checking');

    fetch('/admin-home/auth-check', {
      method: 'GET',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
      .then(async (response) => {
        const contentType = response.headers.get('content-type') || '';
        if (response.ok && contentType.includes('application/json')) {
          const payload = await response.json();
          if (payload?.success && payload?.data?.authenticated) {
            setAuthState('authenticated');
            return;
          }
        }
        setAuthState('unauthenticated');
      })
      .catch(() => setAuthState('unauthenticated'));
  };

  const handleLogout = async () => {
    try {
      await fetch('/logout/admin', {
        method: 'GET',
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });
    } catch {
      // We still clear the local UI state even if the network call fails.
    } finally {
      setCurrentView('dashboard');
      setAuthState('unauthenticated');
    }
  };

  // Map of views to their corresponding permission requirement
  const VIEW_PERMISSIONS: Record<string, Permission> = {
    services: 'manage_services',
    users: 'manage_users',
    orders: 'manage_orders',
    payments: 'manage_payments',
    support: 'manage_support',
    cms: 'manage_cms',
    localization: 'manage_cms', // CMS control or translation key control
    settings: 'manage_settings',
  };

  // Render view conditionally based on currentView state
  const renderViewContent = () => {
    // Check route guard permissions first
    const requiredPermission = VIEW_PERMISSIONS[currentView];
    if (requiredPermission && !checkPermission(activeRole, requiredPermission)) {
      return <RouteBlockedView view={currentView} role={activeRole} language={language} />;
    }

    switch (currentView) {
      case 'dashboard':
        return <DashboardView language={language} />;
      case 'services':
        return <ServicesView language={language} activeRole={activeRole} />;
      case 'users':
        return <UsersView language={language} activeRole={activeRole} />;
      case 'orders':
        return <OrdersView language={language} activeRole={activeRole} />;
      case 'payments':
        return <PaymentsView language={language} activeRole={activeRole} />;
      case 'support':
        return <SupportView language={language} activeRole={activeRole} />;
      case 'cms':
        return <CMSView language={language} activeRole={activeRole} />;
      case 'localization':
        return <LocalizationView language={language} />;
      case 'settings':
        return <SettingsView language={language} activeRole={activeRole} />;
      case 'audit':
        return <AuditView language={language} activeRole={activeRole} />;
      
      // Add-on modules (which display locked state)
      case 'wallet':
      case 'chat':
      case 'jobs':
      case 'subscription':
        return (
          <AddonLockedView
            moduleKey={currentView as AddonModuleKey}
            language={language}
            isLocked={lockedModules[currentView as AddonModuleKey]}
            onToggleLock={handleToggleLock}
          />
        );
      
      default:
        return <DashboardView language={language} />;
    }
  };

  return (
    <div
      dir={language === 'ar' ? 'rtl' : 'ltr'}
      className="min-h-screen bg-slate-50 font-sans text-slate-800 transition-all duration-150 antialiased"
    >
      {authState !== 'authenticated' ? (
        <AdminLoginScreen
          language={language}
          setLanguage={setLanguage}
          onAuthenticated={handleAuthenticated}
          checkingSession={authState === 'checking'}
        />
      ) : (
        <>
      {/* Top sticky bar */}
      <Header
        language={language}
        setLanguage={setLanguage}
        activeRole={activeRole}
        setActiveRole={setActiveRole}
        onToggleMobileSidebar={() => setIsMobileSidebarOpen(!isMobileSidebarOpen)}
        onLogout={handleLogout}
      />

      <div className="flex">
        {/* Navigation panel */}
        <Sidebar
          currentView={currentView}
          setCurrentView={setCurrentView}
          language={language}
          lockedModules={lockedModules}
          isCollapsed={isSidebarCollapsed}
          onToggleCollapse={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
          isMobileOpen={isMobileSidebarOpen}
          onCloseMobile={() => setIsMobileSidebarOpen(false)}
        />

        {/* Content canvas container */}
        <main className="flex-1 p-6 md:p-8 max-w-7xl mx-auto overflow-x-hidden min-h-[calc(100vh-4rem)]">
          {/* Breadcrumbs Navigation */}
          <Breadcrumbs currentView={currentView} language={language} />
          
          {/* Active Business Page */}
          {renderViewContent()}
        </main>
      </div>
        </>
      )}
    </div>
  );
}
