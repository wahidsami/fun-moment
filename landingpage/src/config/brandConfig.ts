/**
 * FUN MOMENT — Central Configuration
 * Store-ready, simplified configuration without fake corporate metadata.
 */

export interface BrandConfig {
  appName: {
    en: string;
    ar: string;
  };
  canonicalDomain: string;
  appStoreUrl: string;
  googlePlayUrl: string;
  supportEmail: string;
  privacyLastUpdated: {
    en: string;
    ar: string;
  };
}

export const brandConfig: BrandConfig = {
  appName: {
    en: "FUN MOMENT",
    ar: "فَن مومنت (FUN MOMENT)",
  },
  canonicalDomain: "https://funmoments.unifinitylab.com/",
  // Store URLs - updated upon production release
  appStoreUrl: "https://apps.apple.com/",
  googlePlayUrl: "https://play.google.com/store/apps",
  // Real domain-aligned support email (no exposed placeholder text)
  supportEmail: "support@funmoments.unifinitylab.com",
  privacyLastUpdated: {
    en: "October 2026",
    ar: "أكتوبر ٢٠٢٦",
  },
};
