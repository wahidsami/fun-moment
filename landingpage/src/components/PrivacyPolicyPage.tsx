import React, { useState } from 'react';
import { Language, translations } from '../data/translations';
import { brandConfig } from '../config/brandConfig';
import { ShieldCheck, Calendar, ArrowLeft, ArrowRight, FileText, Mail } from 'lucide-react';

interface PrivacyPolicyPageProps {
  lang: Language;
  onBack: () => void;
}

export const PrivacyPolicyPage: React.FC<PrivacyPolicyPageProps> = ({ lang, onBack }) => {
  const t = translations[lang].privacy;
  const isRtl = lang === 'ar';
  const [activeSection, setActiveSection] = useState<string>(t.sections[0].id);

  const scrollToSection = (id: string) => {
    setActiveSection(id);
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  return (
    <div className="min-h-screen bg-[#050814] pt-28 pb-24 text-start">
      <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Back to Home Button */}
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
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#00E5FF]/10 text-[#00E5FF] text-xs font-bold">
              <ShieldCheck className="w-3.5 h-3.5" />
              <span>{t.badge}</span>
            </div>

            <h1 className="text-3xl sm:text-4xl font-black text-white tracking-tight">
              {t.title}
            </h1>

            <p className="text-sm sm:text-base text-slate-300 leading-relaxed">
              {t.subtitle}
            </p>

            <div className="pt-2 flex items-center gap-2 text-xs font-mono text-slate-400">
              <Calendar className="w-3.5 h-3.5 text-[#00E5FF]" />
              <span>{t.lastUpdated}</span>
              <span className="text-slate-200 font-bold">{brandConfig.privacyLastUpdated[lang]}</span>
            </div>
          </div>
        </div>

        {/* Layout with Table of Contents + Policy Content */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
          
          {/* Sticky Table of Contents (4 Cols) */}
          <div className="hidden lg:block lg:col-span-4 sticky top-28 space-y-2 p-5 rounded-2xl bg-[#0B1129]/80 border border-white/10 backdrop-blur-md">
            <p className="text-xs font-bold uppercase tracking-wider text-slate-400 px-3 pb-2 border-b border-white/10 flex items-center gap-2">
              <FileText className="w-3.5 h-3.5 text-[#00E5FF]" />
              <span>{t.tableOfContents}</span>
            </p>
            <nav className="space-y-1">
              {t.sections.map((sec) => (
                <button
                  key={sec.id}
                  onClick={() => scrollToSection(sec.id)}
                  className={`w-full text-start px-3 py-2 rounded-lg text-xs font-medium transition-colors truncate block ${
                    activeSection === sec.id
                      ? 'bg-[#00E5FF]/10 text-[#00E5FF] font-bold'
                      : 'text-slate-400 hover:text-white hover:bg-white/5'
                  }`}
                >
                  {sec.title}
                </button>
              ))}
            </nav>

            <div className="pt-4 border-t border-white/10">
              <a
                href={`mailto:${brandConfig.supportEmail}`}
                className="flex items-center gap-2 px-3 py-2 text-xs text-slate-400 hover:text-[#00E5FF] transition-colors"
              >
                <Mail className="w-3.5 h-3.5 text-[#00E5FF]" />
                <span className="truncate">{brandConfig.supportEmail}</span>
              </a>
            </div>
          </div>

          {/* Full Policy Body (8 Cols) */}
          <div className="lg:col-span-8 space-y-10">
            {t.sections.map((section) => (
              <article
                key={section.id}
                id={section.id}
                className="p-6 sm:p-8 rounded-3xl bg-[#090E24]/60 border border-white/10 space-y-4 scroll-mt-28"
              >
                <h2 className="text-xl sm:text-2xl font-bold text-white tracking-tight border-b border-white/10 pb-3">
                  {section.title}
                </h2>

                <div className="space-y-3 text-sm text-slate-300 leading-relaxed">
                  {section.content.map((paragraph, pIdx) => {
                    const urlRegex = /(https?:\/\/[^\s]+)/g;
                    if (urlRegex.test(paragraph)) {
                      const parts = paragraph.split(urlRegex);
                      return (
                        <p key={pIdx}>
                          {parts.map((part, idx) =>
                            urlRegex.test(part) ? (
                              <a
                                key={idx}
                                href={part}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-[#00E5FF] underline hover:text-[#00E5FF]/80 transition-colors font-mono text-xs sm:text-sm"
                              >
                                {part}
                              </a>
                            ) : (
                              part
                            )
                          )}
                        </p>
                      );
                    }
                    return <p key={pIdx}>{paragraph}</p>;
                  })}
                </div>
              </article>
            ))}
          </div>

        </div>

      </div>
    </div>
  );
};
