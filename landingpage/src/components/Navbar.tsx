import React, { useState, useEffect } from 'react';
import { FunMomentLogo } from './FunMomentLogo';
import { Language, translations } from '../data/translations';
import { Globe, Menu, X, Smartphone } from 'lucide-react';

interface NavbarProps {
  lang: Language;
  onLanguageChange: (lang: Language) => void;
  currentPage: string;
  onNavigate: (page: string) => void;
}

export const Navbar: React.FC<NavbarProps> = ({
  lang,
  onLanguageChange,
  currentPage,
  onNavigate,
}) => {
  const [scrolled, setScrolled] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const t = translations[lang].nav;

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 20);
    };
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const handleNavClick = (pageId: string) => {
    setMobileMenuOpen(false);
    onNavigate(pageId);
  };

  const toggleLanguage = () => {
    onLanguageChange(lang === 'ar' ? 'en' : 'ar');
  };

  return (
    <header
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
        scrolled
          ? 'bg-[#050814]/90 backdrop-blur-md border-b border-white/10 shadow-lg shadow-black/40 py-2.5'
          : 'bg-transparent py-4'
      }`}
    >
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between gap-4">
          
          {/* Brand Anchor (Zone 1) */}
          <button
            onClick={() => handleNavClick('home')}
            className="flex items-center cursor-pointer transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00E5FF] rounded-lg p-1 text-start"
            aria-label="FUN MOMENT Home"
          >
            <FunMomentLogo size="sm" withGlow={scrolled} />
          </button>

          {/* Simple Clean Navigation Links (Zone 2) */}
          <nav
            className="hidden md:flex items-center gap-8 text-sm font-medium text-slate-300"
            aria-label="Main Navigation"
          >
            <button
              onClick={() => handleNavClick('home')}
              className={`hover:text-[#00E5FF] transition-colors cursor-pointer py-1 ${
                currentPage === 'home' ? 'text-white font-semibold border-b-2 border-[#00E5FF]' : ''
              }`}
            >
              {t.home}
            </button>
            <button
              onClick={() => handleNavClick('privacy')}
              className={`hover:text-[#00E5FF] transition-colors cursor-pointer py-1 ${
                currentPage === 'privacy' ? 'text-white font-semibold border-b-2 border-[#00E5FF]' : ''
              }`}
            >
              {t.privacy}
            </button>
            <button
              onClick={() => handleNavClick('support')}
              className={`hover:text-[#00E5FF] transition-colors cursor-pointer py-1 ${
                currentPage === 'support' ? 'text-white font-semibold border-b-2 border-[#00E5FF]' : ''
              }`}
            >
              {t.support}
            </button>
          </nav>

          {/* Actions (Zone 3): Language Switcher + Get App */}
          <div className="flex items-center gap-3">
            <button
              onClick={toggleLanguage}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 transition-all hover:border-[#00E5FF]/40 cursor-pointer focus-visible:ring-2 focus-visible:ring-[#00E5FF]"
              aria-label={`Switch language to ${t.switchLang}`}
              title={`Switch language to ${t.switchLang}`}
            >
              <Globe className="w-3.5 h-3.5 text-[#00E5FF]" />
              <span className="font-mono">{t.switchLang}</span>
            </button>

            <button
              onClick={() => {
                if (currentPage !== 'home') {
                  onNavigate('home');
                  setTimeout(() => {
                    document.getElementById('download')?.scrollIntoView({ behavior: 'smooth' });
                  }, 100);
                } else {
                  document.getElementById('download')?.scrollIntoView({ behavior: 'smooth' });
                }
              }}
              className="hidden sm:inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-[#050814] bg-gradient-to-r from-[#00E5FF] via-[#FFA800] to-[#FF007F] hover:brightness-110 rounded-lg transition-all duration-300 shadow-md shadow-[#00E5FF]/20 cursor-pointer uppercase tracking-wider"
            >
              <Smartphone className="w-3.5 h-3.5 text-[#050814]" />
              <span>{t.getApp}</span>
            </button>

            {/* Mobile Menu Button */}
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="md:hidden p-2 rounded-lg bg-white/5 text-slate-200 hover:text-white hover:bg-white/10 border border-white/10 focus-visible:ring-2 focus-visible:ring-[#00E5FF] cursor-pointer"
              aria-label={mobileMenuOpen ? 'Close menu' : 'Open menu'}
              aria-expanded={mobileMenuOpen}
            >
              {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
            </button>
          </div>
        </div>
      </div>

      {/* Mobile Drawer Menu */}
      {mobileMenuOpen && (
        <div className="md:hidden bg-[#070B1F]/98 border-b border-white/10 backdrop-blur-2xl px-5 py-6 mt-2 space-y-4 shadow-2xl">
          <nav className="flex flex-col space-y-2 text-base font-medium text-slate-200">
            <button
              onClick={() => handleNavClick('home')}
              className={`text-start py-2.5 px-3 rounded-lg hover:bg-white/5 hover:text-[#00E5FF] transition-colors ${
                currentPage === 'home' ? 'text-[#00E5FF] bg-white/5 font-semibold' : ''
              }`}
            >
              {t.home}
            </button>
            <button
              onClick={() => handleNavClick('privacy')}
              className={`text-start py-2.5 px-3 rounded-lg hover:bg-white/5 hover:text-[#00E5FF] transition-colors ${
                currentPage === 'privacy' ? 'text-[#00E5FF] bg-white/5 font-semibold' : ''
              }`}
            >
              {t.privacy}
            </button>
            <button
              onClick={() => handleNavClick('support')}
              className={`text-start py-2.5 px-3 rounded-lg hover:bg-white/5 hover:text-[#00E5FF] transition-colors ${
                currentPage === 'support' ? 'text-[#00E5FF] bg-white/5 font-semibold' : ''
              }`}
            >
              {t.support}
            </button>
          </nav>

          <div className="pt-4 border-t border-white/10 flex flex-col gap-3">
            <button
              onClick={toggleLanguage}
              className="flex items-center justify-between w-full px-4 py-2.5 text-sm rounded-lg bg-white/5 text-slate-200 border border-white/10"
            >
              <span className="flex items-center gap-2">
                <Globe className="w-4 h-4 text-[#00E5FF]" />
                <span>{lang === 'ar' ? 'English' : 'اللغة العربية'}</span>
              </span>
              <span className="text-xs font-mono text-[#00E5FF]">{t.switchLang}</span>
            </button>

            <button
              onClick={() => {
                setMobileMenuOpen(false);
                if (currentPage !== 'home') {
                  onNavigate('home');
                  setTimeout(() => {
                    document.getElementById('download')?.scrollIntoView({ behavior: 'smooth' });
                  }, 100);
                } else {
                  document.getElementById('download')?.scrollIntoView({ behavior: 'smooth' });
                }
              }}
              className="flex items-center justify-center gap-2 w-full py-3 text-sm font-bold text-[#050814] bg-gradient-to-r from-[#00E5FF] via-[#FFA800] to-[#FF007F] rounded-lg shadow-lg text-center cursor-pointer"
            >
              <Smartphone className="w-4 h-4" />
              <span>{t.getApp}</span>
            </button>
          </div>
        </div>
      )}
    </header>
  );
};
