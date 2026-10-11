import React, { useState } from 'react';
import { Language, translations } from '../data/translations';
import { brandConfig } from '../config/brandConfig';
import {
  Headphones,
  Mail,
  UserX,
  CheckCircle2,
  AlertCircle,
  Loader2,
  ArrowLeft,
  ArrowRight,
  Send,
  HelpCircle,
} from 'lucide-react';

interface SupportPageProps {
  lang: Language;
  onBack: () => void;
}

export const SupportPage: React.FC<SupportPageProps> = ({ lang, onBack }) => {
  const t = translations[lang].support;
  const isRtl = lang === 'ar';

  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    topic: 'general',
    message: '',
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [serverError, setServerError] = useState<string | null>(null);
  const [honeypot, setHoneypot] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  const validate = () => {
    const newErrors: Record<string, string> = {};
    if (!formData.name.trim()) {
      newErrors.name = t.form.errorRequired;
    }
    if (!formData.email.trim()) {
      newErrors.email = t.form.errorRequired;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = t.form.errorEmail;
    }
    if (!formData.message.trim()) {
      newErrors.message = t.form.errorRequired;
    }
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setServerError(null);

    if (!validate()) return;

    setSubmitting(true);
    try {
      const response = await fetch('/api/support', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          ...formData,
          _hp: honeypot,
        }),
      });

      const data = await response.json().catch(() => ({}));

      if (response.ok && data.success) {
        setSubmitted(true);
        setFormData({
          name: '',
          email: '',
          phone: '',
          topic: 'general',
          message: '',
        });
        setHoneypot('');
      } else {
        const errorMsg =
          (lang === 'ar' ? data.errorAr : data.error) ||
          data.error ||
          t.form.errorMessage;
        setServerError(errorMsg);
      }
    } catch (err) {
      setServerError(t.form.errorNetwork);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#050814] pt-28 pb-24 text-start">
      <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Navigation Breadcrumb */}
        <div className="mb-8">
          <button
            onClick={onBack}
            className="inline-flex items-center gap-2 text-xs font-semibold text-slate-400 hover:text-[#00E5FF] transition-colors cursor-pointer"
          >
            {isRtl ? <ArrowRight className="w-4 h-4" /> : <ArrowLeft className="w-4 h-4" />}
            <span>{translations[lang].nav.home}</span>
          </button>
        </div>

        {/* Page Header */}
        <div className="p-8 sm:p-10 rounded-3xl bg-[#090E24] border border-white/10 relative overflow-hidden mb-12 shadow-xl">
          <div className="space-y-4 max-w-3xl">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#FFA800]/10 text-[#FFA800] text-xs font-bold">
              <Headphones className="w-3.5 h-3.5" />
              <span>{t.badge}</span>
            </div>

            <h1 className="text-3xl sm:text-4xl font-black text-white tracking-tight">
              {t.title}
            </h1>

            <p className="text-sm sm:text-base text-slate-300 leading-relaxed">
              {t.subtitle}
            </p>

            <div className="pt-2 flex items-center gap-2 text-xs text-slate-400">
              <Mail className="w-4 h-4 text-[#00E5FF]" />
              <span>{t.emailLabel}</span>
              <a
                href={`mailto:${brandConfig.supportEmail}`}
                className="font-mono text-slate-200 hover:text-[#00E5FF] transition-colors"
              >
                {brandConfig.supportEmail}
              </a>
            </div>
          </div>
        </div>

        {/* SECTION C: ACCOUNT DELETION (CRITICAL FOR GOOGLE PLAY COMPLIANCE) */}
        <div id="account-deletion" className="mb-12 p-6 sm:p-8 rounded-3xl bg-[#0B1129] border border-rose-500/20 shadow-xl space-y-4">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-xl bg-rose-500/10 border border-rose-500/20">
              <UserX className="w-5 h-5 text-rose-400" />
            </div>
            <div>
              <h2 className="text-xl sm:text-2xl font-bold text-white tracking-tight">
                {t.deletionTitle}
              </h2>
              <p className="text-xs sm:text-sm text-slate-400">
                {t.deletionSubtitle}
              </p>
            </div>
          </div>

          <div className="p-5 rounded-2xl bg-white/[0.02] border border-white/5 space-y-3">
            <p className="text-xs font-semibold text-slate-300 uppercase tracking-wider">
              {lang === 'ar' ? 'خطوات الحذف عبر التطبيق:' : 'In-App Account Deletion Steps:'}
            </p>
            <ol className="list-decimal list-inside space-y-2 text-xs sm:text-sm text-slate-300 leading-relaxed ps-1">
              {t.deletionSteps.map((step, idx) => (
                <li key={idx} className="text-slate-300">
                  <span className="text-slate-200">{step}</span>
                </li>
              ))}
            </ol>
          </div>

          <p className="text-xs text-slate-400 leading-relaxed">
            {t.deletionWebNote}
          </p>
        </div>

        {/* Support Grid: Contact Form (Left) & Common Help Topics (Right) */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
          
          {/* Section A: Contact Support Form (6 Cols) */}
          <div className="lg:col-span-6 p-6 sm:p-8 rounded-3xl bg-[#090E24] border border-white/10 shadow-xl space-y-6">
            <div className="space-y-1">
              <h2 className="text-xl sm:text-2xl font-bold text-white tracking-tight">
                {t.contactTitle}
              </h2>
              <p className="text-xs sm:text-sm text-slate-400">
                {t.contactSubtitle}
              </p>
            </div>

            {submitted ? (
              <div className="p-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 space-y-3 animate-in fade-in duration-300">
                <div className="flex items-center gap-2 text-emerald-400 font-bold text-base">
                  <CheckCircle2 className="w-5 h-5 shrink-0" />
                  <span>{t.form.successTitle}</span>
                </div>
                <p className="text-xs sm:text-sm leading-relaxed text-slate-300">
                  {t.form.successMessage}
                </p>
                <button
                  onClick={() => {
                    setSubmitted(false);
                    setServerError(null);
                  }}
                  className="mt-3 px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600/30 hover:bg-emerald-600/50 text-white transition-colors cursor-pointer"
                >
                  {lang === 'ar' ? 'إرسال رسالة أخرى' : 'Send Another Message'}
                </button>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="space-y-4">
                {/* Server Error Feedback */}
                {serverError && (
                  <div className="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-200 flex items-start gap-3 animate-in fade-in duration-200">
                    <AlertCircle className="w-5 h-5 text-rose-400 shrink-0 mt-0.5" />
                    <div className="text-xs sm:text-sm leading-relaxed space-y-1">
                      <p className="font-semibold text-rose-300">{serverError}</p>
                      <p className="text-slate-400 text-xs">
                        {lang === 'ar'
                          ? 'يمكنك أيضاً مراسلتنا مباشرة على:'
                          : 'You can also reach out directly to:'}{' '}
                        <a
                          href={`mailto:${brandConfig.supportEmail}`}
                          className="text-[#00E5FF] underline font-mono"
                        >
                          {brandConfig.supportEmail}
                        </a>
                      </p>
                    </div>
                  </div>
                )}

                {/* Anti-spam Honeypot Field (invisible to genuine users) */}
                <div
                  style={{
                    position: 'absolute',
                    left: '-9999px',
                    opacity: 0,
                    height: 0,
                    width: 0,
                    overflow: 'hidden',
                  }}
                  aria-hidden="true"
                >
                  <input
                    type="text"
                    name="_hp"
                    tabIndex={-1}
                    autoComplete="off"
                    value={honeypot}
                    onChange={(e) => setHoneypot(e.target.value)}
                  />
                </div>

                {/* Name */}
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-slate-300">
                    {t.form.name} <span className="text-[#FF007F]">*</span>
                  </label>
                  <input
                    type="text"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    placeholder={t.form.namePlaceholder}
                    className={`w-full px-4 py-2.5 rounded-xl bg-white/5 border ${
                      errors.name ? 'border-rose-500' : 'border-white/10'
                    } focus:border-[#00E5FF] focus:outline-none text-white text-sm transition-colors`}
                  />
                  {errors.name && <p className="text-xs text-rose-400">{errors.name}</p>}
                </div>

                {/* Email */}
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-slate-300">
                    {t.form.email} <span className="text-[#FF007F]">*</span>
                  </label>
                  <input
                    type="email"
                    value={formData.email}
                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    placeholder={t.form.emailPlaceholder}
                    className={`w-full px-4 py-2.5 rounded-xl bg-white/5 border ${
                      errors.email ? 'border-rose-500' : 'border-white/10'
                    } focus:border-[#00E5FF] focus:outline-none text-white text-sm transition-colors`}
                  />
                  {errors.email && <p className="text-xs text-rose-400">{errors.email}</p>}
                </div>

                {/* Phone (Optional) */}
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-slate-300">
                    {t.form.phone}
                  </label>
                  <input
                    type="tel"
                    value={formData.phone}
                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    placeholder={t.form.phonePlaceholder}
                    className="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 focus:border-[#00E5FF] focus:outline-none text-white text-sm transition-colors"
                  />
                </div>

                {/* Topic */}
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-slate-300">
                    {t.form.topic}
                  </label>
                  <select
                    value={formData.topic}
                    onChange={(e) => setFormData({ ...formData, topic: e.target.value })}
                    className="w-full px-4 py-2.5 rounded-xl bg-[#070B1F] border border-white/10 focus:border-[#00E5FF] focus:outline-none text-white text-sm transition-colors"
                  >
                    <option value="general">{t.form.topics.general}</option>
                    <option value="account">{t.form.topics.account}</option>
                    <option value="technical">{t.form.topics.technical}</option>
                    <option value="experiences">{t.form.topics.experiences}</option>
                    <option value="deletion">{t.form.topics.deletion}</option>
                  </select>
                </div>

                {/* Message */}
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-slate-300">
                    {t.form.message} <span className="text-[#FF007F]">*</span>
                  </label>
                  <textarea
                    rows={4}
                    value={formData.message}
                    onChange={(e) => setFormData({ ...formData, message: e.target.value })}
                    placeholder={t.form.messagePlaceholder}
                    className={`w-full px-4 py-2.5 rounded-xl bg-white/5 border ${
                      errors.message ? 'border-rose-500' : 'border-white/10'
                    } focus:border-[#00E5FF] focus:outline-none text-white text-sm transition-colors resize-none`}
                  />
                  {errors.message && <p className="text-xs text-rose-400">{errors.message}</p>}
                </div>

                {/* Submit Button */}
                <button
                  type="submit"
                  disabled={submitting}
                  className="w-full flex items-center justify-center gap-2 py-3.5 px-6 rounded-xl font-bold text-sm text-[#050814] bg-gradient-to-r from-[#00E5FF] via-[#FFA800] to-[#FF007F] hover:brightness-110 transition-all duration-300 shadow-md cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {submitting ? (
                    <>
                      <Loader2 className="w-4 h-4 animate-spin" />
                      <span>{t.form.submitting}</span>
                    </>
                  ) : (
                    <>
                      <Send className="w-4 h-4" />
                      <span>{t.form.submit}</span>
                    </>
                  )}
                </button>
              </form>
            )}
          </div>

          {/* Section B: Common Help Topics (6 Cols) */}
          <div className="lg:col-span-6 space-y-6">
            <div className="space-y-1">
              <h2 className="text-xl sm:text-2xl font-bold text-white tracking-tight">
                {t.topicsTitle}
              </h2>
              <p className="text-xs sm:text-sm text-slate-400">
                {t.topicsSubtitle}
              </p>
            </div>

            <div className="space-y-4">
              {t.topicsList.map((item, idx) => (
                <div
                  key={idx}
                  className="p-5 rounded-2xl bg-[#090E24] border border-white/10 space-y-2"
                >
                  <div className="flex items-center gap-2">
                    <HelpCircle className="w-4 h-4 text-[#00E5FF]" />
                    <h3 className="text-sm sm:text-base font-bold text-white">
                      {item.title}
                    </h3>
                  </div>
                  <p className="text-xs sm:text-sm text-slate-400 leading-relaxed ps-6">
                    {item.description}
                  </p>
                </div>
              ))}
            </div>

            {/* Email contact prompt */}
            <div className="p-5 rounded-2xl bg-white/[0.02] border border-white/5 space-y-2">
              <p className="text-xs text-slate-400 leading-relaxed">
                {lang === 'ar'
                  ? 'إذا كنت بحاجة إلى مساعدة عاجلة، يمكنك مراسلتنا مباشرة عبر البريد الإلكتروني وسيقوم فريق الدعم بمساعدتك.'
                  : 'If you require direct assistance, email our support desk directly and our team will assist you.'}
              </p>
              <a
                href={`mailto:${brandConfig.supportEmail}`}
                className="inline-flex items-center gap-1.5 text-xs font-semibold text-[#00E5FF] hover:underline"
              >
                <Mail className="w-3.5 h-3.5" />
                <span>{brandConfig.supportEmail}</span>
              </a>
            </div>
          </div>

        </div>

      </div>
    </div>
  );
};
