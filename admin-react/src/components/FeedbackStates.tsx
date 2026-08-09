/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language } from '../types';
import { RefreshCw, Inbox, AlertTriangle } from 'lucide-react';

interface LoadingStateProps {
  language: Language;
  message?: string;
}

export function LoadingState({ language, message }: LoadingStateProps) {
  const isRtl = language === 'ar';
  return (
    <div className="flex flex-col items-center justify-center p-12 text-center rounded-2xl border border-dashed border-slate-200 bg-white">
      <div className="relative flex items-center justify-center h-12 w-12 rounded-full bg-indigo-50 text-indigo-600 mb-4">
        <RefreshCw className="h-6 w-6 animate-spin" />
      </div>
      <h4 className="text-sm font-bold text-slate-800">
        {isRtl ? 'جاري تحميل البيانات...' : 'Loading system records...'}
      </h4>
      <p className="text-xs text-slate-400 mt-1 max-w-sm">
        {message || (isRtl 
          ? 'يرجى الانتظار بينما نقوم بمزامنة البيانات مع خادم Laravel API الافتراضي.'
          : 'Please wait while we sync datasets with the Mocked Laravel API contract.')}
      </p>
      
      {/* Skeletons mimicking list */}
      <div className="w-full mt-6 space-y-2 max-w-md">
        <div className="h-3 bg-slate-100 rounded-full w-3/4 animate-pulse mx-auto"></div>
        <div className="h-2.5 bg-slate-50 rounded-full w-5/6 animate-pulse mx-auto"></div>
        <div className="h-2.5 bg-slate-50 rounded-full w-2/3 animate-pulse mx-auto"></div>
      </div>
    </div>
  );
}

interface EmptyStateProps {
  language: Language;
  title?: string;
  description?: string;
  actionLabel?: string;
  onAction?: () => void;
}

export function EmptyState({ language, title, description, actionLabel, onAction }: EmptyStateProps) {
  const isRtl = language === 'ar';
  return (
    <div className="flex flex-col items-center justify-center p-12 text-center rounded-2xl border border-dashed border-slate-200 bg-white shadow-xs">
      <div className="flex items-center justify-center h-12 w-12 rounded-full bg-slate-50 text-slate-400 mb-4 border border-slate-100">
        <Inbox className="h-6 w-6" />
      </div>
      <h4 className="text-sm font-bold text-slate-800">
        {title || (isRtl ? 'لا يوجد سجلات متاحة' : 'No Records Found')}
      </h4>
      <p className="text-xs text-slate-400 mt-1.5 max-w-sm leading-relaxed">
        {description || (isRtl 
          ? 'لم نتمكن من العثور على أي نتائج تتطابق مع التصفية الحالية في قاعدة البيانات.'
          : 'We couldn\'t find any records matching your active filters in the database.')}
      </p>
      {actionLabel && onAction && (
        <button
          onClick={onAction}
          className="mt-5 rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white shadow-md shadow-indigo-100 transition hover:bg-indigo-700 focus:outline-none"
        >
          {actionLabel}
        </button>
      )}
    </div>
  );
}

interface ErrorStateProps {
  language: Language;
  title?: string;
  message?: string;
  onRetry?: () => void;
}

export function ErrorState({ language, title, message, onRetry }: ErrorStateProps) {
  const isRtl = language === 'ar';
  return (
    <div className="flex flex-col items-center justify-center p-12 text-center rounded-2xl border border-rose-100 bg-rose-50/30">
      <div className="flex items-center justify-center h-12 w-12 rounded-full bg-rose-50 text-rose-600 mb-4 border border-rose-100">
        <AlertTriangle className="h-5 w-5 animate-bounce" />
      </div>
      <h4 className="text-sm font-bold text-rose-900">
        {title || (isRtl ? 'حدث خطأ في مزامنة البيانات' : 'API Connection Lost')}
      </h4>
      <p className="text-xs text-rose-700/80 mt-1.5 max-w-sm leading-relaxed">
        {message || (isRtl 
          ? 'تعذر الاتصال بخادم PHP Laravel. يرجى مراجعة إعدادات الخادم أو المحاولة مرة أخرى.'
          : 'Could not resolve backend handshake with PHP Laravel server. Please check configurations or retry.')}
      </p>
      {onRetry && (
        <button
          onClick={onRetry}
          className="mt-5 flex items-center gap-1.5 rounded-lg bg-rose-600 px-4 py-1.5 text-xs font-bold text-white shadow-md shadow-rose-100 transition hover:bg-rose-700 focus:outline-none"
        >
          <RefreshCw className="h-3 w-3" />
          <span>{isRtl ? 'إعادة المحاولة' : 'Retry Handshake'}</span>
        </button>
      )}
    </div>
  );
}
