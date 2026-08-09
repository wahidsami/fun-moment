/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { AdminAccount, AdminRoleRecord, Language, Permission, User, UserRole } from '../types';
import { translations } from '../translations';
import { useState, useEffect } from 'react';
import { LaravelAPI } from '../api';
import { saveAuditLog, saveChangeRecord } from '../utils/auditLogger';
import {
  Search,
  ShieldAlert,
  Award,
  Ban,
  UserCheck,
  Edit2,
  CheckCircle2,
  RefreshCcw,
  Plus,
  Trash2,
  Check,
  Building,
  DollarSign,
  Shield,
  Briefcase,
  Eye
} from 'lucide-react';

interface UsersViewProps {
  language: Language;
  activeRole: UserRole;
}

// Extended types for audit profile details
interface SellerProfile {
  userId: number;
  storeName: string;
  crNumber: string; // Commercial Register
  vatNumber: string; // VAT ID
  commissionRate: number; // custom platform rate
  commissionOwed: number;
  totalEarnings: number;
  rating: number;
  isVerified: boolean;
}

interface BuyerProfile {
  userId: number;
  bookingsCount: number;
  totalSpent: number;
  preferredLocale: string;
  registrationIP: string;
}

const DEFAULT_PERMISSION_MATRIX: Record<Permission, boolean> = {
  manage_users: false,
  manage_services: false,
  manage_orders: false,
  manage_payments: false,
  manage_support: false,
  manage_settings: false,
  manage_cms: false,
  view_analytics: false,
};

export default function UsersView({ language, activeRole }: UsersViewProps) {
  const t = translations[language];
  const isRtl = language === 'ar';

  const [activeSubTab, setActiveSubTab] = useState<'sellers' | 'buyers' | 'admins'>('sellers');
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  // Audit modals
  const [selectedSellerId, setSelectedSellerId] = useState<number | null>(null);
  const [selectedBuyerId, setSelectedBuyerId] = useState<number | null>(null);

  // Edit Wallet
  const [editingBalanceUser, setEditingBalanceUser] = useState<User | null>(null);
  const [newBalance, setNewBalance] = useState<string>('');

  // Admin and permission controls
  const [adminUsers, setAdminUsers] = useState<AdminAccount[]>([]);
  const [adminRoles, setAdminRoles] = useState<AdminRoleRecord[]>([]);

  const [selectedAdminId, setSelectedAdminId] = useState<number | null>(null);
  const [permissionsMatrix, setPermissionsMatrix] = useState<Record<Permission, boolean>>({
    ...DEFAULT_PERMISSION_MATRIX,
  });

  // Seller and buyer profiles come only from the backend now.
  const [sellerProfiles, setSellerProfiles] = useState<Record<number, SellerProfile>>({});

  const [buyerProfiles] = useState<Record<number, BuyerProfile>>({});

  const hasPermission = activeRole === 'super_admin' || activeRole === 'moderator';

  const applyAdminPermissions = (adminId: number | null, nextAdmins: AdminAccount[] = adminUsers, nextRoles: AdminRoleRecord[] = adminRoles) => {
    if (!adminId) {
      setPermissionsMatrix({ ...DEFAULT_PERMISSION_MATRIX });
      return;
    }

    const selectedAdmin = nextAdmins.find(admin => admin.id === adminId);
    const selectedRoleName = selectedAdmin?.role?.toLowerCase() ?? '';
    const normalizedRoleKey = selectedRoleName ? selectedRoleName.replace(/[^a-z0-9]+/g, '_') : '';
    const roleKey = selectedAdmin?.role_key ?? normalizedRoleKey;
    const matchedRole = nextRoles.find(role => role.key === roleKey || role.name.toLowerCase() === selectedRoleName);

    if (matchedRole?.ui_permissions) {
      setPermissionsMatrix({
        ...DEFAULT_PERMISSION_MATRIX,
        ...matchedRole.ui_permissions,
      });
      return;
    }

    if (roleKey === 'super_admin') {
      setPermissionsMatrix({
        manage_users: true,
        manage_services: true,
        manage_orders: true,
        manage_payments: true,
        manage_support: true,
        manage_settings: true,
        manage_cms: true,
        view_analytics: true,
      });
      return;
    }

    if (roleKey === 'moderator') {
      setPermissionsMatrix({
        manage_users: true,
        manage_services: true,
        manage_orders: true,
        manage_payments: false,
        manage_support: false,
        manage_settings: false,
        manage_cms: true,
        view_analytics: true,
      });
      return;
    }

    if (roleKey === 'financial_manager') {
      setPermissionsMatrix({
        manage_users: false,
        manage_services: false,
        manage_orders: true,
        manage_payments: true,
        manage_support: false,
        manage_settings: false,
        manage_cms: false,
        view_analytics: true,
      });
      return;
    }

    if (roleKey === 'support_agent') {
      setPermissionsMatrix({
        manage_users: false,
        manage_services: false,
        manage_orders: false,
        manage_payments: false,
        manage_support: true,
        manage_settings: false,
        manage_cms: false,
        view_analytics: false,
      });
      return;
    }

    setPermissionsMatrix({ ...DEFAULT_PERMISSION_MATRIX });
  };

  const loadUsers = async () => {
    setLoading(true);
    try {
      const data = await LaravelAPI.getUsers();
      setUsers(data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const loadAdminDirectory = async () => {
    try {
      const directory = await LaravelAPI.getAdminDirectory();
      const nextAdmins = Array.isArray(directory.admins) ? directory.admins : [];
      setAdminUsers(nextAdmins);

      if (Array.isArray(directory.roles)) {
        setAdminRoles(directory.roles);
      } else {
        setAdminRoles([]);
      }

      const nextSelectedAdminId = selectedAdminId ?? nextAdmins[0]?.id ?? null;
      if (nextSelectedAdminId) {
        setSelectedAdminId(nextSelectedAdminId);
        applyAdminPermissions(nextSelectedAdminId, nextAdmins, Array.isArray(directory.roles) ? directory.roles : []);
      }
    } catch (error) {
      console.error(error);
      setAdminUsers([]);
      setAdminRoles([]);
      setSelectedAdminId(null);
      setPermissionsMatrix({ ...DEFAULT_PERMISSION_MATRIX });
    }
  };

  useEffect(() => {
    loadUsers();
    loadAdminDirectory();
  }, []);

  const handleUpdateStatus = async (id: number, status: 'active' | 'suspended') => {
    if (!hasPermission) {
      saveAuditLog({
        actorRole: activeRole,
        actorName: 'Current Active Admin',
        action: 'status change',
        resource: `User Account #${id}`,
        detailsEn: `Unauthorized attempt by ${activeRole} to change user status to ${status}.`,
        detailsAr: `محاولة غير مصرح بها من ${activeRole} لتغيير حالة المستخدم إلى ${status}.`,
        status: 'denied'
      });
      alert(language === 'en' ? "Access Denied: Read-Only or Unauthorized role." : "تم رفض الوصول: دورك غير مصرح له.");
      return;
    }
    try {
      const original = users.find(u => u.id === id);
      const updated = await LaravelAPI.updateUserStatus(id, status);
      setUsers(users.map(u => u.id === id ? updated : u));

      // Audit Log
      saveAuditLog({
        actorRole: activeRole,
        actorName: 'Current Active Admin',
        action: 'status change',
        resource: `User #${id} (${updated.name})`,
        detailsEn: `Set user status to ${status}.`,
        detailsAr: `تعديل حالة حساب العميل إلى ${status === 'active' ? 'نشط' : 'موقوف'}.`,
        status: 'success'
      });

      if (original) {
        saveChangeRecord({
          resource: `User Account #${id} (${updated.name})`,
          actorName: 'Current Active Admin',
          field: 'Account Status',
          oldValue: original.status.toUpperCase(),
          newValue: status.toUpperCase()
        });
      }

      showSuccess(language === 'en' ? `User account set to ${status}!` : `تم تعديل حالة حساب المستخدم بنجاح!`);
    } catch (err) {
      console.error(err);
    }
  };

  const handleUpdateBalance = async () => {
    if (!editingBalanceUser) return;
    const amountVal = parseFloat(newBalance);
    if (isNaN(amountVal) || amountVal < 0) {
      alert("Invalid balance amount");
      return;
    }

    // Role Guard Check: Only super_admin or financial_manager can edit balances!
    const isAuthorized = activeRole === 'super_admin' || activeRole === 'financial_manager';
    if (!isAuthorized) {
      saveAuditLog({
        actorRole: activeRole,
        actorName: 'Current Active Admin',
        action: 'payout-related',
        resource: `User Wallet Balance #${editingBalanceUser.id}`,
        detailsEn: `Unauthorized wallet balance modification attempt by ${activeRole}.`,
        detailsAr: `محاولة غير مصرح بها لتعديل الرصيد المالي من ${activeRole}.`,
        status: 'denied'
      });
      alert(language === 'en' ? "Access Denied: Balance adjustments require financial rights." : "تم الرفض: يتطلب تعديل الأرصدة صلاحيات مالية.");
      return;
    }

    try {
      const original = users.find(u => u.id === editingBalanceUser.id);
      const updated = await LaravelAPI.updateUserBalance(editingBalanceUser.id, amountVal);
      setUsers(users.map(u => u.id === editingBalanceUser.id ? updated : u));
      setEditingBalanceUser(null);

      // Audit Log
      saveAuditLog({
        actorRole: activeRole,
        actorName: 'Current Active Admin',
        action: 'payout-related',
        resource: `User Wallet #${editingBalanceUser.id} (${updated.name})`,
        detailsEn: `Manually adjusted ledger balance to ${amountVal} SAR.`,
        detailsAr: `تسوية رصيد المحفظة المالي يدوياً إلى ${amountVal} ريال سعودي.`,
        status: 'success'
      });

      if (original) {
        saveChangeRecord({
          resource: `User Wallet Balance #${editingBalanceUser.id} (${updated.name})`,
          actorName: 'Current Active Admin',
          field: 'Ledger Balance',
          oldValue: `${original.wallet_balance || 0} SAR`,
          newValue: `${amountVal} SAR`
        });
      }

      showSuccess(language === 'en' ? "User ledger balance adjusted!" : "تم تسوية رصيد حساب العميل المالي بنجاح!");
    } catch (err) {
      console.error(err);
    }
  };

  const toggleVerification = (userId: number) => {
    if (!hasPermission) {
      alert(language === 'en' ? "Access Denied." : "تم رفض الوصول.");
      return;
    }
    const profile = sellerProfiles[userId];
    if (!profile) return;

    const nextVerified = !profile.isVerified;

    setSellerProfiles({
      ...sellerProfiles,
      [userId]: { ...profile, isVerified: nextVerified }
    });

    // Audit Log
    const sellerUser = users.find(u => u.id === userId);
    saveAuditLog({
      actorRole: activeRole,
      actorName: 'Current Active Admin',
      action: 'status change',
      resource: `Seller #${userId} ${sellerUser ? `(${sellerUser.name})` : ''}`,
      detailsEn: `Toggled Commercial verification to ${nextVerified ? 'Verified' : 'Unverified'}.`,
      detailsAr: `تعديل حالة مطابقة وتوثيق السجل التجاري للبائع إلى ${nextVerified ? 'موثق' : 'غير موثق'}.`,
      status: 'success'
    });

    saveChangeRecord({
      resource: `Seller Verification Status #${userId}`,
      actorName: 'Current Active Admin',
      field: 'CR_VERIFIED_STATUS',
      oldValue: profile.isVerified ? 'VERIFIED' : 'UNVERIFIED',
      newValue: nextVerified ? 'VERIFIED' : 'UNVERIFIED'
    });

    showSuccess(
      language === 'en' 
        ? "Seller verification status updated!" 
        : "تم تحديث حالة توثيق البائع والربط الوطني!"
    );
  };

  const showSuccess = (msg: string) => {
    setSuccessMsg(msg);
    setTimeout(() => setSuccessMsg(''), 3000);
  };

  // Filter lists
  const sellersList = users.filter(u => u.role === 'seller');
  const buyersList = users.filter(u => u.role === 'buyer');

  const filteredSellers = sellersList.filter(user => {
    const profile = sellerProfiles[user.id];
    const store = profile?.storeName || '';
    return (
      user.name.toLowerCase().includes(search.toLowerCase()) ||
      user.email.toLowerCase().includes(search.toLowerCase()) ||
      store.toLowerCase().includes(search.toLowerCase())
    );
  });

  const filteredBuyers = buyersList.filter(user => {
    return (
      user.name.toLowerCase().includes(search.toLowerCase()) ||
      user.email.toLowerCase().includes(search.toLowerCase()) ||
      user.country.toLowerCase().includes(search.toLowerCase())
    );
  });

  return (
    <div className="space-y-6">
      {/* View Header with Sub-Tabs */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-slate-800">
            {t.users_title}
          </h2>
          <p className="text-xs text-slate-500">
            {t.users_subtitle}
          </p>
        </div>

        {/* Top tab selector */}
        <div className="flex flex-wrap items-center gap-1.5 rounded-xl bg-slate-100 p-1 text-xs font-semibold self-start sm:self-center">
          <button
            onClick={() => setActiveSubTab('sellers')}
            className={`rounded-lg px-3 py-1.5 transition ${
              activeSubTab === 'sellers' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'
            }`}
          >
            {language === 'en' ? 'Sellers & Providers' : 'البائعين ومزودي الخدمات'}
          </button>
          <button
            onClick={() => setActiveSubTab('buyers')}
            className={`rounded-lg px-3 py-1.5 transition ${
              activeSubTab === 'buyers' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'
            }`}
          >
            {language === 'en' ? 'Buyers & Clients' : 'المشترين والعملاء'}
          </button>
          <button
            onClick={() => setActiveSubTab('admins')}
            className={`rounded-lg px-3 py-1.5 transition ${
              activeSubTab === 'admins' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'
            }`}
          >
            {language === 'en' ? 'Roles & Permissions' : 'المشرفين والصلاحيات'}
          </button>
        </div>
      </div>

      {successMsg && (
        <div className="rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-xs text-emerald-800 font-semibold animate-fade-in flex items-center gap-1.5">
          <CheckCircle2 className="h-4 w-4 text-emerald-600" />
          <span>{successMsg}</span>
        </div>
      )}

      {/* Search Filter for Tables */}
      {activeSubTab !== 'admins' && (
        <div className="relative max-w-md">
          <Search className="absolute top-2.5 left-3 h-4 w-4 text-slate-400 rtl:right-3 rtl:left-auto" />
          <input
            type="text"
            placeholder={t.searchPlaceholder}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs outline-hidden focus:border-indigo-500 rtl:pr-9 rtl:pl-3"
          />
        </div>
      )}

      {/* ======================= SUB TAB 1: SELLERS DIRECTORY ======================= */}
      {activeSubTab === 'sellers' && (
        <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-sm">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-500 rtl:text-right">
              <thead className="bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase border-b border-slate-100">
                <tr>
                  <th className="px-6 py-4">ID</th>
                  <th className="px-6 py-4">{language === 'en' ? 'Seller Store Name' : 'اسم متجر مزود الخدمة'}</th>
                  <th className="px-6 py-4">{language === 'en' ? 'Legal Name' : 'الاسم النظامي والبريد'}</th>
                  <th className="px-6 py-4">{language === 'en' ? 'CR Status' : 'السجل التجاري توثيق'}</th>
                  <th className="px-6 py-4">{language === 'en' ? 'Commission' : 'العمولة'}</th>
                  <th className="px-6 py-4">{language === 'en' ? 'Earning Balance' : 'رصيد الأرباح الحالية'}</th>
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
                ) : filteredSellers.length === 0 ? (
                  <tr>
                    <td colSpan={8} className="py-10 text-center text-slate-400">
                      {language === 'en' ? 'No sellers found matching search.' : 'لا يوجد مزودو خدمة يطابقون خيارات البحث.'}
                    </td>
                  </tr>
                ) : (
                  filteredSellers.map((user) => {
                    const profile = sellerProfiles[user.id];
                    return (
                      <tr key={user.id} className="hover:bg-slate-50/50 transition-colors">
                        <td className="px-6 py-4 text-slate-400 font-bold">#{user.id}</td>
                        <td className="px-6 py-4">
                          <div className="font-bold text-slate-800">
                            {profile?.storeName || '—'}
                          </div>
                          <div className="text-[10px] text-slate-400 mt-0.5">{user.phone}</div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="font-bold text-slate-700">{user.name}</div>
                          <div className="text-[10px] text-slate-400 font-mono">{user.email}</div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex items-center gap-1">
                            <span className={`inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[9px] font-bold ${
                              profile?.isVerified ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                            }`}>
                              {profile?.isVerified ? (language === 'en' ? 'CR Verified' : 'سجل موثق') : (language === 'en' ? 'CR Pending' : 'غير موثق')}
                            </span>
                          </div>
                        </td>
                        <td className="px-6 py-4 text-slate-600 font-bold">
                          {profile?.commissionRate}%
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex items-center gap-1">
                            <span className="font-bold text-slate-900">{user.wallet_balance?.toLocaleString()} SAR</span>
                            {hasPermission && (
                              <button
                                onClick={() => {
                                  setEditingBalanceUser(user);
                                  setNewBalance(user.wallet_balance?.toString() || '0');
                                }}
                                className="text-slate-400 hover:text-indigo-600 transition"
                              >
                                <Edit2 className="h-3 w-3" />
                              </button>
                            )}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${
                            user.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                          }`}>
                            {user.status === 'active' ? t.status_active : t.status_suspended}
                          </span>
                        </td>
                        <td className="px-6 py-4 text-center">
                          <div className="flex items-center justify-center gap-1">
                            <button
                              onClick={() => setSelectedSellerId(user.id)}
                              className="rounded bg-slate-100 p-1 text-slate-600 hover:bg-slate-200"
                              title={language === 'en' ? 'Audit Store Profile' : 'مراجعة وتوثيق المتجر'}
                            >
                              <Eye className="h-4 w-4" />
                            </button>
                            {hasPermission && (
                              user.status === 'active' ? (
                                <button
                                  onClick={() => handleUpdateStatus(user.id, 'suspended')}
                                  className="rounded p-1 text-rose-600 hover:bg-rose-50"
                                  title={t.btn_suspend_user}
                                >
                                  <Ban className="h-4 w-4" />
                                </button>
                              ) : (
                                <button
                                  onClick={() => handleUpdateStatus(user.id, 'active')}
                                  className="rounded p-1 text-emerald-600 hover:bg-emerald-50"
                                  title={t.btn_activate_user}
                                >
                                  <UserCheck className="h-4 w-4" />
                                </button>
                              )
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ======================= SUB TAB 2: BUYERS DIRECTORY ======================= */}
      {activeSubTab === 'buyers' && (
        <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-sm">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-500 rtl:text-right">
              <thead className="bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase border-b border-slate-100">
                <tr>
                  <th className="px-6 py-4">ID</th>
                  <th className="px-6 py-4">{language === 'en' ? 'Client / Buyer Name' : 'اسم العميل المشتري'}</th>
                  <th className="px-6 py-4">{t.col_email}</th>
                  <th className="px-6 py-4">{language === 'en' ? 'Total Bookings' : 'مجموع الحجوزات'}</th>
                  <th className="px-6 py-4">{language === 'en' ? 'Total Amount Spent' : 'إجمالي المبالغ المصروفة'}</th>
                  <th className="px-6 py-4">{language === 'en' ? 'Preferred Language' : 'اللغة المفضلة'}</th>
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
                ) : filteredBuyers.length === 0 ? (
                  <tr>
                    <td colSpan={8} className="py-10 text-center text-slate-400">
                      {language === 'en' ? 'No buyers found.' : 'لا يوجد عملاء يطابقون معايير البحث.'}
                    </td>
                  </tr>
                ) : (
                  filteredBuyers.map((user) => {
                    const profile = buyerProfiles[user.id];
                    return (
                      <tr key={user.id} className="hover:bg-slate-50/50 transition-colors">
                        <td className="px-6 py-4 text-slate-400 font-bold">#{user.id}</td>
                        <td className="px-6 py-4">
                          <div className="font-bold text-slate-800">{user.name}</div>
                          <div className="text-[10px] text-slate-400 mt-0.5">{user.phone}</div>
                        </td>
                        <td className="px-6 py-4 font-mono text-[11px] text-slate-500">{user.email}</td>
                        <td className="px-6 py-4 text-slate-600 font-bold">
                          {profile?.bookingsCount || 0} bookings
                        </td>
                        <td className="px-6 py-4 text-slate-900 font-extrabold">
                          {(profile?.totalSpent || 0).toLocaleString()} SAR
                        </td>
                        <td className="px-6 py-4 uppercase font-mono">{profile?.preferredLocale || 'en'}</td>
                        <td className="px-6 py-4">
                          <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${
                            user.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                          }`}>
                            {user.status === 'active' ? t.status_active : t.status_suspended}
                          </span>
                        </td>
                        <td className="px-6 py-4 text-center">
                          <button
                            onClick={() => setSelectedBuyerId(user.id)}
                            className="rounded bg-slate-100 p-1 text-slate-600 hover:bg-slate-200"
                            title="Inspect Buyer Logs"
                          >
                            <Eye className="h-4 w-4" />
                          </button>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ======================= SUB TAB 3: ROLES & PERMISSIONS MATRIX ======================= */}
      {activeSubTab === 'admins' && (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          {/* Admin user accounts directory */}
          <div className="rounded-2xl border border-slate-200 bg-white p-5 space-y-4 lg:col-span-2 shadow-xs">
            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 border-b pb-2">
              {language === 'en' ? 'System Operator Accounts' : 'حسابات مدراء وموظفي النظام'}
            </h3>

            <div className="divide-y divide-slate-100">
              {adminUsers.map(admin => (
                <div 
                  key={admin.id} 
                  onClick={() => {
                    setSelectedAdminId(admin.id);
                    applyAdminPermissions(admin.id);
                  }}
                  className={`flex items-start justify-between py-3.5 cursor-pointer rounded-lg px-2 transition ${
                    selectedAdminId === admin.id ? 'bg-slate-50 border-l-2 border-indigo-600' : 'hover:bg-slate-50/40'
                  }`}
                >
                  <div>
                    <p className="text-xs font-bold text-slate-800">{admin.name}</p>
                    <p className="text-[10px] text-slate-400 font-mono mt-0.5">{admin.email}</p>
                    <p className="text-[10px] text-slate-400 mt-0.5">Last login: {admin.lastLogin}</p>
                  </div>
                  <div className="flex flex-col items-end gap-1.5">
                    <span className="rounded bg-indigo-50 px-2 py-0.5 text-[9px] font-bold text-indigo-700">
                      {admin.role}
                    </span>
                    <span className="text-[9px] text-emerald-600 font-semibold">● ACTIVE</span>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Interactive Live Permission Matrix Inspector */}
          <div className="rounded-2xl border border-slate-200 bg-white p-5 space-y-4 shadow-xs">
            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 border-b pb-2 flex items-center gap-1">
              <Shield className="h-4 w-4 text-indigo-500" />
              <span>{language === 'en' ? 'Fine-Grained Permissions Map' : 'مصفوفة التراخيص وتأمين العمليات'}</span>
            </h3>

            {selectedAdminId ? (
              <div className="space-y-4">
                <p className="text-xs text-slate-500 leading-relaxed">
                  {language === 'en' 
                    ? `Live security blueprint for administrative account ID #${selectedAdminId}. Adjust to enforce access guards.`
                    : `مخطط الأمان المباشر لحساب المشرف رقم #${selectedAdminId}. قم بتعديل التراخيص للتحكم بعمليات الدفع والنشر.`}
                </p>

                <div className="space-y-3 pt-2">
                  {Object.entries(permissionsMatrix).map(([key, enabled]) => (
                    <label 
                      key={key} 
                      className="flex items-center justify-between p-2.5 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer text-xs"
                    >
                      <span className="font-semibold text-slate-700 capitalize">
                        {key.replace('_', ' ')}
                      </span>
                      <input
                        type="checkbox"
                        disabled={!hasPermission}
                        checked={enabled}
                        onChange={(e) => {
                          setPermissionsMatrix({ ...permissionsMatrix, [key]: e.target.checked });
                          showSuccess(language === 'en' ? "Access token scope updated!" : "تمت إعادة موازنة تراخيص المشرف بنجاح!");
                        }}
                        className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4 disabled:opacity-40"
                      />
                    </label>
                  ))}
                </div>
              </div>
            ) : (
              <div className="py-12 text-center text-slate-400 text-xs">
                {language === 'en' ? 'Select an operator account to configure permissions.' : 'يرجى اختيار حساب مشرف لعرض مصفوفة تراخيصه.'}
              </div>
            )}
          </div>
        </div>
      )}

      {/* ======================= AUDIT SELLER DRAWER MODAL ======================= */}
      {selectedSellerId && (() => {
        const user = users.find(u => u.id === selectedSellerId);
        const profile = sellerProfiles[selectedSellerId];
        return (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-lg rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl animate-scale-in">
              <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <span className="text-[10px] font-bold text-slate-400 uppercase">
                  {language === 'en' ? 'Government & Platform Seller Audit' : 'ملف التدقيق الحكومي والوطني للمزود'} #{selectedSellerId}
                </span>
                <button
                  onClick={() => setSelectedSellerId(null)}
                  className="text-slate-400 hover:text-slate-600 text-sm font-bold"
                >
                  ✕
                </button>
              </div>

              {user && profile && (
                <div className="space-y-4">
                  <div className="rounded-xl bg-slate-50 p-4 border flex items-center gap-3">
                    <Building className="h-10 w-10 text-slate-400" />
                    <div>
                      <h4 className="font-bold text-slate-800 text-sm">{profile.storeName}</h4>
                      <p className="text-xs text-slate-400">{user.name} ({user.email})</p>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4 pt-2">
                    <div>
                      <h4 className="text-[10px] text-slate-400 font-bold uppercase">{language === 'en' ? 'Commercial Register (CR)' : 'السجل التجاري لمؤسسة'}</h4>
                      <p className="text-xs font-semibold text-slate-700 mt-1 font-mono">{profile.crNumber}</p>
                    </div>
                    <div>
                      <h4 className="text-[10px] text-slate-400 font-bold uppercase">{language === 'en' ? 'VAT Register ID' : 'الرقم الضريبي الموحد'}</h4>
                      <p className="text-xs font-semibold text-slate-700 mt-1 font-mono">{profile.vatNumber}</p>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4 border-t border-slate-100 pt-3">
                    <div>
                      <h4 className="text-[10px] text-slate-400 font-bold uppercase">{language === 'en' ? 'Total Platform Earnings' : 'مجموع مداخيل المنصة'}</h4>
                      <p className="text-sm font-bold text-slate-900 mt-1">{profile.totalEarnings.toLocaleString()} SAR</p>
                    </div>
                    <div>
                      <h4 className="text-[10px] text-slate-400 font-bold uppercase">{language === 'en' ? 'Verification Status' : 'حالة توثيق الربط'}</h4>
                      <div className="mt-1 flex items-center gap-1.5">
                        <span className={`inline-block rounded px-2 py-0.5 text-[9px] font-bold ${
                          profile.isVerified ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                        }`}>
                          {profile.isVerified ? 'VERIFIED' : 'UNVERIFIED'}
                        </span>
                        {hasPermission && (
                          <button
                            type="button"
                            onClick={() => toggleVerification(user.id)}
                            className="text-[10px] text-indigo-600 font-bold hover:underline"
                          >
                            Toggle
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              )}

              <div className="mt-6 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <button
                  onClick={() => setSelectedSellerId(null)}
                  className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                >
                  {t.back}
                </button>
              </div>
            </div>
          </div>
        );
      })()}

      {/* ======================= AUDIT BUYER DRAWER MODAL ======================= */}
      {selectedBuyerId && (() => {
        const user = users.find(u => u.id === selectedBuyerId);
        const profile = buyerProfiles[selectedBuyerId];
        return (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-sm rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl animate-scale-in">
              <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <span className="text-[10px] font-bold text-slate-400 uppercase">
                  {language === 'en' ? 'Buyer Activity Ledger' : 'سجل نشاط العميل المشتري'} #{selectedBuyerId}
                </span>
                <button
                  onClick={() => setSelectedBuyerId(null)}
                  className="text-slate-400 hover:text-slate-600 text-sm font-bold"
                >
                  ✕
                </button>
              </div>

              {user && profile && (
                <div className="space-y-4 text-xs">
                  <div className="rounded-xl bg-slate-50 p-4 border">
                    <h4 className="font-bold text-slate-800 text-sm">{user.name}</h4>
                    <p className="text-xs text-slate-400 mt-1">{user.email}</p>
                    <p className="text-xs text-slate-400">{user.phone}</p>
                  </div>

                  <div className="space-y-2 border-t border-slate-100 pt-3">
                    <div className="flex items-center justify-between">
                      <span className="text-slate-400 font-semibold">Total Event Bookings:</span>
                      <span className="font-bold text-slate-800">{profile.bookingsCount}</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-slate-400 font-semibold">Cumulative Value Spent:</span>
                      <span className="font-bold text-slate-800">{profile.totalSpent.toLocaleString()} SAR</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-slate-400 font-semibold">Preferred Language:</span>
                      <span className="font-mono text-slate-800 uppercase">{profile.preferredLocale}</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-slate-400 font-semibold">Registration IP Address:</span>
                      <span className="font-mono text-slate-500">{profile.registrationIP}</span>
                    </div>
                  </div>
                </div>
              )}

              <div className="mt-6 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <button
                  onClick={() => setSelectedBuyerId(null)}
                  className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                >
                  {t.back}
                </button>
              </div>
            </div>
          </div>
        );
      })()}

      {/* ======================= ADJUST WALLET BALANCE MODAL ======================= */}
      {editingBalanceUser && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-sm rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl animate-scale-in">
            <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-3 mb-4">
              {language === 'en' ? 'Adjust User Account Balance' : 'تسوية الرصيد المالي للعميل'}
            </h3>
            <p className="text-xs text-slate-500 mb-4">
              {language === 'en'
                ? `Direct ledger adjustment for ${editingBalanceUser.name}. This syncs with Laravel escrow ledger.`
                : `إجراء تسوية يدوية مباشرة لحساب العميل ${editingBalanceUser.name}. يتطابق هذا الإجراء مباشرة مع قيود Laravel.`}
            </p>

            <div className="space-y-4">
              <div>
                <label className="text-[10px] font-bold uppercase text-slate-400">
                  {language === 'en' ? 'New Balance (SAR)' : 'الرصيد الجديد (ريال سعودي)'}
                </label>
                <input
                  type="number"
                  value={newBalance}
                  onChange={(e) => setNewBalance(e.target.value)}
                  className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-2 text-sm outline-hidden focus:border-indigo-500"
                  placeholder="0.00"
                />
              </div>
            </div>

            <div className="mt-6 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
              <button
                onClick={() => setEditingBalanceUser(null)}
                className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
              >
                {t.cancel}
              </button>
              <button
                onClick={handleUpdateBalance}
                className="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
              >
                {t.save}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
