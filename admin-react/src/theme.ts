/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * FUN MOMENT Admin Theme Configuration
 * Defines design tokens for colors, spacing, radius, shadows, and typography
 * to ensure enterprise-grade UI consistency.
 */

export const themeTokens = {
  colors: {
    primary: {
      light: 'indigo-50',
      main: 'indigo-600',
      dark: 'indigo-700',
      hover: 'indigo-800',
      accent: 'indigo-500',
    },
    slate: {
      50: 'slate-50',
      100: 'slate-100',
      200: 'slate-200',
      300: 'slate-300',
      400: 'slate-400',
      500: 'slate-500',
      600: 'slate-600',
      700: 'slate-700',
      800: 'slate-800',
      900: 'slate-900',
    },
    feedback: {
      success: {
        bg: 'emerald-50',
        text: 'emerald-700',
        border: 'emerald-200',
        icon: 'emerald-500',
      },
      warning: {
        bg: 'amber-50',
        text: 'amber-700',
        border: 'amber-200',
        icon: 'amber-500',
      },
      danger: {
        bg: 'rose-50',
        text: 'rose-700',
        border: 'rose-200',
        icon: 'rose-500',
      },
      info: {
        bg: 'blue-50',
        text: 'blue-700',
        border: 'blue-200',
        icon: 'blue-500',
      }
    }
  },
  
  spacing: {
    xs: 'p-2',
    sm: 'p-4',
    md: 'p-6',
    lg: 'p-8',
    xl: 'p-12',
  },

  radius: {
    sm: 'rounded-md',
    md: 'rounded-lg',
    lg: 'rounded-xl',
    xl: 'rounded-2xl',
    full: 'rounded-full',
  },

  shadows: {
    sm: 'shadow-sm',
    md: 'shadow-md',
    lg: 'shadow-lg',
    xl: 'shadow-xl',
    glow: 'shadow-lg shadow-indigo-100 dark:shadow-none',
  },

  typography: {
    sans: 'font-sans',
    mono: 'font-mono',
    arabic: 'font-arabic',
    sizes: {
      xs: 'text-[10px] sm:text-xs',
      sm: 'text-xs sm:text-sm',
      base: 'text-sm sm:text-base',
      lg: 'text-base sm:text-lg font-bold',
      xl: 'text-lg sm:text-xl font-bold tracking-tight',
      display: 'text-xl sm:text-2xl font-black tracking-tight',
    }
  }
};

export type ThemeTokens = typeof themeTokens;
