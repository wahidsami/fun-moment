import React from 'react';
import { Language, translations } from '../data/translations';
import { brandConfig } from '../config/brandConfig';
import { FunMomentLogo } from './FunMomentLogo';
import { Apple, Play } from 'lucide-react';

interface CTASectionProps {
  lang: Language;
}

export const CTASection: React.FC<CTASectionProps> = ({ lang }) => {
  const t = translations[lang].appCta;

  const handleStoreClick = (url: string) => {
    window.open(url, '_blank', 'noopener,noreferrer');
  };

  return (
    <section id="download" className="py-24 bg-[#050814] relative overflow-hidden text-center">
      {/* Ambient Glow */}
      <div className="absolute inset-0 pointer-events-none -z-10 flex items-center justify-center">
        <div className="w-[450px] h-[450px] rounded-full bg-gradient-to-tr from-[#00E5FF]/20 via-[#FFA800]/15 to-[#FF007F]/20 blur-[130px]" />
      </div>

      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="p-8 sm:p-12 rounded-3xl bg-[#090E24]/90 border border-white/10 backdrop-blur-xl shadow-2xl space-y-6">
          
          <div className="flex justify-center">
            <FunMomentLogo size="md" withGlow />
          </div>

          <div className="max-w-xl mx-auto space-y-3">
            <h2 className="text-3xl sm:text-4xl font-black text-white tracking-tight">
              {t.title}
            </h2>
            <p className="text-sm sm:text-base text-slate-300">
              {t.subtitle}
            </p>
          </div>

          {/* Simple Store Buttons */}
          <div className="pt-4 flex flex-wrap items-center justify-center gap-4">
            <button
              onClick={() => handleStoreClick(brandConfig.appStoreUrl)}
              className="flex items-center gap-3 px-6 py-3.5 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/20 hover:border-[#00E5FF]/50 transition-all duration-300 shadow-md text-start cursor-pointer"
            >
              <Apple className="w-7 h-7 text-white shrink-0" />
              <div>
                <p className="text-[10px] text-slate-400 uppercase tracking-wider font-mono">
                  Download on the
                </p>
                <p className="text-sm font-bold text-white">
                  {t.appStore}
                </p>
              </div>
            </button>

            <button
              onClick={() => handleStoreClick(brandConfig.googlePlayUrl)}
              className="flex items-center gap-3 px-6 py-3.5 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/20 hover:border-[#FFA800]/50 transition-all duration-300 shadow-md text-start cursor-pointer"
            >
              <Play className="w-7 h-7 text-[#00E5FF] shrink-0 fill-current" />
              <div>
                <p className="text-[10px] text-slate-400 uppercase tracking-wider font-mono">
                  Get it on
                </p>
                <p className="text-sm font-bold text-white">
                  {t.googlePlay}
                </p>
              </div>
            </button>
          </div>

        </div>
      </div>
    </section>
  );
};
