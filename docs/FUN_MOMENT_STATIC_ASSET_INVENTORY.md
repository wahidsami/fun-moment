# FUN MOMENT Static Asset Inventory

Source folder inspected:

- `app_images/`

Copied into organized app asset folders under:

- `assets/images/app/`

## Inventory

| Asset | Dimensions | Detected Purpose | Integrated Location | Status |
| --- | --- | --- | --- | --- |
| `fun_moment_dj_equipment_01.png` | `1122x1402` | DJ equipment promotional imagery | Category fallback for DJ/equipment surfaces via `FMAssets.djEquipment` | Integrated |
| `fun_moment_dj_equipment_package_01.png` | `1122x1402` | DJ equipment package imagery | Category fallback for equipment-package category via `FMAssets.djEquipmentPackage` | Integrated |
| `fun_moment_dj_hero_01.png` | `1672x941` | Cinematic DJ hero/background | Login hero banner via `FMAssets.djHero` | Integrated |
| `fun_moment_dj_performance_01.png` | `1122x1402` | Live DJ performance imagery | Home promo carousel fallback via `FMAssets.djPerformance` | Integrated |
| `fun_moment_dj_riyadh_skyline_01.png` | `1672x941` | Riyadh DJ event/promo imagery | Home promo carousel fallback via `FMAssets.djRiyadhSkyline` | Integrated |
| `fun_moment_equipment_hero_01.png` | `1672x941` | Party equipment hero/background | Registration banner via `FMAssets.equipmentHero` | Integrated |
| `fun_moment_event_setup_01.png` | `1122x1402` | Event setup / production imagery | Category fallback for event-setup surfaces via `FMAssets.eventSetup` | Integrated |
| `fun_moment_generator_01.png` | `1122x1402` | Generator rental imagery | Category fallback for generator surfaces via `FMAssets.generator` | Integrated |
| `fun_moment_home_hero_01.png` | `1672x941` | Primary home hero/background | Home hero card and promo carousel fallback via `FMAssets.homeHero` | Integrated |
| `fun_moment_lighting_equipment_01.png` | `1122x1402` | Lighting equipment imagery | Category fallback for lighting surfaces via `FMAssets.lightingEquipment` | Integrated |
| `fun_moment_party_energy_01.png` | `1672x941` | Energetic party promo imagery | Home promo carousel fallback and login/register card via `FMAssets.partyEnergy` | Integrated |
| `fun_moment_party_lighting_01.png` | `1672x941` | Party lighting promo imagery | Home promo carousel fallback via `FMAssets.partyLighting` | Integrated |
| `fun_moment_private_party_01.png` | `1003x1568` | Booking / celebration imagery | Booking confirmation hero and payment success hero via `FMAssets.privateParty` | Integrated |
| `fun_moment_riyadh_event_01.png` | `1672x941` | Riyadh event / nightlife imagery | Home promo carousel fallback via `FMAssets.riyadhEvent` | Integrated |
| `fun_moment_sound_system_01.png` | `1122x1402` | Sound system rental imagery | Category fallback for sound-system surfaces via `FMAssets.soundSystem` | Integrated |

## Assets Moved / Copied

Copied from `app_images/` into grouped Flutter asset paths:

- `assets/images/app/hero/`
- `assets/images/app/dj/`
- `assets/images/app/promotions/`
- `assets/images/app/booking/`
- `assets/images/app/equipment/`

## Assets Registered

The copied assets live under `assets/images/`, which is already registered in `pubspec.yaml`.

No additional pubspec asset line was required because the existing `assets/images/` declaration covers the new subfolders.

## Assets Intentionally Unused

None.

Every inspected file in `app_images/` was assigned a legitimate UI slot.

## Notes

- All supplied assets are application-owned static visuals, not marketplace records.
- Real provider/service/category images from the backend remain dynamic and were not replaced.
- No mock DJs, providers, services, prices, ratings, or bookings were introduced.

