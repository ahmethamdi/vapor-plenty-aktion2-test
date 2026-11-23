# Release notes for VaporAktion2Test

## 0.4.0
- Aktion 1 entfernt (jetzt eigenes Plugin VaporAktion1). Nur noch Aktion 2.

## v0.3.0 (2026-11-20)

### Added
- **Promotion 1: "Buy 3 ELFA Pods, get 1 ELFA device free"** – independent of promotion 2: own config tab (`a1…`), REST route `/rest/vapor-aktion1/geschenk`, own boxes (cart, cart preview, optional item page), `aktion1.js` / `aktion1.css` (prefix `va1-`).
- Device as a real line (single colour, 7.49 €) + own fixed-amount promotion coupon. Counts single flavours (line type 10) and unsplit bundles of the collective items (type 11, elfbargermany).
- Not combinable with vouchers or promotion 2 (one coupon per cart).
- Default: promotion 1 **off**, test mode on, coupon code empty → no behaviour change after deployment.

### Unchanged
- Promotion 2.

## v0.2.5 (2026-11-20)

- If the CSRF token is missing on the page, the page is reloaded instead of sending a request without token (500 + logout).

## v0.2.4 (2026-11-20)

### Fixed
- Cache busting for JS/CSS (browser kept the old geschenk.js).

## v0.2.3 (2026-11-20)

### Fixed
- "Add gift" returned 500 / logout: CSRF header was sent twice via jQuery. Request now uses fetch; page reloads after selection.

## v0.2.2 (2026-11-20)

### Fixed
- Gift selection shows the flavour name (previously only "– 10 mg/ml").

## v0.2.1 (2026-11-20)

### Fixed
- Deployment failed: `new` is not allowed in Plenty plugins (Einstellungen::ausArray now uses pluginApp).

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
