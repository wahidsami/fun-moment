/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language } from '../types';
import { translations } from '../translations';
import { Languages, Globe, Coins, Award } from 'lucide-react';

interface LocalizationViewProps {
  language: Language;
}

export default function LocalizationView({ language }: LocalizationViewProps) {
  const t = translations[language];

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
          <div className="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 className="text-sm font-bold text-slate-800 flex items-center gap-1.5">
              <Coins className="h-4.5 w-4.5 text-indigo-600" />
              <span>{language === 'en' ? 'System Base Currency' : 'العملة الأساسية للنظام'}</span>
            </h3>
            <span className="bg-indigo-50 text-indigo-700 text-[10px] font-bold px-2 py-0.5 rounded">
              {language === 'en' ? 'SYSTEM DEFINED' : 'محددة نظامياً'}
            </span>
          </div>

          <div className="space-y-4">
            <div>
              <label className="text-[10px] font-bold text-slate-400 uppercase">
                {language === 'en' ? 'Platform Sovereign Currency' : 'العملة السيادية للمنصة'}
              </label>
              <div className="mt-1 flex items-center gap-2">
                <div className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-800 flex items-center justify-between">
                  <span>SAR - Saudi Arabian Riyal (ريال سعودي)</span>
                  <span className="text-[10px] font-mono text-emerald-600 font-bold">1.0000</span>
                </div>
              </div>
            </div>

            <div>
              <label className="text-[10px] font-bold text-slate-400 uppercase">
                {language === 'en' ? 'Fixed Sovereign Peg (SAMA Official)' : 'سعر الصرف المربوط رسمياً (البنك المركزي السعودي)'}
              </label>
              <div className="mt-1 flex items-center gap-2">
                <div className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-mono text-slate-700 flex items-center justify-between">
                  <span>1 USD = 3.7500 SAR</span>
                  <span className="text-[10px] text-slate-400 font-sans font-bold">FIXED PEG</span>
                </div>
              </div>
            </div>

            <div className="rounded-xl bg-slate-50 border border-slate-200/60 p-3 text-[10px] leading-relaxed text-slate-600 space-y-1">
              <span className="font-bold text-slate-800 block">
                {language === 'en' ? 'Statutory Currency Compliance:' : 'الامتثال لأنظمة العملة الوطنية:'}
              </span>
              <p>
                {language === 'en'
                  ? 'All platform pricing, orders, provider payouts, and invoices are strictly denominated and settled in Saudi Riyals (SAR). Foreign exchange rate configuration is locked to statutory Saudi Central Bank (SAMA) fixed parity.'
                  : 'كافة عمليات التسعير والطلبات وسحوبات المزودين والفواتير مقومة ومسواة حصراً بالريال السعودي (SAR). أسعار صرف العملات الأجنبية مقفلة بموجب التثبيت الرسمي الصادر عن البنك المركزي السعودي (ساما).'}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
