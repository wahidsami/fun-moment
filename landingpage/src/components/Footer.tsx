import React from 'react';
import { Language, translations } from '../data/translations';
import { FunMomentLogo } from './FunMomentLogo';
import { Globe, ShieldCheck, Headphones } from 'lucide-react';

interface FooterProps {
  lang: Language;
  onLanguageChange: (lang: Language) => void;
  onNavigate: (page: string) => void;
}

export const Footer: React.FC<FooterProps> = ({
  lang,
  onLanguageChange,
  onNavigate,
}) => {
  const t = translations[lang];
  const currentYear = new Date().getFullYear();

  return (
    <footer className="bg-[#03050E] border-t border-white/10 text-slate-400 text-sm py-12 text-start">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Main Footer Row: Brand, Primary Links, and Language Switcher */}
        <div className="flex flex-col md:flex-row items-center justify-between gap-6 pb-8 border-b border-white/10">
          
          {/* Official FUN MOMENT Logo */}
          <button
            onClick={() => onNavigate('home')}
            className="cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00E5FF] rounded-lg p-1"
            aria-label="FUN MOMENT Home"
          >
            <FunMomentLogo size="sm" />
          </button>

          {/* Clean Navigation Links: Only Privacy Policy and Support & Help */}
          <div className="flex items-center gap-8 text-xs sm:text-sm">
            <button
              onClick={() => onNavigate('privacy')}
              className="hover:text-[#00E5FF] transition-colors cursor-pointer flex items-center gap-1.5"
            >
              <ShieldCheck className="w-3.5 h-3.5 text-[#00E5FF]" />
              <span>{t.nav.privacy}</span>
            </button>

            <button
              onClick={() => onNavigate('support')}
              className="hover:text-[#00E5FF] transition-colors cursor-pointer flex items-center gap-1.5"
            >
              <Headphones className="w-3.5 h-3.5 text-[#FFA800]" />
              <span>{t.nav.support}</span>
            </button>
          </div>

          {/* Language Switcher */}
          <button
            onClick={() => onLanguageChange(lang === 'ar' ? 'en' : 'ar')}
            className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 transition-colors cursor-pointer"
          >
            <Globe className="w-3.5 h-3.5 text-[#00E5FF]" />
            <span className="font-mono">{t.nav.switchLang}</span>
          </button>
        </div>

        {/* Copyright & Tagline */}
        <div className="pt-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
          <p>© {currentYear} {t.footer.copyright}</p>
          <p>{t.footer.allRightsReserved}</p>
        </div>

      </div>
    </footer>
  );
};
