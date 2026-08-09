/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language } from '../types';
import { translations } from '../translations';
import { useState, FormEvent } from 'react';
import { Languages, Globe, Coins, CheckCircle2, Award } from 'lucide-react';

interface LocalizationViewProps {
  language: Language;
}

export default function LocalizationView({ language }: LocalizationViewProps) {
  const t = translations[language];
  const [exchangeRate, setExchangeRate] = useState('3.75');
  const [successMsg, setSuccessMsg] = useState('');

  const handleSaveRate = (e: FormEvent) => {
    e.preventDefault();
    setSuccessMsg(language === 'en' ? "Exchange rate updated successfully!" : "تم تحديث سعر صرف العملات بنجاح!");
    setTimeout(() => setSuccessMsg(''), 3000);
  };

  return (
    <div className="space-y-6">
      {/* View Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-slate-800">
            {t.loc_title}
          </h2>
          <p className="text-xs text-slate-500">
            {t.loc_subtitle}
          </p>
        </div>
      </div>

      {successMsg && (
        <div className="rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-xs text-emerald-800 font-semibold animate-fade-in flex items-center gap-1.5">
          <CheckCircle2 className="h-4 w-4 text-emerald-600" />
          <span>{successMsg}</span>
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Localization Options */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5 lg:col-span-2">
          <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-3 flex items-center gap-1.5">
            <Globe className="h-4.5 w-4.5 text-indigo-600" />
            <span>{language === 'en' ? 'Core Language Framework' : 'إطار اللغات الرئيسي'}</span>
          </h3>

          <div className="space-y-4">
            <div>
              <label className="text-xs font-bold text-slate-500">{t.loc_base_lang}</label>
              <div className="mt-1 flex items-center gap-3">
                <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700">
                  {language === 'en' ? 'English (US/UK) - LTR' : 'العربية - RTL'}
                </div>
                <span className="text-xs text-slate-400">({language === 'en' ? 'System Base' : 'أساسي بالكامل'})</span>
              </div>
            </div>

            <div>
              <label className="text-xs font-bold text-slate-500">{t.loc_active_langs}</label>
              <div className="mt-2 space-y-2">
                <div className="flex items-center justify-between rounded-lg border border-slate-100 p-3 text-xs font-medium">
                  <div className="flex items-center gap-2">
                    <span className="h-2 w-2 rounded-full bg-emerald-500" />
                    <span>English (EN)</span>
                  </div>
                  <span className="bg-slate-100 text-[10px] text-slate-500 rounded px-1.5 py-0.5">ACTIVE</span>
                </div>
                <div className="flex items-center justify-between rounded-lg border border-slate-100 p-3 text-xs font-medium">
                  <div className="flex items-center gap-2">
                    <span className="h-2 w-2 rounded-full bg-emerald-500" />
                    <span>العربية (AR)</span>
                  </div>
                  <span className="bg-slate-100 text-[10px] text-slate-500 rounded px-1.5 py-0.5">ACTIVE</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Currency Rates Box */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
          <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-3 flex items-center gap-1.5">
            <Coins className="h-4.5 w-4.5 text-indigo-600" />
            <span>{language === 'en' ? 'Currency Rate Settings' : 'سعر صرف العملات'}</span>
          </h3>

          <form onSubmit={handleSaveRate} className="space-y-4">
            <div>
              <label className="text-[10px] font-bold text-slate-400 uppercase">{t.loc_exchange_rate}</label>
              <div className="mt-1 flex items-center gap-2">
                <input
                  type="text"
                  value={exchangeRate}
                  onChange={(e) => setExchangeRate(e.target.value)}
                  className="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs outline-hidden focus:border-indigo-500 font-mono"
                  placeholder="3.75"
                />
                <span className="text-xs font-bold text-slate-600 shrink-0">SAR</span>
              </div>
            </div>

            <button
              type="submit"
              className="w-full rounded-lg bg-indigo-600 py-1.5 text-xs font-bold text-white hover:bg-indigo-700 transition cursor-pointer"
            >
              {t.save}
            </button>
          </form>

          <div className="rounded-xl bg-indigo-50/50 border border-indigo-100/50 p-3 text-[10px] leading-relaxed text-indigo-800">
            <span className="font-bold block mb-0.5">{language === 'en' ? 'Platform Currency Notice:' : 'ملاحظة عملة النظام:'}</span>
            {language === 'en'
              ? 'FUN MOMENT platform operates primarily in Saudi Riyals (SAR). Currency conversions only apply to international credit cards or payment processors.'
              : 'تعمل منصة فَن مومنت بشكل رئيسي بالريال السعودي (SAR). ينطبق تحويل العملات فقط على بطاقات الائتمان الدولية ومزودي الدفع الأجانب.'}
          </div>
        </div>
      </div>
    </div>
  );
}
