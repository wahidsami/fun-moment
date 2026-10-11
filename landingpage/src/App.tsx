import React, { useState, useEffect } from 'react';
import { Language } from './data/translations';
import { Navbar } from './components/Navbar';
import { HeroSlider } from './components/HeroSlider';
import { ProductIntroSection } from './components/ProductIntroSection';
import { CTASection } from './components/CTASection';
import { Footer } from './components/Footer';
import { PrivacyPolicyPage } from './components/PrivacyPolicyPage';
import { SupportPage } from './components/SupportPage';
import { ArrowUp } from 'lucide-react';

export default function App() {
  // 1. Language state: Arabic is primary, English supported
  const [lang, setLang] = useState<Language>(() => {
    if (typeof window !== 'undefined') {
      const searchParams = new URLSearchParams(window.location.search);
      const urlLang = searchParams.get('lang');
      if (urlLang === 'en' || urlLang === 'ar') return urlLang;
      const stored = localStorage.getItem('fun_moment_lang');
      if (stored === 'en' || stored === 'ar') return stored;
    }
    return 'ar';
  });

  // 2. Strict 3-page routing: 'home' | 'privacy' | 'support'
  const [currentPage, setCurrentPage] = useState<string>(() => {
    if (typeof window !== 'undefined') {
      const pathname = window.location.pathname.toLowerCase();
      const searchParams = new URLSearchParams(window.location.search);
      const queryPage = searchParams.get('page')?.toLowerCase();

      if (pathname.includes('/privacy') || queryPage === 'privacy') return 'privacy';
      if (pathname.includes('/support') || queryPage === 'support') return 'support';
    }
    return 'home';
  });

  const [showScrollTop, setShowScrollTop] = useState(false);

  // Synchronize HTML lang, dir, and page title
  useEffect(() => {
    const isRtl = lang === 'ar';
    document.documentElement.lang = lang;
    document.documentElement.dir = isRtl ? 'rtl' : 'ltr';
    localStorage.setItem('fun_moment_lang', lang);

    if (currentPage === 'privacy') {
      document.title =
        lang === 'ar'
          ? 'سياسة الخصوصية | FUN MOMENT'
          : 'FUN MOMENT Privacy Policy';
    } else if (currentPage === 'support') {
      document.title =
        lang === 'ar'
          ? 'الدعم والمساعدة | FUN MOMENT'
          : 'FUN MOMENT Support & Help';
    } else {
      document.title =
        lang === 'ar'
          ? 'FUN MOMENT | لحظات ممتعة'
          : 'FUN MOMENT';
    }
  }, [lang, currentPage]);

  // Handle browser navigation
  useEffect(() => {
    const handlePopState = () => {
      const pathname = window.location.pathname.toLowerCase();
      const searchParams = new URLSearchParams(window.location.search);
      const queryPage = searchParams.get('page')?.toLowerCase();
      const queryLang = searchParams.get('lang');

      if (queryLang === 'en' || queryLang === 'ar') {
        setLang(queryLang);
      }

      if (pathname.includes('/privacy') || queryPage === 'privacy') {
        setCurrentPage('privacy');
      } else if (pathname.includes('/support') || queryPage === 'support') {
        setCurrentPage('support');
      } else {
        setCurrentPage('home');
      }
    };

    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, []);

  // Scroll to top indicator
  useEffect(() => {
    const handleScroll = () => {
      setShowScrollTop(window.scrollY > 400);
    };
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const handleLanguageChange = (newLang: Language) => {
    setLang(newLang);
    const url = new URL(window.location.href);
    url.searchParams.set('lang', newLang);
    window.history.pushState({}, '', url.toString());
  };

  const navigateTo = (page: string) => {
    const validPage = page === 'privacy' || page === 'support' ? page : 'home';
    setCurrentPage(validPage);
    window.scrollTo({ top: 0, behavior: 'smooth' });

    const url = new URL(window.location.href);
    if (validPage === 'home') {
      url.searchParams.delete('page');
      if (window.location.pathname !== '/') {
        window.history.pushState({}, '', `/${url.search}`);
        return;
      }
    } else {
      url.searchParams.set('page', validPage);
    }
    window.history.pushState({}, '', url.toString());
  };

  const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <div className="min-h-screen bg-[#050814] text-slate-100 flex flex-col selection:bg-[#00E5FF]/20 selection:text-[#00E5FF]">
      
      {/* Navbar: 3 pages (Home, Privacy, Support) + Language + Get App */}
      <Navbar
        lang={lang}
        onLanguageChange={handleLanguageChange}
        currentPage={currentPage}
        onNavigate={navigateTo}
      />

      {/* Main Content: Exactly 3 views */}
      <main className="flex-1">
        {currentPage === 'privacy' && (
          <PrivacyPolicyPage lang={lang} onBack={() => navigateTo('home')} />
        )}

        {currentPage === 'support' && (
          <SupportPage lang={lang} onBack={() => navigateTo('home')} />
        )}

        {currentPage === 'home' && (
          <>
            {/* B. Hero: Animated, premium, accurate */}
            <HeroSlider
              lang={lang}
              onNavigateSupport={() => navigateTo('support')}
            />

            {/* C. Short Product Introduction */}
            <ProductIntroSection lang={lang} />

            {/* D. Simple App Download CTA */}
            <CTASection lang={lang} />
          </>
        )}
      </main>

      {/* E. Simple Clean Footer */}
      <Footer
        lang={lang}
        onLanguageChange={handleLanguageChange}
        onNavigate={navigateTo}
      />

      {/* Scroll to Top */}
      {showScrollTop && (
        <button
          onClick={scrollToTop}
          className="fixed bottom-6 end-6 z-40 p-3 rounded-xl bg-[#090E24]/90 hover:bg-[#00E5FF] text-white hover:text-[#050814] border border-white/10 hover:border-[#00E5FF] shadow-xl backdrop-blur-md transition-all duration-300 cursor-pointer"
          aria-label={lang === 'ar' ? 'الرجوع للأعلى' : 'Scroll to top'}
        >
          <ArrowUp className="w-5 h-5" />
        </button>
      )}

    </div>
  );
}
