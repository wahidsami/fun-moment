# FUN MOMENT Image Asset Requirements

This document defines the image slots needed for the FUN MOMENT Mobile 2.0 redesign. It does not generate images. It only specifies the asset inventory for a future coherent asset pack.

## Required Image Slots

| Asset | Screen | Purpose | Aspect Ratio | Recommended Size | Style |
|---|---|---|---|---|---|
| Home Hero | Customer Home | Premium first impression banner | 16:9 | 1600x900 | Cinematic lifestyle, dark overlay, white-text safe |
| Home Category 1-8 | Customer Home / Discovery | Category tiles and chips | 1:1 | 800x800 | Clear subject, simple background, consistent framing |
| Featured 1-6 | Customer Home | Featured service imagery | 4:5 | 1200x1500 | Premium service imagery, visually rich but readable |
| Promotions 1-2 | Customer Home / Checkout | Promotional banners | 16:9 | 1600x900 | High contrast, magenta/cyan/orange accents, text-safe overlay |
| Discovery Service Gallery | Discovery / Service Detail | Service discovery cards and galleries | 4:5 and 1:1 | 1200x1500 / 1000x1000 | Clear service-specific visuals, not generic stock clutter |
| Provider Imagery | Provider profile / Service owner areas | Provider identity and trust surfaces | 1:1 and 4:5 | 1000x1000 / 1200x1500 | Professional portrait or business context, dark-safe |
| Service Gallery | Service Detail | Multi-image service gallery | 4:5 and 16:9 | 1200x1500 / 1600x900 | Premium, immersive, consistent lighting |
| Booking Confirmation Visual | Booking / Success | Confirmation and completion treatment | 4:5 | 1200x1500 | Calm, premium, celebratory without being noisy |
| Empty State Illustrations | Empty states | Friendly no-data visuals | 1:1 | 800x800 | Minimal, dark-compatible, brand-consistent |
| Authentication Backgrounds | Login / Registration | Cinematic brand background accents | 16:9 or 9:16 | 1600x900 / 1080x1920 | Dark, subtle glow, not distracting |

## Asset Organization Suggestion

If future image generation is organized into folders, the preferred structure is:

```text
assets/
  branding/
  images/
    hero/
    categories/
    services/
    providers/
    promotions/
    empty_states/
```

The root `logo.png` is the authoritative logo reference and should remain the source of truth for brand usage.

