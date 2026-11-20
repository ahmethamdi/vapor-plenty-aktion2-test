# Release notes for VaporAktion2Test

## v0.1.1 (2026-11-20)

### Added
- Test customers by contact ID (`testKontaktIds`), in addition to test customer classes. A separate customer class can hide items in the shop.

## v0.1.0 (2026-11-19)

### Added

- First test version: if the trigger variation is in the basket, the gift variation is added with quantity 1 as a real basket item; without the trigger it is removed.
- Only for logged-in customers of the configured test customer classes. Disabled by default.
- Price mode "variation" (own gift variation with 0.00 €) and experiment "givenPrice".
- The basket price of the gift line is logged under **Data » Log**.
