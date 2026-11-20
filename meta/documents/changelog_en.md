# Release notes for VaporAktion2Test

## v0.2.0 (2026-11-20)

### Changed
- Rebuilt as promotion "6 promotion items → 1 ELFLIQ or 1 ELFX Pod Kit free" (gift at normal price + fixed-amount promotion coupon).

### Added
- Gift box in cart and cart preview, REST route `/rest/vapor-aktion2/geschenk`, test mode, coupon cleanup, checkout validation.

## v0.1.1 (2026-11-20)

### Added
- Test customers by contact ID (`testKontaktIds`), in addition to test customer classes. A separate customer class can hide items in the shop.

## v0.1.0 (2026-11-19)

### Added

- First test version: if the trigger variation is in the basket, the gift variation is added with quantity 1 as a real basket item; without the trigger it is removed.
- Only for logged-in customers of the configured test customer classes. Disabled by default.
- Price mode "variation" (own gift variation with 0.00 €) and experiment "givenPrice".
- The basket price of the gift line is logged under **Data » Log**.
