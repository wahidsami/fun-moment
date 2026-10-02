/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { AdminAccount, AdminRoleRecord, Language, Permission, User, UserRole, SellerVerificationDetail } from '../types';
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
  Eye,
  FileCheck,
  XCircle,
  Download,
  ExternalLink,
  FileText,
  MapPin,
  Tag,
  Layers,
  User as UserIcon,
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
  const [verificationDetail, setVerificationDetail] = useState<SellerVerificationDetail | null>(null);
  const [loadingVerification, setLoadingVerification] = useState(false);
  const [selectedBuyerId, setSelectedBuyerId] = useState<number | null>(null);
  const [showRejectForm, setShowRejectForm] = useState(false);
  const [rejectReasonInput, setRejectReasonInput] = useState('');

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

  const handleOpenSellerAudit = async (sellerId: number) => {
    setSelectedSellerId(sellerId);
    setLoadingVerification(true);
    setShowRejectForm(false);
    setRejectReasonInput('');
    try {
      const res = await LaravelAPI.getSellerVerification(sellerId);
      const detailData = (res as any)?.verification ?? res;
      setVerificationDetail(detailData);
    } catch (e) {
      console.error(e);
      setVerificationDetail(null);
    } finally {
      setLoadingVerification(false);
    }
  };

  const handleVerifySeller = async (userId: number, targetStatus: number, reason?: string) => {
    if (!hasPermission) {
      alert(language === 'en' ? "Access Denied." : "تم رفض الوصول.");
      return;
    }

    try {
      const res = await LaravelAPI.verifySeller(userId, targetStatus, reason);
      const isNowVerified = res.seller_verified ?? (targetStatus === 1);

      setUsers(current => current.map(u => {
        if (u.id === userId) {
          return {
            ...u,
            seller_verified: isNowVerified,
            seller_verification: {
              ...(u.seller_verification || { address: '', national_id: '' }),
              status: targetStatus,
              is_verified: isNowVerified
            }
          };
        }
        return u;
      }));

      if (verificationDetail && (verificationDetail.seller_id === userId || verificationDetail.user_id === userId)) {
        setVerificationDetail({
          ...verificationDetail,
          status: targetStatus,
          is_verified: isNowVerified,
          rejection_reason: reason ?? verificationDetail.rejection_reason,
          verified_at: targetStatus === 1 ? new Date().toISOString() : undefined,
        });
      }

      setShowRejectForm(false);
      setRejectReasonInput('');

      showSuccess(
        targetStatus === 1
          ? (language === 'en' ? "Seller verification approved and recorded in PostgreSQL!" : "تمت الموافقة على توثيق المزود وتثبيت الحالة في قاعدة البيانات!")
          : (language === 'en' ? "Seller verification rejected and recorded in PostgreSQL!" : "تم رفض طلب توثيق المزود وتثبيت الحالة في قاعدة البيانات!")
      );
    } catch (err: any) {
      console.error(err);
      alert(err?.message || "Failed to update verification status");
    }
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
                    const isVerified = Boolean(user.seller_verified || user.seller_verification?.is_verified);
                    const verifyStatus = user.seller_verification?.status ?? (isVerified ? 1 : 0);
                    return (
                      <tr key={user.id} className="hover:bg-slate-50/50 transition-colors">
                        <td className="px-6 py-4 text-slate-400 font-bold">#{user.id}</td>
                        <td className="px-6 py-4">
                          <div className="font-bold text-slate-800">
                            {user.business_registration || profile?.storeName || user.name}
                          </div>
                          <div className="text-[10px] text-slate-400 mt-0.5">{user.phone || '—'}</div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="font-bold text-slate-700">{user.name}</div>
                          <div className="text-[10px] text-slate-400 font-mono">{user.email}</div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex items-center gap-1">
                            <span className={`inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[9px] font-bold ${
                              verifyStatus === 1
                                ? 'bg-emerald-50 text-emerald-700'
                                : verifyStatus === 2
                                ? 'bg-rose-50 text-rose-700'
                                : 'bg-amber-50 text-amber-700'
                            }`}>
                              {verifyStatus === 1
                                ? (language === 'en' ? 'CR Verified' : 'سجل موثق')
                                : verifyStatus === 2
                                ? (language === 'en' ? 'CR Rejected' : 'توثيق مرفوض')
                                : (language === 'en' ? 'CR Pending' : 'قيد المراجعة')}
                            </span>
                          </div>
                        </td>
                        <td className="px-6 py-4 text-slate-600 font-bold">
                          {profile?.commissionRate || 10}%
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex items-center gap-1">
                            <span className="font-bold text-slate-900">{user.wallet_balance?.toLocaleString() ?? 0} SAR</span>
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
                              onClick={() => handleOpenSellerAudit(user.id)}
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

      {/* ======================= AUDIT SELLER DRAWER / PROVIDER DOSSIER MODAL ======================= */}
      {selectedSellerId && (() => {
        const user = users.find(u => u.id === selectedSellerId);
        const detail = verificationDetail;
        const currentStatus = detail?.status ?? (user?.seller_verified ? 1 : 0);
        const isVerified = currentStatus === 1;
        const isRejected = currentStatus === 2;
        const isCompany = detail ? detail.seller_type === 2 : user?.seller_type === 2;

        return (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <div className="w-full max-w-3xl max-h-[92vh] flex flex-col rounded-2xl border border-slate-100 bg-white shadow-2xl animate-scale-in overflow-hidden">
              
              {/* Modal Header */}
              <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/50">
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-xl bg-indigo-50 text-indigo-600">
                    {isCompany ? <Building className="h-5 w-5" /> : <UserIcon className="h-5 w-5" />}
                  </div>
                  <div>
                    <h3 className="font-bold text-slate-800 text-sm">
                      {language === 'en' ? 'Provider Dossier & Verification' : 'ملف المزود الشامل والتوثيق'} #{selectedSellerId}
                    </h3>
                    <div className="flex items-center gap-2 mt-0.5 text-[11px] text-slate-400">
                      <span className="font-semibold text-slate-600">
                        {detail?.seller_name || user?.name}
                      </span>
                      <span>•</span>
                      <span className="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-600">
                        {isCompany ? (language === 'en' ? 'Company Provider' : 'مزود شركة') : (language === 'en' ? 'Individual Provider' : 'مزود فردي')}
                      </span>
                    </div>
                  </div>
                </div>
                <button
                  onClick={() => { setSelectedSellerId(null); setVerificationDetail(null); setShowRejectForm(false); setRejectReasonInput(''); }}
                  className="rounded-lg p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition text-sm font-bold"
                >
                  ✕
                </button>
              </div>

              {/* Modal Content - Scrollable */}
              <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5">
                {loadingVerification ? (
                  <div className="py-16 text-center text-slate-400">
                    <RefreshCcw className="mx-auto h-7 w-7 animate-spin mb-3 text-indigo-500" />
                    <p className="text-xs font-semibold">{language === 'en' ? 'Loading provider records and verified attachments...' : 'جاري تحميل ملف المزود والمستندات المرفقة...'}</p>
                  </div>
                ) : user ? (
                  <>
                    {/* Status & Financial Summary Banner */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                      <div className="rounded-xl border border-slate-100 bg-slate-50/60 p-3.5 flex flex-col justify-between">
                        <span className="text-[10px] font-bold uppercase text-slate-400">
                          {language === 'en' ? 'Verification Status' : 'حالة التوثيق'}
                        </span>
                        <div className="mt-1.5">
                          <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ${
                            isVerified 
                              ? 'bg-emerald-100 text-emerald-800' 
                              : isRejected 
                              ? 'bg-rose-100 text-rose-800' 
                              : 'bg-amber-100 text-amber-800'
                          }`}>
                            <span className={`h-1.5 w-1.5 rounded-full ${isVerified ? 'bg-emerald-600' : isRejected ? 'bg-rose-600' : 'bg-amber-600'}`} />
                            {isVerified ? (language === 'en' ? 'APPROVED & VERIFIED' : 'موثق ومعتمد') : isRejected ? (language === 'en' ? 'REJECTED' : 'مرفوض') : (language === 'en' ? 'PENDING DECISION' : 'قيد المراجعة')}
                          </span>
                        </div>
                      </div>

                      <div className="rounded-xl border border-slate-100 bg-slate-50/60 p-3.5 flex flex-col justify-between">
                        <span className="text-[10px] font-bold uppercase text-slate-400">
                          {language === 'en' ? 'Account Balance' : 'رصيد المحفظة'}
                        </span>
                        <div className="mt-1.5 flex items-baseline gap-1">
                          <span className="text-base font-extrabold text-slate-900">{user.wallet_balance ?? 0}</span>
                          <span className="text-xs font-semibold text-slate-500">SAR</span>
                        </div>
                      </div>

                      <div className="rounded-xl border border-slate-100 bg-slate-50/60 p-3.5 flex flex-col justify-between">
                        <span className="text-[10px] font-bold uppercase text-slate-400">
                          {language === 'en' ? 'Platform Subscription' : 'خطة الاشتراك'}
                        </span>
                        <div className="mt-1.5">
                          {detail?.subscription ? (
                            <div>
                              <span className="font-bold text-xs text-indigo-700">{detail.subscription.plan_name}</span>
                              <span className="text-[10px] text-slate-400 ml-1">({detail.subscription.status_label})</span>
                            </div>
                          ) : (
                            <span className="text-xs text-slate-400 italic">{language === 'en' ? 'No active subscription' : 'لا يوجد اشتراك نشط'}</span>
                          )}
                        </div>
                      </div>
                    </div>

                    {/* Section 1: Business / Legal Identity */}
                    <div className="rounded-xl border border-slate-200/80 bg-white p-4 space-y-3">
                      <div className="flex items-center gap-2 border-b border-slate-100 pb-2 text-xs font-bold text-slate-800">
                        <Briefcase className="h-4 w-4 text-indigo-600" />
                        <span>{language === 'en' ? 'Legal & Registration Identity' : 'بيانات الهوية والترخيص النظامية'}</span>
                      </div>

                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                        {isCompany ? (
                          <>
                            <div className="rounded-lg bg-slate-50 p-2.5">
                              <span className="text-[10px] font-bold uppercase text-slate-400">{language === 'en' ? 'Company Name' : 'اسم الشركة المسجل'}</span>
                              <p className="font-semibold text-slate-800 mt-0.5">{detail?.company_name || user.name}</p>
                            </div>
                            <div className="rounded-lg bg-slate-50 p-2.5">
                              <span className="text-[10px] font-bold uppercase text-slate-400">{language === 'en' ? 'Commercial Registration (CR)' : 'رقم السجل التجاري'}</span>
                              <p className="font-mono font-semibold text-slate-800 mt-0.5">{detail?.cr_number || user.business_registration || '—'}</p>
                            </div>
                            <div className="rounded-lg bg-slate-50 p-2.5">
                              <span className="text-[10px] font-bold uppercase text-slate-400">{language === 'en' ? 'Contact Person' : 'الشخص المفوض'}</span>
                              <p className="font-semibold text-slate-800 mt-0.5">{detail?.contact_person_name || '—'}</p>
                              {detail?.contact_person_email && <p className="text-[11px] text-slate-500">{detail.contact_person_email} • {detail.contact_person_phone}</p>}
                            </div>
                            <div className="rounded-lg bg-slate-50 p-2.5">
                              <span className="text-[10px] font-bold uppercase text-slate-400">{language === 'en' ? 'VAT / Tax Number' : 'الرقم الضريبي'}</span>
                              <p className="font-mono font-semibold text-slate-800 mt-0.5">{detail?.tax_number || user.tax_number || '—'}</p>
                            </div>
                          </>
                        ) : (
                          <>
                            <div className="rounded-lg bg-slate-50 p-2.5">
                              <span className="text-[10px] font-bold uppercase text-slate-400">{language === 'en' ? 'National ID / Iqama' : 'الهوية الوطنية / الإقامة'}</span>
                              <p className="font-mono font-semibold text-slate-800 mt-0.5">{detail?.national_id || user.seller_verification?.national_id || '—'}</p>
                            </div>
                            <div className="rounded-lg bg-slate-50 p-2.5">
                              <span className="text-[10px] font-bold uppercase text-slate-400">{language === 'en' ? 'Freelance / Professional License' : 'وثيقة العمل الحر / الترخيص'}</span>
                              <p className="font-mono font-semibold text-slate-800 mt-0.5">{detail?.license_number || '—'}</p>
                            </div>
                            {detail?.is_band_or_group && (
                              <div className="rounded-lg bg-slate-50 p-2.5 md:col-span-2">
                                <span className="text-[10px] font-bold uppercase text-slate-400">{language === 'en' ? 'Musical Band / Entertainment Group' : 'الفرقة الموسيقية / الاستعراضية'}</span>
                                <p className="font-semibold text-slate-800 mt-0.5">{detail.band_name} ({detail.band_members_count} {language === 'en' ? 'members' : 'أعضاء'})</p>
                              </div>
                            )}
                          </>
                        )}
                      </div>
                    </div>

                    {/* Section 2: Contact & Operational Location */}
                    <div className="rounded-xl border border-slate-200/80 bg-white p-4 space-y-3">
                      <div className="flex items-center gap-2 border-b border-slate-100 pb-2 text-xs font-bold text-slate-800">
                        <MapPin className="h-4 w-4 text-indigo-600" />
                        <span>{language === 'en' ? 'Contact & Operational Coverage' : 'بيانات التواصل والتغطية الجغرافية'}</span>
                      </div>

                      <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                        <div className="rounded-lg bg-slate-50 p-2.5">
                          <span className="text-[10px] font-bold uppercase text-slate-400">{language === 'en' ? 'Email & Phone' : 'البريد والهاتف'}</span>
                          <p className="font-semibold text-slate-800 mt-0.5">{user.email}</p>
                          <p className="text-[11px] text-slate-500 font-mono">{user.phone || '—'}</p>
                        </div>
                        <div className="rounded-lg bg-slate-50 p-2.5">
                          <span className="text-[10px] font-bold uppercase text-slate-400">{language === 'en' ? 'City / Area' : 'المدينة والحي'}</span>
                          <p className="font-semibold text-slate-800 mt-0.5">{detail?.city || '—'}, {detail?.area || '—'}</p>
                          <p className="text-[11px] text-slate-500">{detail?.country || user.country || 'Saudi Arabia'}</p>
                        </div>
                        <div className="rounded-lg bg-slate-50 p-2.5">
                          <span className="text-[10px] font-bold uppercase text-slate-400">{language === 'en' ? 'Street Address' : 'العنوان المسجل'}</span>
                          <p className="font-semibold text-slate-800 mt-0.5">{detail?.address || user.address || '—'}</p>
                        </div>
                      </div>
                    </div>

                    {/* Section 3: Service Categories */}
                    <div className="rounded-xl border border-slate-200/80 bg-white p-4 space-y-2.5">
                      <div className="flex items-center gap-2 border-b border-slate-100 pb-2 text-xs font-bold text-slate-800">
                        <Tag className="h-4 w-4 text-indigo-600" />
                        <span>{language === 'en' ? 'Registered Service Categories' : 'تصنيفات الخدمات المعتمدة'}</span>
                      </div>

                      {detail?.categories && detail.categories.length > 0 ? (
                        <div className="flex flex-wrap gap-2 pt-1">
                          {detail.categories.map((cat) => (
                            <span
                              key={cat.id}
                              className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700"
                            >
                              <span className="h-1.5 w-1.5 rounded-full bg-indigo-500" />
                              <span>{language === 'en' ? cat.name_en : cat.name_ar}</span>
                            </span>
                          ))}
                        </div>
                      ) : (
                        <p className="text-xs text-slate-400 italic pt-1">{language === 'en' ? 'No registered categories associated with this provider.' : 'لا توجد تصنيفات مرتبطة بهذا المزود حالياً.'}</p>
                      )}
                    </div>

                    {/* Section 4: Submitted Documents & Attachments */}
                    <div className="rounded-xl border border-slate-200/80 bg-white p-4 space-y-3">
                      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                        <div className="flex items-center gap-2 text-xs font-bold text-slate-800">
                          <FileText className="h-4 w-4 text-indigo-600" />
                          <span>{language === 'en' ? 'Submitted Verification Documents' : 'المستندات والوثائق المرفوعة'}</span>
                        </div>
                        <span className="text-[11px] font-bold text-slate-400">
                          {detail?.documents?.length || 0} {language === 'en' ? 'files' : 'ملفات'}
                        </span>
                      </div>

                      {detail?.documents && detail.documents.length > 0 ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                          {detail.documents.map((doc, idx) => (
                            <div
                              key={idx}
                              className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/50 p-3 hover:bg-slate-50 transition"
                            >
                              <div className="flex items-center gap-2.5 overflow-hidden">
                                <div className="p-2 rounded-lg bg-indigo-50 text-indigo-600 shrink-0">
                                  <FileText className="h-4 w-4" />
                                </div>
                                <div className="overflow-hidden">
                                  <p className="text-xs font-bold text-slate-800 truncate">
                                    {language === 'en' ? doc.label_en : doc.label_ar}
                                  </p>
                                  <p className="text-[10px] text-slate-400 font-mono truncate mt-0.5">
                                    {doc.filename} {doc.size_bytes ? `• ${(doc.size_bytes / 1024).toFixed(0)} KB` : ''}
                                  </p>
                                </div>
                              </div>

                              <div className="shrink-0 ml-2">
                                {doc.exists ? (
                                  <a
                                    href={doc.download_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-indigo-600 hover:bg-indigo-50 hover:border-indigo-200 transition shadow-2xs"
                                  >
                                    <Download className="h-3 w-3" />
                                    <span>{language === 'en' ? 'Open' : 'عرض'}</span>
                                  </a>
                                ) : (
                                  <span className="text-[10px] font-semibold text-rose-500 bg-rose-50 px-2 py-1 rounded">
                                    {language === 'en' ? 'File Missing' : 'الملف مفقود'}
                                  </span>
                                )}
                              </div>
                            </div>
                          ))}
                        </div>
                      ) : (
                        <div className="rounded-lg bg-slate-50 p-4 text-center">
                          <p className="text-xs text-slate-400 italic">
                            {language === 'en' ? 'No verification documents were uploaded by this provider during registration.' : 'لم يقم هذا المزود برفع أي مستندات توثيق أثناء التسجيل.'}
                          </p>
                        </div>
                      )}
                    </div>

                    {/* Rejection / Decision Feedback if already rejected */}
                    {isRejected && detail?.rejection_reason && (
                      <div className="rounded-xl border border-rose-200 bg-rose-50/60 p-3 text-xs text-rose-800">
                        <span className="font-bold">{language === 'en' ? 'Rejection Reason Recorded:' : 'سبب الرفض المسجل:'}</span>
                        <p className="mt-0.5">{detail.rejection_reason}</p>
                      </div>
                    )}

                    {/* Section 5: Administrative Decision Actions */}
                    {hasPermission && (
                      <div className="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 text-xs space-y-3">
                        <div className="flex items-center justify-between">
                          <p className="font-bold text-indigo-950">
                            {language === 'en' ? 'Administrative Decision (Persists directly to PostgreSQL):' : 'القرار الإداري للتوثيق (يُحفظ مباشرة في قاعدة البيانات):'}
                          </p>
                          {detail?.verified_at && (
                            <span className="text-[10px] text-indigo-600 font-mono">
                              {language === 'en' ? 'Last Verified:' : 'تاريخ الاعتماد:'} {detail.verified_at}
                            </span>
                          )}
                        </div>

                        {showRejectForm ? (
                          <div className="rounded-lg border border-rose-200 bg-white p-3 space-y-2.5">
                            <label className="text-[11px] font-bold text-slate-700">
                              {language === 'en' ? 'Specify Reason for Rejection:' : 'حدد سبب رفض التوثيق:'}
                            </label>
                            <input
                              type="text"
                              value={rejectReasonInput}
                              onChange={(e) => setRejectReasonInput(e.target.value)}
                              placeholder={language === 'en' ? 'e.g. Expired Commercial Registration or Invalid ID...' : 'مثال: السجل التجاري منتهي الصلاحية أو الهوية غير مطابقة...'}
                              className="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs outline-hidden focus:border-rose-500"
                            />
                            <div className="flex items-center justify-end gap-2 pt-1">
                              <button
                                type="button"
                                onClick={() => { setShowRejectForm(false); setRejectReasonInput(''); }}
                                className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                              >
                                {t.cancel}
                              </button>
                              <button
                                type="button"
                                onClick={() => handleVerifySeller(user.id, 2, rejectReasonInput)}
                                className="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-rose-700 transition"
                              >
                                {language === 'en' ? 'Confirm Rejection' : 'تأكيد الرفض'}
                              </button>
                            </div>
                          </div>
                        ) : (
                          <div className="flex items-center gap-2.5">
                            <button
                              type="button"
                              onClick={() => handleVerifySeller(user.id, 1)}
                              disabled={isVerified}
                              className="flex-1 rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50 transition flex items-center justify-center gap-1.5 shadow-xs"
                            >
                              <FileCheck className="h-4 w-4" />
                              <span>{language === 'en' ? 'Approve Verification' : 'اعتماد وتوثيق الحساب'}</span>
                            </button>

                            <button
                              type="button"
                              onClick={() => setShowRejectForm(true)}
                              disabled={isRejected}
                              className="flex-1 rounded-lg bg-rose-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-rose-700 disabled:opacity-50 transition flex items-center justify-center gap-1.5 shadow-xs"
                            >
                              <XCircle className="h-4 w-4" />
                              <span>{language === 'en' ? 'Reject Submission' : 'رفض طلب التوثيق'}</span>
                            </button>
                          </div>
                        )}
                      </div>
                    )}
                  </>
                ) : null}
              </div>

              {/* Modal Footer */}
              <div className="flex items-center justify-end border-t border-slate-100 px-6 py-3 bg-slate-50/50">
                <button
                  onClick={() => { setSelectedSellerId(null); setVerificationDetail(null); setShowRejectForm(false); setRejectReasonInput(''); }}
                  className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
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
