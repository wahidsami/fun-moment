import React, { useState, useEffect, useRef } from 'react';
import { Language, translations } from '../data/translations';
import { FunMomentLogo } from './FunMomentLogo';
import {
  ChevronLeft,
  ChevronRight,
  Sparkles,
  Smartphone,
  Play,
  Pause,
  Headphones,
} from 'lucide-react';

interface HeroSliderProps {
  lang: Language;
  onNavigateSupport: () => void;
}

export const HeroSlider: React.FC<HeroSliderProps> = ({ lang, onNavigateSupport }) => {
  const t = translations[lang].hero;
  const isRtl = lang === 'ar';
  const [currentSlide, setCurrentSlide] = useState(0);
  const [isPlaying, setIsPlaying] = useState(true);
  const [mousePos, setMousePos] = useState({ x: 0, y: 0 });
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!isPlaying) return;
    const interval = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % t.slides.length);
    }, 6000);
    return () => clearInterval(interval);
  }, [isPlaying, t.slides.length]);

  const handleMouseMove = (e: React.MouseEvent<HTMLDivElement>) => {
    if (!containerRef.current) return;
    const rect = containerRef.current.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width - 0.5;
    const y = (e.clientY - rect.top) / rect.height - 0.5;
    setMousePos({ x, y });
  };

  const nextSlide = () => {
    setCurrentSlide((prev) => (prev + 1) % t.slides.length);
  };

  const prevSlide = () => {
    setCurrentSlide((prev) => (prev - 1 + t.slides.length) % t.slides.length);
  };

  const activeSlide = t.slides[currentSlide];

  const colorStyles = {
    cyan: {
      accent: '#00E5FF',
      glow: 'rgba(0, 229, 255, 0.35)',
      pill: 'bg-[#00E5FF]/10 text-[#00E5FF] border-[#00E5FF]/30',
    },
    amber: {
      accent: '#FFA800',
      glow: 'rgba(255, 168, 0, 0.35)',
      pill: 'bg-[#FFA800]/10 text-[#FFA800] border-[#FFA800]/30',
    },
    pink: {
      accent: '#FF007F',
      glow: 'rgba(255, 0, 127, 0.35)',
      pill: 'bg-[#FF007F]/10 text-[#FF007F] border-[#FF007F]/30',
    },
  }[activeSlide.accentColor];

  return (
    <section
      ref={containerRef}
      onMouseMove={handleMouseMove}
      className="relative min-h-[85vh] lg:min-h-[92vh] flex items-center justify-center pt-28 pb-16 overflow-hidden bg-[#050814]"
      aria-label="Hero Showcase"
    >
      {/* Dynamic Background Atmosphere */}
      <div className="absolute inset-0 pointer-events-none -z-10 overflow-hidden">
        <div
          className="absolute inset-0 opacity-[0.03]"
          style={{
            backgroundImage: `radial-gradient(circle at 1px 1px, #FFFFFF 1px, transparent 0)`,
            backgroundSize: '40px 40px',
          }}
        />

        {/* Ambient Neon Blobs matching logo colors */}
        <div
          className="absolute top-1/4 -start-48 w-96 h-96 rounded-full blur-[140px] opacity-35 transition-all duration-1000"
          style={{
            backgroundColor: activeSlide.accentColor === 'cyan' ? '#00E5FF' : '#0077D4',
            transform: `translate(${mousePos.x * 25}px, ${mousePos.y * 25}px)`,
          }}
        />
        <div
          className="absolute top-1/3 -end-48 w-96 h-96 rounded-full blur-[140px] opacity-30 transition-all duration-1000"
          style={{
            backgroundColor: activeSlide.accentColor === 'pink' ? '#FF007F' : '#FFA800',
            transform: `translate(${-mousePos.x * 25}px, ${-mousePos.y * 25}px)`,
          }}
        />
        <div className="absolute bottom-0 inset-x-0 h-32 bg-gradient-to-t from-[#050814] to-transparent" />
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
          
          {/* Main Editorial Text Column (7 Cols) */}
          <div className="lg:col-span-7 flex flex-col justify-center space-y-6 z-10 text-start">
            
            {/* Kicker & Slide Category Tag */}
            <div className="flex flex-wrap items-center gap-3 text-xs font-semibold tracking-wider">
              <span className="flex items-center gap-1.5 text-slate-400">
                <Sparkles className="w-3.5 h-3.5 text-[#00E5FF]" />
                <span>{t.kicker}</span>
              </span>
              <span className="text-slate-600">·</span>
              <span
                className={`px-3 py-1 rounded-full text-xs font-bold border transition-colors duration-500 ${colorStyles.pill}`}
              >
                {activeSlide.tag}
              </span>
            </div>

            {/* Dominant Headline */}
            <div className="relative">
              <h1
                key={`title-${currentSlide}`}
                className="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white leading-[1.18] text-balance transition-all duration-700 animate-in fade-in slide-in-from-bottom-2"
              >
                {activeSlide.title}
              </h1>

              {/* Glowing horizon line */}
              <div className="w-32 h-1 mt-4 rounded-full bg-gradient-to-r from-[#00E5FF] via-[#FFA800] to-[#FF007F] opacity-90" />
            </div>

            {/* Supporting Description */}
            <p
              key={`subtitle-${currentSlide}`}
              className="text-base sm:text-lg text-slate-300 max-w-2xl leading-relaxed text-balance transition-all duration-700 delay-100 animate-in fade-in slide-in-from-bottom-2"
            >
              {activeSlide.subtitle}
            </p>

            {/* CTAs */}
            <div className="pt-3 flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
              <button
                onClick={() => {
                  document.getElementById('download')?.scrollIntoView({ behavior: 'smooth' });
                }}
                className="inline-flex items-center justify-center gap-2.5 px-7 py-3.5 rounded-xl font-bold text-sm text-[#050814] bg-gradient-to-r from-[#00E5FF] via-[#FFA800] to-[#FF007F] hover:brightness-110 transition-all duration-300 shadow-lg shadow-[#00E5FF]/20 cursor-pointer text-center"
              >
                <Smartphone className="w-4 h-4 text-[#050814]" />
                <span>{t.primaryCta}</span>
              </button>

              <button
                onClick={onNavigateSupport}
                className="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl font-semibold text-sm text-slate-200 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 transition-all duration-300 cursor-pointer"
              >
                <Headphones className="w-4 h-4 text-[#00E5FF]" />
                <span>{t.secondaryCta}</span>
              </button>
            </div>

            {/* Available Platform Statement */}
            <div className="pt-2 flex items-center gap-2 text-xs text-slate-400">
              <span className="w-2 h-2 rounded-full bg-[#00E5FF]" />
              <span>{t.availableOn}</span>
            </div>
          </div>

          {/* Cinematic Interactive Visual Card (5 Cols) */}
          <div className="lg:col-span-5 relative flex items-center justify-center">
            
            {/* Ambient Backdrop Glowing Card Frame */}
            <div
              className="relative w-full max-w-md rounded-3xl p-8 bg-[#0B1129]/80 border border-white/10 backdrop-blur-xl shadow-2xl transition-all duration-700"
              style={{
                boxShadow: `0 20px 60px -15px ${colorStyles.glow}`,
                transform: `perspective(1000px) rotateX(${mousePos.y * -5}deg) rotateY(${
                  mousePos.x * 5
                }deg)`,
              }}
            >
              {/* Brand Logo Presentation */}
              <div className="flex flex-col items-center justify-center py-6 text-center space-y-5">
                <FunMomentLogo size="lg" withGlow />

                <div className="flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs text-slate-300">
                  <span className="w-1.5 h-1.5 rounded-full bg-[#00E5FF] animate-pulse" />
                  <span className="font-mono">{activeSlide.tag}</span>
                </div>

                <p className="text-xs text-slate-400 max-w-xs leading-relaxed">
                  {activeSlide.subtitle}
                </p>
              </div>

              {/* Soundwave Equalizer Pulse Indicator */}
              <div className="pt-4 border-t border-white/10 flex items-center justify-center gap-1.5 h-6">
                <span className="w-1 h-3 rounded-full bg-[#00E5FF] animate-eq-1" />
                <span className="w-1 h-5 rounded-full bg-[#FFA800] animate-eq-2" />
                <span className="w-1 h-4 rounded-full bg-[#FF007F] animate-eq-3" />
                <span className="w-1 h-2 rounded-full bg-[#00E5FF] animate-eq-4" />
              </div>
            </div>

            {/* Slide Navigation Controls */}
            <div className="absolute -bottom-10 inset-x-0 flex items-center justify-center gap-3">
              <button
                onClick={() => setIsPlaying(!isPlaying)}
                className="p-1.5 rounded-full bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white transition-colors cursor-pointer border border-white/10"
                aria-label={isPlaying ? 'Pause slider' : 'Resume slider'}
              >
                {isPlaying ? <Pause className="w-3.5 h-3.5" /> : <Play className="w-3.5 h-3.5" />}
              </button>

              <button
                onClick={prevSlide}
                className="p-1.5 rounded-full bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white transition-colors cursor-pointer border border-white/10"
                aria-label="Previous slide"
              >
                {isRtl ? <ChevronRight className="w-4 h-4" /> : <ChevronLeft className="w-4 h-4" />}
              </button>

              <div className="flex items-center gap-1.5">
                {t.slides.map((_, idx) => (
                  <button
                    key={idx}
                    onClick={() => setCurrentSlide(idx)}
                    className={`h-1.5 rounded-full transition-all duration-300 cursor-pointer ${
                      currentSlide === idx
                        ? 'w-7 bg-gradient-to-r from-[#00E5FF] to-[#FF007F]'
                        : 'w-2 bg-white/20 hover:bg-white/40'
                    }`}
                    aria-label={`Go to slide ${idx + 1}`}
                  />
                ))}
              </div>

              <button
                onClick={nextSlide}
                className="p-1.5 rounded-full bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white transition-colors cursor-pointer border border-white/10"
                aria-label="Next slide"
              >
                {isRtl ? <ChevronLeft className="w-4 h-4" /> : <ChevronRight className="w-4 h-4" />}
              </button>
            </div>
          </div>

        </div>
      </div>
    </section>
  );
};
