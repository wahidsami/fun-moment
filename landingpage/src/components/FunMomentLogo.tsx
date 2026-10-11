import React from 'react';

interface FunMomentLogoProps {
  className?: string;
  size?: 'sm' | 'md' | 'lg' | 'xl' | 'custom';
  withGlow?: boolean;
  priority?: boolean;
}

export const FunMomentLogo: React.FC<FunMomentLogoProps> = ({
  className = '',
  size = 'md',
  withGlow = false,
}) => {
  // Size presets maintaining exact aspect ratio (1000 x 560 = ~16:9)
  const sizeClasses = {
    sm: 'h-8 md:h-9 w-auto',
    md: 'h-10 md:h-12 w-auto',
    lg: 'h-14 md:h-16 w-auto',
    xl: 'h-20 md:h-24 w-auto',
    custom: '',
  };

  return (
    <div
      className={`relative inline-flex items-center justify-center shrink-0 select-none ${className}`}
    >
      {/* Optional ambient backdrop glow */}
      {withGlow && (
        <div
          className="absolute inset-0 -z-10 blur-xl opacity-60 pointer-events-none transform scale-110"
          style={{
            background:
              'radial-gradient(ellipse at 50% 50%, rgba(0, 229, 255, 0.25) 0%, rgba(255, 168, 0, 0.2) 40%, rgba(255, 0, 127, 0.25) 80%, transparent 100%)',
          }}
        />
      )}

      {/* Authoritative Logo Asset - Crisp Vector & Fallback */}
      <picture className="flex items-center">
        <source srcSet="/logo.svg" type="image/svg+xml" />
        <img
          src="/logo.png"
          alt="FUN MOMENT — Official Logo"
          className={`object-contain block transition-transform duration-300 hover:scale-[1.02] ${
            size !== 'custom' ? sizeClasses[size] : ''
          }`}
          loading="eager"
          decoding="async"
        />
      </picture>
    </div>
  );
};
