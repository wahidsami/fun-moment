import React from 'react';
import { Language, translations } from '../data/translations';
import { Compass, Sparkles, Heart } from 'lucide-react';

interface ProductIntroSectionProps {
  lang: Language;
}

export const ProductIntroSection: React.FC<ProductIntroSectionProps> = ({ lang }) => {
  const t = translations[lang].intro;

  const icons = [
    <Compass key="1" className="w-5 h-5 text-[#00E5FF]" />,
    <Sparkles key="2" className="w-5 h-5 text-[#FFA800]" />,
    <Heart key="3" className="w-5 h-5 text-[#FF007F]" />,
  ];

  const colorStyles = {
    cyan: 'border-[#00E5FF]/20 hover:border-[#00E5FF]/40 text-[#00E5FF]',
    amber: 'border-[#FFA800]/20 hover:border-[#FFA800]/40 text-[#FFA800]',
    pink: 'border-[#FF007F]/20 hover:border-[#FF007F]/40 text-[#FF007F]',
  };

  return (
    <section className="py-20 bg-[#070B1F] relative overflow-hidden text-start">
      <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Intro Block: Heading & Paragraph */}
        <div className="space-y-4 max-w-3xl">
          <p className="text-xs font-bold tracking-widest text-[#00E5FF] uppercase">
            {t.badge}
          </p>
          <h2 className="text-3xl sm:text-4xl font-black text-white tracking-tight">
            {t.heading}
          </h2>
          <p className="text-base sm:text-lg text-slate-300 leading-relaxed">
            {t.paragraph}
          </p>
        </div>

        {/* 3 Compact Benefit Statements */}
        <div className="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6">
          {t.benefits.map((benefit, idx) => {
            const badgeClass = colorStyles[benefit.color];

            return (
              <div
                key={idx}
                className={`p-6 rounded-2xl bg-[#090E24] border ${badgeClass} transition-all duration-300 space-y-3 shadow-sm`}
              >
                <div className="p-2.5 rounded-xl bg-white/5 border border-white/10 w-fit">
                  {icons[idx]}
                </div>
                <h3 className="text-base font-bold text-white">
                  {benefit.title}
                </h3>
                <p className="text-xs sm:text-sm text-slate-400 leading-relaxed">
                  {benefit.description}
                </p>
              </div>
            );
          })}
        </div>

      </div>
    </section>
  );
};
