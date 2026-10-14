/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, useEffect } from 'react';
import { Language, UserRole, LegalTerm, LegalTermVersion } from '../types';
import { LaravelAPI } from '../api';
import { LoadingState } from './FeedbackStates';
import {
  Scale,
  Save,
  Eye,
  CheckCircle2,
  AlertCircle,
  Clock,
  History,
  Copy,
  Check,
  X,
  ExternalLink,
  ShieldCheck,
  FileText,
  UserCheck,
  Building2,
  RefreshCw,
  Info,
  Edit3,
} from 'lucide-react';
import { formatLegalDocumentToHtml } from '../utils/legalDocumentFormatter';

interface LegalTermsViewProps {
  language: Language;
  activeRole: UserRole;
}

export default function LegalTermsView({ language, activeRole }: LegalTermsViewProps) {
  const isRtl = language === 'ar';

  // Role tab: 'buyer' or 'seller'
  const [selectedRole, setSelectedRole] = useState<'buyer' | 'seller'>('buyer');
  // Language sub-tab for document: 'ar' or 'en'
  const [docLang, setDocLang] = useState<'ar' | 'en'>(language);

  const [termsList, setTermsList] = useState<LegalTerm[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [savingDraft, setSavingDraft] = useState<boolean>(false);
  const [publishing, setPublishing] = useState<boolean>(false);

  // Active document being edited
  const [draftTitle, setDraftTitle] = useState<string>('');
  const [draftContent, setDraftContent] = useState<string>('');
  const [editorViewMode, setEditorViewMode] = useState<'edit' | 'preview'>('edit');

  // Notifications
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  // Modals
  const [showPreviewModal, setShowPreviewModal] = useState<boolean>(false);
  const [showPublishModal, setShowPublishModal] = useState<boolean>(false);
  const [showHistoryModal, setShowHistoryModal] = useState<boolean>(false);
  const [historyList, setHistoryList] = useState<LegalTermVersion[]>([]);
  const [loadingHistory, setLoadingHistory] = useState<boolean>(false);
  const [viewingHistoricalVersion, setViewingHistoricalVersion] = useState<LegalTermVersion | null>(null);

  // Copied hash feedback
  const [copiedHash, setCopiedHash] = useState<string | null>(null);

  // Load all 4 terms documents
  const loadTerms = async () => {
    setLoading(true);
    try {
      const data = await LaravelAPI.getLegalTerms();
      setTermsList(data);
    } catch (err: any) {
      setFeedback({
        type: 'error',
        message: err?.message || (isRtl ? 'فشل تحميل وثائق الشروط' : 'Failed to load legal terms documents.'),
      });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadTerms();
  }, []);

  // Find currently active document based on selectedRole and docLang
  const currentDoc = termsList.find(
    (t) => t.role === selectedRole && t.language === docLang
  );

  // Whenever currentDoc changes, sync editor inputs
  useEffect(() => {
    if (currentDoc) {
      setDraftTitle(currentDoc.draft_title || currentDoc.title || '');
      setDraftContent(currentDoc.draft_content || '');
    } else {
      setDraftTitle('');
      setDraftContent('');
    }
  }, [currentDoc?.id, currentDoc?.draft_title, currentDoc?.draft_content]);

  // Clear feedback after 4 seconds
  useEffect(() => {
    if (feedback) {
      const timer = setTimeout(() => setFeedback(null), 4000);
      return () => clearTimeout(timer);
    }
  }, [feedback]);

  // Handle Save Draft
  const handleSaveDraft = async () => {
    if (!currentDoc) return;
    setSavingDraft(true);
    setFeedback(null);
    try {
      await LaravelAPI.saveLegalTermDraft(currentDoc.id, {
        draft_title: draftTitle.trim() || null,
        draft_content: draftContent,
      });

      // Update in local state
      setTermsList((prev) =>
        prev.map((t) =>
          t.id === currentDoc.id
            ? { ...t, draft_title: draftTitle.trim() || null, draft_content: draftContent }
            : t
        )
      );

      setFeedback({
        type: 'success',
        message: isRtl ? 'تم حفظ المسودة بنجاح دون أي تعديل على النسخة المنشورة.' : 'Draft saved successfully without affecting published versions.',
      });
    } catch (err: any) {
      setFeedback({
        type: 'error',
        message: err?.message || (isRtl ? 'فشل حفظ المسودة' : 'Failed to save draft.'),
      });
    } finally {
      setSavingDraft(false);
    }
  };

  // Handle Publish
  const handlePublishConfirmed = async () => {
    if (!currentDoc) return;
    setPublishing(true);
    setShowPublishModal(false);
    setFeedback(null);

    try {
      // Ensure draft is saved first if modified
      await LaravelAPI.saveLegalTermDraft(currentDoc.id, {
        draft_title: draftTitle.trim() || null,
        draft_content: draftContent,
      });

      const result = await LaravelAPI.publishLegalTerm(currentDoc.id);

      // Reload full state
      await loadTerms();

      setFeedback({
        type: 'success',
        message: isRtl
          ? `تم اعتماد ونشر الإصدار ${result.version.version_number} بنجاح مع إنشاء بصمة SHA-256 مشفرة.`
          : `Version ${result.version.version_number} published successfully with verified SHA-256 hash.`,
      });
    } catch (err: any) {
      setFeedback({
        type: 'error',
        message: err?.message || (isRtl ? 'فشل نشر الوثيقة' : 'Failed to publish terms.'),
      });
    } finally {
      setPublishing(false);
    }
  };

  // Load and show Version History
  const handleOpenHistory = async () => {
    if (!currentDoc) return;
    setShowHistoryModal(true);
    setLoadingHistory(true);
    try {
      const history = await LaravelAPI.getLegalTermHistory(currentDoc.id);
      setHistoryList(history);
    } catch (err: any) {
      setFeedback({
        type: 'error',
        message: err?.message || (isRtl ? 'فشل تحميل سجل الإصدارات' : 'Failed to load version history.'),
      });
    } finally {
      setLoadingHistory(false);
    }
  };

  // Copy hash helper
  const handleCopyHash = (hash: string) => {
    navigator.clipboard.writeText(hash);
    setCopiedHash(hash);
    setTimeout(() => setCopiedHash(null), 2500);
  };

  // Quick formatting insert helpers for rich editing (Markdown & HTML supported)
  const insertFormatting = (prefix: string, suffix: string, placeholder: string) => {
    setDraftContent((prev) => prev + `${prefix}${placeholder}${suffix}\n`);
  };

  if (loading) {
    return (
      <LoadingState
        language={language}
        message={isRtl ? 'جاري تحميل وثائق الشروط والأحكام...' : 'Loading Legal Terms registry...'}
      />
    );
  }

  const isCurrentDocRtl = docLang === 'ar';
  const hasPublished = !!currentDoc?.current_version;
  const currentVersion = currentDoc?.current_version;
  const plainTextLength = draftContent.replace(/<[^>]*>/g, '').trim().length;

  return (
    <div className="space-y-6 max-w-7xl mx-auto pb-12">
      {/* Page Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
        <div className="flex items-center gap-4">
          <div className="h-12 w-12 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-xs">
            <Scale className="h-6 w-6" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-black text-slate-900 tracking-tight">
                {isRtl ? 'إدارة الشروط والأحكام' : 'Legal Terms & Conditions'}
              </h1>
              <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-slate-100 text-slate-600 border border-slate-200 uppercase font-mono">
                Bilingual v2.0
              </span>
            </div>
            <p className="text-xs text-slate-500 mt-1">
              {isRtl
                ? 'إدارة وصياغة ونشر شروط المشتري ومقدم الخدمة باللغتين العربية والإنجليزية مع أرشفة بصمات القبول.'
                : 'Manage, draft, and publish immutable Buyer and Seller terms in Arabic & English with audit hashing.'}
            </p>
          </div>
        </div>

        <button
          onClick={loadTerms}
          disabled={loading}
          className="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-xl border border-slate-200 transition-colors"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
          {isRtl ? 'تحديث السجلات' : 'Refresh'}
        </button>
      </div>

      {/* Feedback banner */}
      {feedback && (
        <div
          className={`flex items-center gap-3 p-4 rounded-xl border text-xs font-medium transition-all ${
            feedback.type === 'success'
              ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
              : 'bg-rose-50 text-rose-800 border-rose-200'
          }`}
        >
          {feedback.type === 'success' ? (
            <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
          ) : (
            <AlertCircle className="h-4 w-4 text-rose-600 shrink-0" />
          )}
          <span className="flex-1">{feedback.message}</span>
          <button onClick={() => setFeedback(null)} className="text-slate-400 hover:text-slate-600">
            <X className="h-4 w-4" />
          </button>
        </div>
      )}

      {/* Main Tabs: Role Selection (Buyer vs Seller) */}
      <div className="flex flex-col sm:flex-row items-center justify-between gap-4 border-b border-slate-200 pb-3">
        <div className="flex items-center gap-2 bg-slate-100/80 p-1 rounded-xl w-full sm:w-auto">
          <button
            onClick={() => setSelectedRole('buyer')}
            className={`flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-xs font-bold transition-all ${
              selectedRole === 'buyer'
                ? 'bg-white text-indigo-600 shadow-xs border border-slate-200/60'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <UserCheck className="h-4 w-4" />
            {isRtl ? 'شروط المشتري (Buyer)' : 'Buyer Terms'}
          </button>

          <button
            onClick={() => setSelectedRole('seller')}
            className={`flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-xs font-bold transition-all ${
              selectedRole === 'seller'
                ? 'bg-white text-indigo-600 shadow-xs border border-slate-200/60'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <Building2 className="h-4 w-4" />
            {isRtl ? 'شروط مقدم الخدمة (Seller)' : 'Seller Terms'}
          </button>
        </div>

        {/* Language sub-tabs for selected role */}
        <div className="flex items-center gap-1.5 bg-slate-100 p-1 rounded-xl">
          <button
            onClick={() => setDocLang('ar')}
            className={`px-4 py-1.5 rounded-lg text-xs font-bold transition-all ${
              docLang === 'ar'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            العربية (RTL)
          </button>
          <button
            onClick={() => setDocLang('en')}
            className={`px-4 py-1.5 rounded-lg text-xs font-bold transition-all ${
              docLang === 'en'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            English (LTR)
          </button>
        </div>
      </div>

      {/* Active Document Card */}
      <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        {/* Document Status Banner */}
        <div className="p-5 border-b border-slate-100 bg-slate-50/60 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="space-y-1">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="text-sm font-extrabold text-slate-800">
                {currentDoc?.title || (isRtl ? 'وثيقة الشروط' : 'Terms Document')}
              </span>
              <span className="text-xs px-2 py-0.5 rounded-md font-mono font-medium bg-slate-200/70 text-slate-700">
                {selectedRole.toUpperCase()} / {docLang.toUpperCase()}
              </span>

              {hasPublished ? (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                  <ShieldCheck className="h-3 w-3" />
                  {isRtl
                    ? `الإصدار ${currentVersion?.version_number} منشور`
                    : `Version ${currentVersion?.version_number} Published`}
                </span>
              ) : (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                  <Clock className="h-3 w-3" />
                  {isRtl ? 'مسودة فقط (غير منشورة بعد)' : 'Draft Only (Not Published Yet)'}
                </span>
              )}
            </div>

            {hasPublished && currentVersion && (
              <div className="flex items-center gap-3 text-[11px] text-slate-500 flex-wrap pt-0.5">
                <span>
                  {isRtl ? 'تاريخ النشر:' : 'Published:'}{' '}
                  {currentVersion.published_at ? new Date(currentVersion.published_at).toLocaleString() : '—'}
                </span>
                <span>•</span>
                <span>
                  {isRtl ? 'المشرف:' : 'By:'} {currentVersion.publisher_name || 'Admin'}
                </span>
                <span>•</span>
                <span className="inline-flex items-center gap-1 font-mono text-[10px] bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">
                  SHA-256: {currentVersion.content_hash.slice(0, 12)}...
                  <button
                    onClick={() => handleCopyHash(currentVersion.content_hash)}
                    title={isRtl ? 'نسخ البصمة الكاملة' : 'Copy Full Hash'}
                    className="text-slate-400 hover:text-indigo-600 transition-colors"
                  >
                    {copiedHash === currentVersion.content_hash ? (
                      <Check className="h-3 w-3 text-emerald-600" />
                    ) : (
                      <Copy className="h-3 w-3" />
                    )}
                  </button>
                </span>
              </div>
            )}
          </div>

          {/* Quick Actions */}
          <div className="flex items-center gap-2 flex-wrap">
            <button
              onClick={handleOpenHistory}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 shadow-2xs transition-colors"
            >
              <History className="h-3.5 w-3.5 text-slate-500" />
              {isRtl ? 'سجل الإصدارات' : 'Version History'}
              {currentDoc?.versions_count ? (
                <span className="ml-1 px-1.5 py-0.2 rounded-full bg-slate-100 text-[10px] font-mono text-slate-600">
                  {currentDoc.versions_count}
                </span>
              ) : null}
            </button>

            <button
              onClick={() => setShowPreviewModal(true)}
              disabled={plainTextLength === 0}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors disabled:opacity-50"
            >
              <Eye className="h-3.5 w-3.5" />
              {isRtl ? 'معاينة العقد' : 'Preview'}
            </button>

            <button
              onClick={handleSaveDraft}
              disabled={savingDraft}
              className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-800 bg-slate-100 hover:bg-slate-200 border border-slate-300 shadow-2xs transition-colors disabled:opacity-50"
            >
              <Save className="h-3.5 w-3.5" />
              {savingDraft ? (isRtl ? 'جاري الحفظ...' : 'Saving...') : (isRtl ? 'حفظ المسودة' : 'Save Draft')}
            </button>

            <button
              onClick={() => setShowPublishModal(true)}
              disabled={plainTextLength < 5 || publishing}
              className="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-2xs transition-colors disabled:opacity-50"
            >
              <ShieldCheck className="h-3.5 w-3.5" />
              {publishing ? (isRtl ? 'جاري النشر...' : 'Publishing...') : (isRtl ? 'اعتماد ونشر' : 'Publish Version')}
            </button>
          </div>
        </div>

        {/* Editor Form Area */}
        <div className="p-6 space-y-5">
          {/* Draft Title Input */}
          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-700">
              {isRtl ? 'عنوان الوثيقة (مسودة)' : 'Document Display Title (Draft)'}
            </label>
            <input
              type="text"
              value={draftTitle}
              onChange={(e) => setDraftTitle(e.target.value)}
              placeholder={isRtl ? 'أدخل عنوان الوثيقة...' : 'Enter document title...'}
              dir={isCurrentDocRtl ? 'rtl' : 'ltr'}
              className="w-full text-xs font-semibold px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-hidden transition-all"
            />
          </div>

          {/* Formatting Helper & Mode Switcher Bar */}
          <div className="flex items-center justify-between gap-3 p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 flex-wrap">
            {/* Left: Mode Switcher Tabs */}
            <div className="flex items-center gap-1 p-0.5 rounded-lg bg-slate-200/60 border border-slate-200/80">
              <button
                type="button"
                onClick={() => setEditorViewMode('edit')}
                className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-bold transition-all ${
                  editorViewMode === 'edit'
                    ? 'bg-white text-indigo-700 shadow-2xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <Edit3 className="h-3.5 w-3.5" />
                {isRtl ? 'تحرير المستند' : 'Edit Source'}
              </button>
              <button
                type="button"
                onClick={() => setEditorViewMode('preview')}
                className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-bold transition-all ${
                  editorViewMode === 'preview'
                    ? 'bg-white text-indigo-700 shadow-2xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <Eye className="h-3.5 w-3.5" />
                {isRtl ? 'معاينة فورية' : 'Live Preview'}
              </button>
            </div>

            {/* Middle: Quick Insert Formatting Buttons (Markdown & HTML) */}
            <div className="flex items-center gap-1 text-[11px] font-semibold text-slate-600 flex-wrap">
              <span className="text-[10px] text-slate-400 font-bold uppercase tracking-wider px-1">
                {isRtl ? 'إدراج سريع:' : 'Insert:'}
              </span>
              <button
                type="button"
                onClick={() => insertFormatting('## ', '', isRtl ? 'عنوان قسم رئيسي' : 'Major Section')}
                title={isRtl ? 'عنوان رئيسي (H2)' : 'Major Heading (H2)'}
                className="px-2 py-1 rounded bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-bold transition-colors"
              >
                H2
              </button>
              <button
                type="button"
                onClick={() => insertFormatting('### ', '', isRtl ? 'بند فرعي' : 'Subsection')}
                title={isRtl ? 'بند فرعي (H3)' : 'Subsection (H3)'}
                className="px-2 py-1 rounded bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 transition-colors"
              >
                H3
              </button>
              <button
                type="button"
                onClick={() => insertFormatting('**', '**', isRtl ? 'نص بارز ومهم' : 'Important Bold Text')}
                title={isRtl ? 'نص عريض' : 'Bold Text'}
                className="px-2 py-1 rounded bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-bold transition-colors"
              >
                Bold
              </button>
              <button
                type="button"
                onClick={() => setDraftContent((prev) => prev + `- ${isRtl ? 'بند نقطي 1' : 'Bullet item 1'}\n- ${isRtl ? 'بند نقطي 2' : 'Bullet item 2'}\n`)}
                title={isRtl ? 'قائمة نقطية' : 'Bullet List'}
                className="px-2 py-1 rounded bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 transition-colors"
              >
                List
              </button>
              <button
                type="button"
                onClick={() => setDraftContent((prev) => prev + `1. ${isRtl ? 'البند الأول' : 'First item'}\n2. ${isRtl ? 'البند الثاني' : 'Second item'}\n`)}
                title={isRtl ? 'قائمة رقمية' : 'Numbered List'}
                className="px-2 py-1 rounded bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 transition-colors"
              >
                1. 2.
              </button>
              <button
                type="button"
                onClick={() => setDraftContent((prev) => prev + `[${isRtl ? 'رابط الدعم' : 'Support Link'}](https://funmoments.unifinitylab.com)\n`)}
                title={isRtl ? 'رابط إلكتروني' : 'Link'}
                className="px-2 py-1 rounded bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 transition-colors"
              >
                Link
              </button>
            </div>

            {/* Right: Character Count */}
            <div className="text-[10px] font-mono text-slate-400">
              {isRtl ? `${plainTextLength} حرف` : `${plainTextLength} characters`}
            </div>
          </div>

          {/* Draft Content: Editor or Live Preview */}
          <div className="space-y-1.5">
            <div className="flex items-center justify-between">
              <label className="block text-xs font-bold text-slate-700">
                {isRtl
                  ? 'محتوى نص الشروط والأحكام (يدعم تنسيق Markdown و HTML)'
                  : 'Terms & Conditions Document Body (Markdown & HTML Supported)'}
              </label>
              <span className="text-[10px] text-slate-400">
                {isCurrentDocRtl ? 'اتجاه النص: من اليمين لليسار (RTL)' : 'Direction: Left-to-Right (LTR)'}
              </span>
            </div>

            {editorViewMode === 'edit' ? (
              <textarea
                value={draftContent}
                onChange={(e) => setDraftContent(e.target.value)}
                rows={16}
                dir={isCurrentDocRtl ? 'rtl' : 'ltr'}
                placeholder={
                  isCurrentDocRtl
                    ? 'أدخل بنود الشروط والأحكام هنا...\n\nيدعم المحرر تنسيق Markdown (# للعناوين، **للخط العريض**، - للقوائم النقطية، 1. للقوائم الرقمية) بالإضافة إلى وسوم HTML القياسية.'
                    : 'Enter terms and conditions text here...\n\nThe editor supports Markdown formatting (# for headings, **for bold**, - for bullet lists, 1. for numbered lists) as well as standard HTML tags.'
                }
                className={`w-full text-xs font-mono p-4 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-hidden transition-all leading-relaxed ${
                  isCurrentDocRtl ? 'text-right' : 'text-left'
                }`}
              />
            ) : (
              <div
                dir={isCurrentDocRtl ? 'rtl' : 'ltr'}
                className={`w-full min-h-[380px] max-h-[500px] overflow-y-auto p-5 rounded-xl border border-slate-200 bg-white shadow-2xs font-sans ${
                  isCurrentDocRtl ? 'text-right' : 'text-left'
                }`}
              >
                {draftContent ? (
                  <div
                    className="prose prose-xs max-w-none text-slate-700"
                    dangerouslySetInnerHTML={{ __html: formatLegalDocumentToHtml(draftContent) }}
                  />
                ) : (
                  <div className="text-center py-20 text-slate-400">
                    {isRtl ? 'لا يوجد محتوى في المسودة بعد للمعاينة.' : 'No draft content available to preview.'}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Safety Notice */}
          <div className="flex items-start gap-2.5 p-3.5 rounded-xl bg-slate-50 border border-slate-200/60 text-xs text-slate-500">
            <Info className="h-4 w-4 text-indigo-500 shrink-0 mt-0.5" />
            <p className="leading-relaxed text-[11px]">
              {isRtl
                ? 'ملاحظة أمان: حفظ المسودة يحفظ التغييرات داخلياً فقط ولا يؤثر إطلاقاً على مستخدمي التطبيق الحاليين. لا يدخل أي تعديل حيز التنفيذ إلا بعد الضغط على "اعتماد ونشر"، حيث يتم إنشاء إصدار مؤرخ غير قابل للتعديل وتحديث مؤشر النسخة النشطة.'
                : 'Security Note: Saving a draft stores changes privately without affecting active users. Revisions only take effect once you click "Publish Version", which creates an immutable timestamped snapshot with an auditable cryptographic SHA-256 hash.'}
            </p>
          </div>
        </div>
      </div>

      {/* MODAL 1: Document Preview (Emulating Flutter Modal) */}
      {showPreviewModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="bg-white rounded-2xl max-w-2xl w-full max-h-[85vh] flex flex-col shadow-2xl border border-slate-100 overflow-hidden">
            {/* Modal Header */}
            <div className="flex items-center justify-between p-4 px-6 border-b border-slate-100 bg-slate-50/80">
              <div className="flex items-center gap-2">
                <FileText className="h-5 w-5 text-indigo-600" />
                <div>
                  <h3 className="text-sm font-extrabold text-slate-800">
                    {draftTitle || currentDoc?.title || (isRtl ? 'معاينة الشروط' : 'Terms Preview')}
                  </h3>
                  <span className="text-[10px] text-slate-400 font-mono">
                    {selectedRole.toUpperCase()} / {docLang.toUpperCase()} — {isRtl ? 'معاينة وضع شاشة التسجيل' : 'Registration Modal View'}
                  </span>
                </div>
              </div>
              <button
                onClick={() => setShowPreviewModal(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200/50 transition-colors"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Modal Body (Formatted as mobile agreement modal) */}
            <div
              dir={isCurrentDocRtl ? 'rtl' : 'ltr'}
              className="flex-1 p-6 overflow-y-auto space-y-4 text-xs text-slate-700 leading-relaxed font-sans"
            >
              {draftContent ? (
                <div
                  className="prose prose-xs max-w-none text-slate-700 space-y-3"
                  dangerouslySetInnerHTML={{ __html: formatLegalDocumentToHtml(draftContent) }}
                />
              ) : (
                <div className="text-center py-12 text-slate-400">
                  {isRtl ? 'لا يوجد محتوى في المسودة بعد.' : 'No draft content available to preview.'}
                </div>
              )}
            </div>

            {/* Modal Footer (Emulating Flutter Checkbox & Agree Button) */}
            <div className="p-4 px-6 border-t border-slate-100 bg-slate-50 flex items-center justify-between gap-4">
              <div className="flex items-center gap-2 text-[11px] text-slate-500 font-medium">
                <span className="h-4 w-4 rounded border border-slate-300 flex items-center justify-center bg-white">
                  <Check className="h-3 w-3 text-indigo-600" />
                </span>
                <span>{isRtl ? 'أوافق على الشروط والأحكام (محاكاة)' : 'I agree to the Terms & Conditions (Simulation)'}</span>
              </div>
              <button
                onClick={() => setShowPreviewModal(false)}
                className="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-100 transition-colors shadow-2xs"
              >
                {isRtl ? 'إغلاق المعاينة' : 'Close Preview'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 2: Publish Confirmation */}
      {showPublishModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-5">
            <div className="h-12 w-12 rounded-xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center mx-auto">
              <ShieldCheck className="h-6 w-6" />
            </div>

            <div className="text-center space-y-2">
              <h3 className="text-base font-extrabold text-slate-900">
                {isRtl ? 'تأكيد اعتماد ونشر الوثيقة' : 'Confirm Publication'}
              </h3>
              <p className="text-xs text-slate-500 leading-relaxed">
                {isRtl
                  ? `هل أنت متأكد من رغبتك في اعتماد ونشر هذه المسودة كوحدة قانونية رسمية لوثيقة "${currentDoc?.title}"؟ سيتم إنشاء إصدار جديد غير قابل للحذف أو التعديل، وحساب بصمة SHA-256 فريدة له فوراً.`
                  : `Are you sure you want to publish this draft as the official legal version for "${currentDoc?.title}"? An immutable new version will be created, with a cryptographic SHA-256 hash immediately recorded.`}
              </p>
            </div>

            <div className="p-3 bg-slate-50 rounded-xl border border-slate-200/60 text-[11px] text-slate-600 space-y-1">
              <div>
                <strong>{isRtl ? 'الدور:' : 'Role:'}</strong> {selectedRole.toUpperCase()}
              </div>
              <div>
                <strong>{isRtl ? 'اللغة:' : 'Language:'}</strong> {docLang.toUpperCase()}
              </div>
              <div>
                <strong>{isRtl ? 'الإصدار التالي:' : 'Next Version:'}</strong> v{(currentDoc?.versions_count || 0) + 1}
              </div>
            </div>

            <div className="flex items-center gap-3">
              <button
                onClick={() => setShowPublishModal(false)}
                className="flex-1 py-2.5 rounded-xl text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors"
              >
                {isRtl ? 'إلغاء' : 'Cancel'}
              </button>
              <button
                onClick={handlePublishConfirmed}
                disabled={publishing}
                className="flex-1 py-2.5 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-2xs"
              >
                {publishing ? (isRtl ? 'جاري النشر...' : 'Publishing...') : (isRtl ? 'تأكيد النشر الرسمي' : 'Confirm & Publish')}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 3: Version History Drawer / Modal */}
      {showHistoryModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="bg-white rounded-2xl max-w-3xl w-full max-h-[85vh] flex flex-col shadow-2xl border border-slate-100 overflow-hidden">
            <div className="flex items-center justify-between p-4 px-6 border-b border-slate-100 bg-slate-50/80">
              <div className="flex items-center gap-2">
                <History className="h-5 w-5 text-indigo-600" />
                <div>
                  <h3 className="text-sm font-extrabold text-slate-800">
                    {isRtl ? 'سجل الإصدارات المعتمدة' : 'Immutable Version History'}
                  </h3>
                  <span className="text-[10px] text-slate-400 font-mono">
                    {currentDoc?.title} ({docLang.toUpperCase()})
                  </span>
                </div>
              </div>
              <button
                onClick={() => {
                  setShowHistoryModal(false);
                  setViewingHistoricalVersion(null);
                }}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200/50 transition-colors"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="flex-1 p-6 overflow-y-auto space-y-4">
              {loadingHistory ? (
                <div className="text-center py-12 text-xs text-slate-400">
                  {isRtl ? 'جاري تحميل الأرشيف...' : 'Loading history records...'}
                </div>
              ) : historyList.length === 0 ? (
                <div className="text-center py-12 text-slate-400 text-xs">
                  {isRtl ? 'لم يتم نشر أي إصدارات لهذه الوثيقة بعد.' : 'No published versions recorded yet.'}
                </div>
              ) : viewingHistoricalVersion ? (
                // Detailed read-only view of a selected past version
                <div className="space-y-4">
                  <button
                    onClick={() => setViewingHistoricalVersion(null)}
                    className="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 hover:text-indigo-800"
                  >
                    ← {isRtl ? 'العودة لقائمة الإصدارات' : 'Back to Version List'}
                  </button>

                  <div className="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2 text-xs">
                    <div className="flex items-center justify-between">
                      <span className="font-extrabold text-slate-900">
                        {viewingHistoricalVersion.title} (v{viewingHistoricalVersion.version_number})
                      </span>
                      {viewingHistoricalVersion.is_current && (
                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                          {isRtl ? 'النسخة النشطة الحالية' : 'Currently Active'}
                        </span>
                      )}
                    </div>
                    <div className="text-[11px] text-slate-500">
                      {isRtl ? 'تاريخ النشر:' : 'Published:'}{' '}
                      {viewingHistoricalVersion.published_at ? new Date(viewingHistoricalVersion.published_at).toLocaleString() : '—'}{' '}
                      | {isRtl ? 'المشرف:' : 'By:'} {viewingHistoricalVersion.publisher_name}
                    </div>
                    <div className="font-mono text-[10px] text-slate-500 bg-white p-2 rounded border border-slate-200 break-all">
                      SHA-256: {viewingHistoricalVersion.content_hash}
                    </div>
                  </div>

                  <div
                    dir={isCurrentDocRtl ? 'rtl' : 'ltr'}
                    className="p-5 rounded-xl border border-slate-200 bg-white max-h-80 overflow-y-auto text-xs leading-relaxed"
                    dangerouslySetInnerHTML={{ __html: formatLegalDocumentToHtml(viewingHistoricalVersion.content) }}
                  />
                </div>
              ) : (
                // Table of versions
                <div className="space-y-2.5">
                  {historyList.map((ver) => (
                    <div
                      key={ver.id}
                      className="p-4 rounded-xl border border-slate-200/80 bg-white hover:bg-slate-50 transition-colors flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs"
                    >
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <span className="font-mono font-black text-slate-900">
                            Version {ver.version_number}
                          </span>
                          {ver.is_current ? (
                            <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                              {isRtl ? 'النشط حالياً' : 'Currently Active'}
                            </span>
                          ) : (
                            <span className="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-600">
                              {isRtl ? 'مؤرشف' : 'Archived'}
                            </span>
                          )}
                        </div>
                        <div className="text-[11px] text-slate-500 flex items-center gap-2 flex-wrap">
                          <span>
                            {ver.published_at ? new Date(ver.published_at).toLocaleString() : '—'}
                          </span>
                          <span>•</span>
                          <span>{ver.publisher_name}</span>
                        </div>
                        <div className="font-mono text-[10px] text-slate-400 break-all">
                          Hash: {ver.content_hash.slice(0, 24)}...
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => handleCopyHash(ver.content_hash)}
                          title={isRtl ? 'نسخ البصمة' : 'Copy Hash'}
                          className="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:text-indigo-600 transition-colors inline-flex items-center gap-1 text-[11px]"
                        >
                          {copiedHash === ver.content_hash ? (
                            <Check className="h-3.5 w-3.5 text-emerald-600" />
                          ) : (
                            <Copy className="h-3.5 w-3.5" />
                          )}
                          {isRtl ? 'البصمة' : 'Hash'}
                        </button>

                        <button
                          onClick={() => setViewingHistoricalVersion(ver)}
                          className="px-3 py-1.5 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 font-semibold transition-colors text-[11px] inline-flex items-center gap-1"
                        >
                          <Eye className="h-3.5 w-3.5" />
                          {isRtl ? 'عرض النص' : 'Read Version'}
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div className="p-4 px-6 border-t border-slate-100 bg-slate-50 text-right">
              <button
                onClick={() => {
                  setShowHistoryModal(false);
                  setViewingHistoricalVersion(null);
                }}
                className="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-100 transition-colors shadow-2xs"
              >
                {isRtl ? 'إغلاق' : 'Close'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
