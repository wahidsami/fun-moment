/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, UserRole } from '../types';
import { translations } from '../translations';
import { useState, useEffect, FormEvent } from 'react';
import { LaravelAPI } from '../api';
import { saveAuditLog, saveChangeRecord } from '../utils/auditLogger';
import { Settings2, Save, CheckCircle2, RefreshCw, Smartphone, ShieldCheck } from 'lucide-react';

interface SettingsViewProps {
  language: Language;
  activeRole: UserRole;
}

export default function SettingsView({ language, activeRole }: SettingsViewProps) {
  const t = translations[language];
  const [commission, setCommission] = useState('15.0');
  const [minPayout, setMinPayout] = useState('500');
  const [maintenance, setMaintenance] = useState(false);
  const [appVersion, setAppVersion] = useState('2.4.1');
  
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');

  // Track original values for diff-logging
  const [origCommission, setOrigCommission] = useState('15.0');
  const [origMinPayout, setOrigMinPayout] = useState('500');
  const [origMaintenance, setOrigMaintenance] = useState(false);
  const [origAppVersion, setOrigAppVersion] = useState('2.4.1');

  const loadSettings = async () => {
    setLoading(true);
    try {
      const config = await LaravelAPI.getSettings();
      const commStr = config.commission_percentage.toString();
      const minPayStr = config.min_payout_amount.toString();
      
      setCommission(commStr);
      setOrigCommission(commStr);
      
      setMinPayout(minPayStr);
      setOrigMinPayout(minPayStr);
      
      setMaintenance(config.maintenance_mode);
      setOrigMaintenance(config.maintenance_mode);
      
      setAppVersion(config.required_app_version);
      setOrigAppVersion(config.required_app_version);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSettings();
  }, []);

  const handleSave = async (e: FormEvent) => {
    e.preventDefault();

    // Guard: strictly Super Admin
    if (activeRole !== 'super_admin') {
      saveAuditLog({
        actorRole: activeRole,
        actorName: 'Current Active Admin',
        action: 'gateway_settings',
        resource: 'Platform Config Registry',
        detailsEn: `Blocked unauthorized settings save attempt.`,
        detailsAr: `تم حظر محاولة غير مصرح بها لتعديل الإعدادات العامة من صفة ${activeRole}.`,
        status: 'denied'
      });
      alert(language === 'en' ? "Access Denied: Only Super Admin can modify global platform parameters." : "تم رفض الوصول: يسمح فقط للمشرف العام بتعديل متغيرات المنصة البنيوية.");
      return;
    }

    setSaving(true);
    try {
      const updatedComm = parseFloat(commission) || 15.0;
      const updatedMinPay = parseFloat(minPayout) || 500;

      await LaravelAPI.saveSettings({
        commission_percentage: updatedComm,
        min_payout_amount: updatedMinPay,
        maintenance_mode: maintenance,
        required_app_version: appVersion,
        base_currency: 'SAR',
        exchange_rate_usd: 3.75
      });

      // Audit Success
      saveAuditLog({
        actorRole: activeRole,
        actorName: 'Current Active Admin',
        action: 'gateway_settings',
        resource: 'Platform Config Registry',
        detailsEn: `Saved global commission, payout, and maintenance parameters.`,
        detailsAr: `حفظ بيانات العمولات والتسويات البنكية ووضع الصيانة للنظام.`,
        status: 'success'
      });

      // Change History logging for mutated fields
      if (commission !== origCommission) {
        saveChangeRecord({
          resource: 'Platform Commission Rate',
          actorName: 'Current Active Admin',
          field: 'Commission Percentage',
          oldValue: `${origCommission}%`,
          newValue: `${commission}%`
        });
        setOrigCommission(commission);
      }
      if (minPayout !== origMinPayout) {
        saveChangeRecord({
          resource: 'Minimum Wallet Payout',
          actorName: 'Current Active Admin',
          field: 'Min Payout Amount',
          oldValue: `${origMinPayout} SAR`,
          newValue: `${minPayout} SAR`
        });
        setOrigMinPayout(minPayout);
      }
      if (maintenance !== origMaintenance) {
        saveChangeRecord({
          resource: 'Maintenance Mode Status',
          actorName: 'Current Active Admin',
          field: 'System Maintenance Active',
          oldValue: origMaintenance ? 'TRUE' : 'FALSE',
          newValue: maintenance ? 'TRUE' : 'FALSE'
        });
        setOrigMaintenance(maintenance);
      }
      if (appVersion !== origAppVersion) {
        saveChangeRecord({
          resource: 'Mobile Client Forced Version',
          actorName: 'Current Active Admin',
          field: 'Required App Version',
          oldValue: origAppVersion,
          newValue: appVersion
        });
        setOrigAppVersion(appVersion);
      }

      setSuccessMsg(t.saved_success);
      setTimeout(() => setSuccessMsg(''), 4000);
    } catch (err) {
      console.error(err);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* View Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-slate-800">
            {t.settings_title}
          </h2>
          <p className="text-xs text-slate-500">
            {t.settings_subtitle}
          </p>
        </div>
      </div>

      {successMsg && (
        <div className="rounded-xl bg-emerald-50 border border-emerald-100 p-4 text-xs text-emerald-800 font-semibold animate-fade-in flex items-start gap-2">
          <CheckCircle2 className="h-4.5 w-4.5 text-emerald-600 shrink-0 mt-0.5" />
          <span>{successMsg}</span>
        </div>
      )}

      {loading ? (
        <div className="py-10 text-center text-slate-400">
          <RefreshCw className="mx-auto h-5 w-5 animate-spin mb-2" />
          <span>{t.loading}</span>
        </div>
      ) : (
        <form onSubmit={handleSave} className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          {/* Main settings options */}
          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5 lg:col-span-2">
            <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-3 flex items-center gap-1.5">
              <Settings2 className="h-4.5 w-4.5 text-indigo-600" />
              <span>{language === 'en' ? 'Core Financial & Commission Config' : 'تكوينات العمولات والنسب الأساسية'}</span>
            </h3>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-slate-600">{t.set_commission}</label>
                <input
                  type="text"
                  value={commission}
                  onChange={(e) => setCommission(e.target.value)}
                  className="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-xs outline-hidden focus:border-indigo-500"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-slate-600">{t.set_min_payout}</label>
                <div className="relative">
                  <input
                    type="text"
                    value={minPayout}
                    onChange={(e) => setMinPayout(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-xs outline-hidden focus:border-indigo-500 font-mono"
                  />
                  <span className="absolute top-2.5 right-3.5 text-[10px] font-bold text-slate-400">SAR</span>
                </div>
              </div>
            </div>

            {/* Maintenance Mode */}
            <div className="border-t border-slate-100 pt-5 space-y-3">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-xs font-bold text-slate-800">{t.set_maintenance}</h4>
                  <p className="text-[10px] text-slate-400 mt-0.5">
                    {language === 'en'
                      ? 'Display a "Service Temporarily Unavailable" screen across both apps.'
                      : 'عرض شاشة "المنصة في وضع صيانة" للموقع والتطبيق بشكل فوري.'}
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => setMaintenance(!maintenance)}
                  className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${
                    maintenance ? 'bg-indigo-600' : 'bg-slate-200'
                  }`}
                >
                  <span
                    className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out ${
                      maintenance ? 'translate-x-5' : 'translate-x-0'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* Form Actions */}
            <div className="border-t border-slate-100 pt-5 flex items-center justify-end">
              <button
                type="submit"
                disabled={saving}
                className="flex items-center gap-1.5 rounded-lg bg-indigo-600 px-5 py-2 text-xs font-bold text-white shadow-md shadow-indigo-100 transition hover:bg-indigo-700 disabled:opacity-50 cursor-pointer"
              >
                {saving ? (
                  <RefreshCw className="h-4 w-4 animate-spin" />
                ) : (
                  <Save className="h-4 w-4" />
                )}
                <span>{language === 'en' ? 'Save Platform Settings' : 'حفظ إعدادات النظام'}</span>
              </button>
            </div>
          </div>

          {/* Device & Client Control Side Box */}
          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-3 flex items-center gap-1.5">
              <Smartphone className="h-4.5 w-4.5 text-indigo-600" />
              <span>{language === 'en' ? 'Mobile App Control' : 'إصدارات تطبيق الجوال'}</span>
            </h3>

            <div className="space-y-4">
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-slate-600">{t.set_app_version}</label>
                <input
                  type="text"
                  value={appVersion}
                  onChange={(e) => setAppVersion(e.target.value)}
                  className="w-full rounded-lg border border-slate-200 px-3.5 py-1.5 text-xs outline-hidden focus:border-indigo-500 font-mono"
                />
              </div>

              <div className="rounded-xl bg-indigo-50/50 border border-indigo-100/50 p-3 text-[10px] leading-relaxed text-indigo-800 space-y-2">
                <p className="font-bold flex items-center gap-1">
                  <ShieldCheck className="h-3.5 w-3.5 text-indigo-600" />
                  <span>{language === 'en' ? 'Forced Update Active' : 'تفعيل التحديث الإجباري'}</span>
                </p>
                <p>
                  {language === 'en'
                    ? 'Users with application versions lower than specified above will be prompted to upgrade immediately via the Apple App Store or Google Play.'
                    : 'سيُطلب من كافة مستخدمي الأجهزة الذكية الذين يمتلكون إصدارات أقدم من الإصدار المذكور التحديث فوراً لمتابعة استخدام التطبيق.'}
                </p>
              </div>
            </div>
          </div>
        </form>
      )}
    </div>
  );
}
