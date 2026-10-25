/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, UserRole, MusicMarketplaceSettings, MusicMarketplaceRuntime } from '../types';
import { translations } from '../translations';
import { useState, useEffect, FormEvent } from 'react';
import { LaravelAPI } from '../api';
import { saveAuditLog, saveChangeRecord } from '../utils/auditLogger';
import {
  Music,
  Save,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  Sliders,
  ShieldCheck,
  Radio,
  FileAudio,
  DollarSign,
  Clock,
  HardDrive,
  Sparkles,
  Info
} from 'lucide-react';

interface MusicSettingsViewProps {
  language: Language;
  activeRole: UserRole;
}

export default function MusicSettingsView({ language, activeRole }: MusicSettingsViewProps) {
  const t = translations[language];
  const isRtl = language === 'ar';

  // Marketplace Availability
  const [marketplaceEnabled, setMarketplaceEnabled] = useState(false);

  // Audio Pipeline Bounds
  const [previewDuration, setPreviewDuration] = useState(20);
  const [maxUploadMb, setMaxUploadMb] = useState(25);
  const [allowedFormats, setAllowedFormats] = useState<string[]>(['mp3', 'wav', 'aac', 'flac', 'm4a']);

  // Commercial Licensing & Commission
  const [commissionRate, setCommissionRate] = useState(15.0);
  const [offerFree, setOfferFree] = useState(true);
  const [offerRent, setOfferRent] = useState(true);
  const [offerSale, setOfferSale] = useState(true);
  const [rentalDays, setRentalDays] = useState(7);

  // Governance & Moderation
  const [sellerSubmissions, setSellerSubmissions] = useState(true);
  const [sellerApproval, setSellerApproval] = useState(true);

  // UI & Runtime State
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');
  const [errorMsg, setErrorMsg] = useState('');
  const [dispatchedJobsNotice, setDispatchedJobsNotice] = useState<number | null>(null);
  const [runtime, setRuntime] = useState<MusicMarketplaceRuntime | null>(null);
  const [canManage, setCanManage] = useState(true);

  // Original values for diff logging
  const [origPreviewDuration, setOrigPreviewDuration] = useState(20);
  const [origMarketplaceEnabled, setOrigMarketplaceEnabled] = useState(false);

  const availableFormatChips = [
    { key: 'mp3', label: 'MP3 (MPEG Audio)', desc: 'Standard compressed streaming audio' },
    { key: 'wav', label: 'WAV (RIFF WAVE)', desc: 'Uncompressed lossless PCM master audio' },
    { key: 'flac', label: 'FLAC (Free Lossless)', desc: 'High-fidelity compressed lossless audio' },
    { key: 'aac', label: 'AAC (Raw ADTS Stream)', desc: 'Raw Advanced Audio Coding stream' },
    { key: 'm4a', label: 'M4A (MP4 Audio Container)', desc: 'ISO MPEG-4 Part 14 audio container' },
  ];

  const loadSettings = async () => {
    setLoading(true);
    setErrorMsg('');
    try {
      const res = await LaravelAPI.getMusicSettings();
      const data = res.data;
      setMarketplaceEnabled(data.music_marketplace_enabled);
      setOrigMarketplaceEnabled(data.music_marketplace_enabled);

      setPreviewDuration(data.paid_preview_duration_seconds);
      setOrigPreviewDuration(data.paid_preview_duration_seconds);

      setMaxUploadMb(data.max_audio_upload_mb);
      setCommissionRate(data.platform_commission_rate);
      setAllowedFormats(data.allowed_audio_formats || ['mp3', 'wav', 'aac', 'flac', 'm4a']);

      setSellerSubmissions(data.seller_submissions_enabled);
      setSellerApproval(data.seller_tracks_require_approval);
      setOfferFree(data.offer_free_enabled);
      setOfferRent(data.offer_rent_enabled);
      setOfferSale(data.offer_sale_enabled);
      setRentalDays(data.default_rental_duration_days);

      if (res.runtime) {
        setRuntime(res.runtime);
      }
      if (res.meta?.can_manage !== undefined) {
        setCanManage(res.meta.can_manage);
      }
    } catch (e: any) {
      console.error(e);
      setErrorMsg(e?.message || 'Failed to load music marketplace configuration.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSettings();
  }, []);

  const toggleFormat = (formatKey: string) => {
    setAllowedFormats(prev => {
      if (prev.includes(formatKey)) {
        // Prevent deselecting all formats
        if (prev.length <= 1) {
          alert(isRtl ? 'يجب إبقاء صيغة واحدة على الأقل مفعلة.' : 'At least one audio format must remain enabled.');
          return prev;
        }
        return prev.filter(f => f !== formatKey);
      } else {
        return [...prev, formatKey];
      }
    });
  };

  const handleSave = async (e: FormEvent) => {
    e.preventDefault();

    // Guard: strictly Super Admin
    if (activeRole !== 'super_admin') {
      saveAuditLog({
        actorRole: activeRole,
        actorName: 'Current Active Admin',
        action: 'music_settings_save',
        resource: 'Music Marketplace Config',
        detailsEn: 'Blocked unauthorized music settings modification attempt.',
        detailsAr: `تم حظر محاولة غير مصرح بها لتعديل إعدادات سوق الموسيقى من صفة ${activeRole}.`,
        status: 'denied',
      });
      alert(
        isRtl
          ? 'تم رفض الوصول: يسمح فقط للمشرف العام بتعديل إعدادات سوق الموسيقى.'
          : 'Access Denied: Only Super Admin can modify Music Marketplace configuration.'
      );
      return;
    }

    setSaving(true);
    setErrorMsg('');
    setSuccessMsg('');
    setDispatchedJobsNotice(null);

    try {
      const payload: Partial<MusicMarketplaceSettings> = {
        music_marketplace_enabled: marketplaceEnabled,
        paid_preview_duration_seconds: previewDuration,
        max_audio_upload_mb: maxUploadMb,
        platform_commission_rate: commissionRate,
        allowed_audio_formats: allowedFormats,
        seller_submissions_enabled: sellerSubmissions,
        seller_tracks_require_approval: sellerApproval,
        offer_free_enabled: offerFree,
        offer_rent_enabled: offerRent,
        offer_sale_enabled: offerSale,
        default_rental_duration_days: rentalDays,
      };

      const res = await LaravelAPI.saveMusicSettings(payload);

      setSuccessMsg(isRtl ? res.message_ar : res.message_en);

      if (res.total_dispatched_jobs && res.total_dispatched_jobs > 0) {
        setDispatchedJobsNotice(res.total_dispatched_jobs);
      }

      if (res.runtime) {
        setRuntime(res.runtime);
      }

      // Record audit diff
      if (origPreviewDuration !== previewDuration) {
        saveChangeRecord({
          resource: 'Music Marketplace Config',
          actorName: 'Current Active Admin',
          field: 'Paid Track Preview Duration',
          oldValue: `${origPreviewDuration}s`,
          newValue: `${previewDuration}s`,
        });
        setOrigPreviewDuration(previewDuration);
      }

      if (origMarketplaceEnabled !== marketplaceEnabled) {
        saveChangeRecord({
          resource: 'Music Marketplace Config',
          actorName: 'Current Active Admin',
          field: 'Global Marketplace Master Switch',
          oldValue: origMarketplaceEnabled ? 'ENABLED' : 'DISABLED',
          newValue: marketplaceEnabled ? 'ENABLED' : 'DISABLED',
        });
        setOrigMarketplaceEnabled(marketplaceEnabled);
      }

      saveAuditLog({
        actorRole: activeRole,
        actorName: 'Current Active Admin',
        action: 'music_settings_save',
        resource: 'Music Marketplace Settings',
        detailsEn: `Updated music marketplace settings (enabled: ${marketplaceEnabled}, preview: ${previewDuration}s, commission: ${commissionRate}%).`,
        detailsAr: `تم تحديث إعدادات سوق الموسيقى بنجاح (المتجر: ${marketplaceEnabled ? 'مفعل' : 'معطل'}، المعاينة: ${previewDuration}ث، العمولة: ${commissionRate}%).`,
        status: 'success',
      });
    } catch (err: any) {
      console.error(err);
      setErrorMsg(err?.message || (isRtl ? 'فشل حفظ الإعدادات' : 'Failed to save settings'));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center p-16 space-y-4 text-slate-400">
        <RefreshCw className="w-8 h-8 animate-spin text-indigo-500" />
        <p className="text-sm font-medium">{t.loading}</p>
      </div>
    );
  }

  return (
    <div className="space-y-6 pb-12 max-w-6xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200/80 pb-5">
        <div className="space-y-1">
          <div className="flex items-center gap-2.5">
            <div className="p-2 bg-gradient-to-tr from-indigo-500 to-purple-600 text-white rounded-xl shadow-xs">
              <Music className="w-5 h-5" />
            </div>
            <h1 className="text-2xl font-black text-slate-900 tracking-tight">
              {t.music_settings_title || 'Music Marketplace Settings'}
            </h1>
          </div>
          <p className="text-xs text-slate-500 max-w-2xl">
            {t.music_settings_subtitle ||
              'Configure streaming availability, audio processing bounds, commercial licensing rules, and seller moderation.'}
          </p>
        </div>

        <button
          type="button"
          onClick={loadSettings}
          disabled={loading || saving}
          className="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition shadow-2xs self-start sm:self-auto"
        >
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
          <span>{t.refresh}</span>
        </button>
      </div>

      {/* Feedback Messages */}
      {successMsg && (
        <div className="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-start gap-3 shadow-2xs animate-fadeIn">
          <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
          <div className="text-xs font-medium space-y-1">
            <p>{successMsg}</p>
            {dispatchedJobsNotice !== null && (
              <p className="text-emerald-700 font-semibold">
                {isRtl
                  ? `تمت جدولة ${dispatchedJobsNotice} مهمة لإعادة توليد المقاطع الترويجية للمسارات في الخلفية.`
                  : `Dispatched ${dispatchedJobsNotice} background preview regeneration jobs for outdated tracks.`}
              </p>
            )}
          </div>
        </div>
      )}

      {errorMsg && (
        <div className="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start gap-3 shadow-2xs animate-fadeIn">
          <AlertTriangle className="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
          <div className="text-xs font-medium">{errorMsg}</div>
        </div>
      )}

      <form onSubmit={handleSave} className="space-y-6">
        {/* Section 1: Marketplace Availability & Master Switch */}
        <div className="rounded-2xl border border-slate-200/90 bg-white shadow-2xs overflow-hidden">
          <div className="p-5 border-b border-slate-100 flex items-center justify-between">
            <div className="space-y-0.5">
              <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
                <Radio className="w-4 h-4 text-indigo-500" />
                {t.music_marketplace_enabled || 'Music Marketplace Global Switch'}
              </h2>
              <p className="text-xs text-slate-500">
                {t.music_marketplace_enabled_desc ||
                  'Master switch to enable or disable the music section across buyer applications at runtime without rebuild.'}
              </p>
            </div>
            <span
              className={`px-2.5 py-1 text-[11px] font-bold rounded-full ${
                marketplaceEnabled
                  ? 'bg-emerald-100 text-emerald-700 border border-emerald-200'
                  : 'bg-slate-100 text-slate-600 border border-slate-200'
              }`}
            >
              {marketplaceEnabled ? (isRtl ? 'مفعل بالكامل' : 'ENABLED') : (isRtl ? 'معطل حالياً' : 'DISABLED')}
            </span>
          </div>

          <div className="p-5 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="text-xs text-slate-600 space-y-1">
              <p className="font-semibold text-slate-800">
                {isRtl ? 'حالة التفعيل اللحظية عبر التطبيقات' : 'Runtime Application State'}
              </p>
              <p className="text-slate-500">
                {marketplaceEnabled
                  ? isRtl
                    ? 'يتم عرض قسم الموسيقى والمشغل في الصفحة الرئيسية وقوائم التنقل بالتطبيق للمشترين.'
                    : 'The music section, player, and store routes are fully active and discoverable in mobile client apps.'
                  : isRtl
                    ? 'القسم مخفي في التطبيقات للزوار الجدد، مع الحفاظ على حق المشترين السابقين في الاستماع لمشترياتهم.'
                    : 'Music browsing is hidden for public users. Prior buyers retain permanent streaming for previously purchased tracks.'}
              </p>
            </div>

            <label className="relative inline-flex items-center cursor-pointer shrink-0">
              <input
                type="checkbox"
                checked={marketplaceEnabled}
                onChange={e => setMarketplaceEnabled(e.target.checked)}
                className="sr-only peer"
              />
              <div className="w-13 h-7 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-emerald-600"></div>
            </label>
          </div>
        </div>

        {/* Section 2: Audio Processing & Preview Bounds */}
        <div className="rounded-2xl border border-slate-200/90 bg-white shadow-2xs overflow-hidden">
          <div className="p-5 border-b border-slate-100">
            <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
              <Sliders className="w-4 h-4 text-purple-500" />
              {isRtl ? 'معالجة الصوت والمقاطع الترويجية' : 'Audio Processing & Preview Duration'}
            </h2>
            <p className="text-xs text-slate-500">
              {isRtl
                ? 'التحكم في معايير التشفير الصوتي، ومدة المعاينة المجانية، وحدود أحجام الملفات.'
                : 'Configure audio transcoding parameters, free preview clips, and file upload thresholds.'}
            </p>
          </div>

          <div className="p-5 space-y-6">
            {/* Paid Preview Duration Slider */}
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <div>
                  <label className="text-xs font-bold text-slate-800">
                    {t.paid_preview_duration || 'Paid Track Preview Duration (Seconds)'}
                  </label>
                  <p className="text-[11px] text-slate-500">
                    {t.paid_preview_duration_desc ||
                      'Duration in seconds (5–60s) of high-quality audio preview generated for non-entitled listeners.'}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <span className="px-3 py-1 bg-purple-50 text-purple-700 border border-purple-200 rounded-lg font-mono font-bold text-sm">
                    {previewDuration}s
                  </span>
                </div>
              </div>

              <div className="space-y-2">
                <input
                  type="range"
                  min="5"
                  max="60"
                  step="1"
                  value={previewDuration}
                  onChange={e => setPreviewDuration(parseInt(e.target.value) || 20)}
                  className="w-full accent-purple-600 cursor-pointer"
                />
                <div className="flex justify-between text-[10px] text-slate-400 font-mono">
                  <span>5s ({isRtl ? 'الأدنى' : 'Min Bound'})</span>
                  <span>15s</span>
                  <span>20s ({isRtl ? 'الافتراضي' : 'Default'})</span>
                  <span>30s</span>
                  <span>45s</span>
                  <span>60s ({isRtl ? 'الأقصى' : 'Max Bound'})</span>
                </div>
              </div>

              {previewDuration !== origPreviewDuration && (
                <div className="flex items-center gap-2 p-3 bg-amber-50/70 border border-amber-200/80 rounded-xl text-amber-800 text-xs">
                  <Sparkles className="w-4 h-4 text-amber-600 shrink-0" />
                  <span>
                    {isRtl
                      ? `تنبيه: سيؤدي تعديل المدة من ${origPreviewDuration}ث إلى ${previewDuration}ث لإطلاق مهام تلقائية لإعادة تقطيع ومعالجة المقاطع الترويجية لجميع المسارات القائمة.`
                      : `Notice: Saving with preview duration updated from ${origPreviewDuration}s to ${previewDuration}s will automatically enqueue background regeneration jobs for existing published tracks.`}
                  </span>
                </div>
              )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-5 pt-3 border-t border-slate-100">
              {/* Max Audio Upload Size (MB) */}
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                  <HardDrive className="w-3.5 h-3.5 text-slate-500" />
                  {t.max_upload_size || 'Max Audio Upload Size (MB)'}
                </label>
                <div className="relative">
                  <input
                    type="number"
                    min="1"
                    max={runtime?.effective_max_upload_mb || 30}
                    value={maxUploadMb}
                    onChange={e => setMaxUploadMb(parseInt(e.target.value) || 25)}
                    className="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                  />
                  <span className="absolute inset-y-0 right-3 flex items-center text-xs text-slate-400 pointer-events-none font-mono">
                    MB
                  </span>
                </div>
                <p className="text-[11px] text-slate-500">
                  {t.max_upload_size_desc ||
                    'Maximum audio file size allowed for seller track uploads, constrained by server runtime limits.'}
                </p>
                {runtime && (
                  <div className="text-[10px] text-slate-500 font-mono bg-slate-100/70 p-2.5 rounded-lg space-y-1">
                    <div className="flex justify-between">
                      <span>PHP upload_max_filesize:</span>
                      <span className="font-semibold text-slate-700">{runtime.php_upload_max_filesize}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>PHP post_max_size:</span>
                      <span className="font-semibold text-slate-700">{runtime.php_post_max_size}</span>
                    </div>
                    <div className="flex justify-between text-indigo-700 font-bold border-t border-slate-200 pt-1 mt-1">
                      <span>{isRtl ? 'الحد الفعلي المطبق للخادم:' : 'Effective Runtime Ceiling:'}</span>
                      <span>{runtime.effective_max_upload_mb} MB</span>
                    </div>
                    <div className="text-[9px] text-slate-400 italic pt-0.5">
                      {isRtl
                        ? 'ملاحظة: هذا الحد محكوم بإعدادات PHP وخادم الويب؛ لا يمكن لزيادة القيمة هنا تجاوز السقف الفعلي للبنية التحتية.'
                        : 'Note: Governed by PHP and reverse-proxy limits; increasing this setting cannot exceed the server infrastructure ceiling.'}
                    </div>
                  </div>
                )}
              </div>

              {/* Commission Rate (%) */}
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                  <DollarSign className="w-3.5 h-3.5 text-slate-500" />
                  {t.platform_commission || 'Platform Commission Rate (%)'}
                </label>
                <div className="relative">
                  <input
                    type="number"
                    min="0"
                    max="100"
                    step="0.25"
                    value={commissionRate}
                    onChange={e => setCommissionRate(parseFloat(e.target.value) || 0)}
                    className="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                  />
                  <span className="absolute inset-y-0 right-3 flex items-center text-xs text-slate-400 pointer-events-none font-mono">
                    %
                  </span>
                </div>
                <p className="text-[11px] text-slate-500">
                  {t.platform_commission_desc ||
                    'Platform fee deducted from digital music track sales and rentals.'}
                </p>
              </div>
            </div>

            {/* Allowed Formats */}
            <div className="space-y-2 pt-3 border-t border-slate-100">
              <label className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                <FileAudio className="w-3.5 h-3.5 text-indigo-500" />
                {t.allowed_formats || 'Permitted Audio Formats'}
              </label>
              <p className="text-[11px] text-slate-500">
                {t.allowed_formats_desc ||
                  'Accepted audio containers and encoding formats for seller uploads. AAC and M4A are independently handled.'}
              </p>

              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 pt-1">
                {availableFormatChips.map(chip => {
                  const isChecked = allowedFormats.includes(chip.key);
                  return (
                    <div
                      key={chip.key}
                      onClick={() => toggleFormat(chip.key)}
                      className={`p-3 rounded-xl border cursor-pointer transition flex items-start gap-3 select-none ${
                        isChecked
                          ? 'bg-indigo-50/60 border-indigo-200 text-slate-900 shadow-2xs'
                          : 'bg-white border-slate-200/70 text-slate-400 hover:bg-slate-50'
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={isChecked}
                        onChange={() => {}} // handled by parent div click
                        className="mt-0.5 accent-indigo-600 rounded"
                      />
                      <div className="space-y-0.5">
                        <div className="flex items-center gap-1.5">
                          <span className="text-xs font-bold uppercase">{chip.key}</span>
                          <span
                            className={`text-[9px] px-1.5 py-0.2 rounded font-mono ${
                              isChecked ? 'bg-indigo-200 text-indigo-900 font-bold' : 'bg-slate-100 text-slate-400'
                            }`}
                          >
                            .{chip.key}
                          </span>
                        </div>
                        <p className="text-[10px] text-slate-500 leading-tight">{chip.desc}</p>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        </div>

        {/* Section 3: Commercial Licensing & Offer Availability */}
        <div className="rounded-2xl border border-slate-200/90 bg-white shadow-2xs overflow-hidden">
          <div className="p-5 border-b border-slate-100">
            <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
              <DollarSign className="w-4 h-4 text-emerald-500" />
              {t.commercial_offers || 'Commercial Licensing & Offer Availability'}
            </h2>
            <p className="text-xs text-slate-500">
              {t.commercial_offers_desc ||
                'Toggle which commercial terms sellers may offer for published music tracks.'}
            </p>
          </div>

          <div className="p-5 divide-y divide-slate-100 space-y-4">
            {/* Free Offer */}
            <div className="flex items-center justify-between pt-2">
              <div className="space-y-0.5 max-w-xl">
                <p className="text-xs font-bold text-slate-800">
                  {t.offer_free_title || 'Free In-App Streaming'}
                </p>
                <p className="text-[11px] text-slate-500">
                  {t.offer_free_desc ||
                    'Allows full in-app listening without payment while the track is active.'}
                </p>
              </div>
              <label className="relative inline-flex items-center cursor-pointer shrink-0">
                <input
                  type="checkbox"
                  checked={offerFree}
                  onChange={e => setOfferFree(e.target.checked)}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
              </label>
            </div>

            {/* Rent Offer */}
            <div className="flex items-center justify-between pt-4">
              <div className="space-y-0.5 max-w-xl">
                <p className="text-xs font-bold text-slate-800">
                  {t.offer_rent_title || 'Time-Limited Rental'}
                </p>
                <p className="text-[11px] text-slate-500">
                  {t.offer_rent_desc ||
                    'Permits buyers to rent in-app listening rights for a configured duration.'}
                </p>
              </div>
              <label className="relative inline-flex items-center cursor-pointer shrink-0">
                <input
                  type="checkbox"
                  checked={offerRent}
                  onChange={e => setOfferRent(e.target.checked)}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
              </label>
            </div>

            {/* Default Rental Days */}
            {offerRent && (
              <div className="pt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60 p-4 rounded-xl border border-slate-100">
                <div className="space-y-0.5">
                  <label className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                    <Clock className="w-3.5 h-3.5 text-slate-500" />
                    {t.rental_duration_title || 'Default Rental Duration (Days)'}
                  </label>
                  <p className="text-[11px] text-slate-500">
                    {t.rental_duration_desc ||
                      'Standard rental period duration when creating rental offers (1–365 days).'}
                  </p>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                  <input
                    type="number"
                    min="1"
                    max="365"
                    value={rentalDays}
                    onChange={e => setRentalDays(parseInt(e.target.value) || 7)}
                    className="w-24 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 font-mono text-center"
                  />
                  <span className="text-xs text-slate-500">{isRtl ? 'يوم' : 'Days'}</span>
                </div>
              </div>
            )}

            {/* Sale Offer */}
            <div className="flex items-center justify-between pt-4">
              <div className="space-y-0.5 max-w-xl">
                <div className="flex items-center gap-2">
                  <p className="text-xs font-bold text-slate-800">
                    {t.offer_sale_title || 'Perpetual Purchase (Sale)'}
                  </p>
                  <span className="bg-amber-100 text-amber-800 text-[10px] px-2 py-0.5 rounded-full font-bold">
                    {isRtl ? 'بث داخل التطبيق فقط' : 'IN-APP STREAMING ONLY'}
                  </span>
                </div>
                <p className="text-[11px] text-slate-500">
                  {t.offer_sale_desc ||
                    'Grants permanent in-app streaming rights. Master audio downloads remain strictly disabled.'}
                </p>
              </div>
              <label className="relative inline-flex items-center cursor-pointer shrink-0">
                <input
                  type="checkbox"
                  checked={offerSale}
                  onChange={e => setOfferSale(e.target.checked)}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
              </label>
            </div>
          </div>
        </div>

        {/* Section 4: Seller Submissions & Moderation Governance */}
        <div className="rounded-2xl border border-slate-200/90 bg-white shadow-2xs overflow-hidden">
          <div className="p-5 border-b border-slate-100">
            <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
              <ShieldCheck className="w-4 h-4 text-blue-500" />
              {t.seller_controls || 'Seller Submissions & Moderation'}
            </h2>
            <p className="text-xs text-slate-500">
              {t.seller_controls_desc ||
                'Control who can upload music and whether tracks require administrative review before publishing.'}
            </p>
          </div>

          <div className="p-5 divide-y divide-slate-100 space-y-4">
            {/* Allow Seller Submissions */}
            <div className="flex items-center justify-between pt-2">
              <div className="space-y-0.5 max-w-xl">
                <p className="text-xs font-bold text-slate-800">
                  {t.seller_submissions_title || 'Allow Seller Submissions'}
                </p>
                <p className="text-[11px] text-slate-500">
                  {t.seller_submissions_desc ||
                    'When disabled, only platform administrators may upload and publish tracks.'}
                </p>
              </div>
              <label className="relative inline-flex items-center cursor-pointer shrink-0">
                <input
                  type="checkbox"
                  checked={sellerSubmissions}
                  onChange={e => setSellerSubmissions(e.target.checked)}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
            </div>

            {/* Require Moderation Approval */}
            <div className="flex items-center justify-between pt-4">
              <div className="space-y-0.5 max-w-xl">
                <p className="text-xs font-bold text-slate-800">
                  {t.seller_approval_title || 'Require Moderation Approval'}
                </p>
                <p className="text-[11px] text-slate-500">
                  {t.seller_approval_desc ||
                    'When enabled, newly uploaded seller tracks remain pending until approved by an administrator.'}
                </p>
              </div>
              <label className="relative inline-flex items-center cursor-pointer shrink-0">
                <input
                  type="checkbox"
                  checked={sellerApproval}
                  onChange={e => setSellerApproval(e.target.checked)}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
            </div>
          </div>
        </div>

        {/* Save Bar */}
        <div className="flex items-center justify-end gap-3 pt-2">
          <button
            type="submit"
            disabled={saving}
            className="inline-flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-xl text-xs font-bold shadow-md hover:shadow-lg transition-all active:scale-[0.98] disabled:opacity-50 cursor-pointer"
          >
            <Save className={`w-4 h-4 ${saving ? 'animate-spin' : ''}`} />
            <span>{saving ? (isRtl ? 'جاري الحفظ...' : 'Saving...') : t.save}</span>
          </button>
        </div>
      </form>
    </div>
  );
}
