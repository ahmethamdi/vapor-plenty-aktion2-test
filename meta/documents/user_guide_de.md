# VaporAktion2Test – Aktion „Kaufe 6 ELFLIQ, erhalte 1 Geschenk gratis“

## Funktionsweise

- Liegen mindestens **N Aktions-Artikel** (Standard 6, Sorten beliebig gemischt) im Warenkorb, erscheint im Warenkorb eine Auswahl: **ELFX Pod Kit** (Farbe) oder **1 ELFLIQ** (Sorte/Stärke).
- Das Geschenk kommt als echte Position zum Normalpreis in den Warenkorb, das Plugin löst gleichzeitig den passenden **Aktionsgutschein** (Festbetrag = Preis des Geschenks) ein. Ergebnis: Geschenk 0,00 €.
- Max. 1 Geschenk pro Bestellung. Menge des ELFX-Geschenks immer 1.
- Fallen die Aktions-Artikel unter N, werden Geschenk und Gutschein automatisch entfernt.
- Liegt ein anderer Gutschein im Warenkorb, kann kein Geschenk gewählt werden („Aktion nicht mit anderen Gutscheinen kombinierbar“).
- Liegt einer der Aktions-Gutscheincodes ohne Berechtigung im Warenkorb, entfernt das Plugin ihn (für alle Kunden).
- Vor der Zahlung (ValidateCheckoutEvent) wird nochmals geprüft; wurde der Warenkorb angepasst, wird der Kunde informiert.

## Einstellungen

| Einstellung | Bedeutung |
|---|---|
| Aktion aktiv | Standard aus |
| Testmodus | Standard an: nur Test-Kunden / Test-Kundenklassen |
| Benötigte Menge | Standard 6 |
| Aktions-Artikel (Artikel-IDs / Variations-IDs) | zählen für die Aktion; auch als ELFLIQ-Geschenk wählbar |
| Geschenk ELFX (Artikel-IDs) | wählbare Geräte (nur aktiv, sichtbar, verkäuflich) |
| ELFLIQ als Geschenk | an/aus |
| Gutscheincode ELFX / ELFLIQ | Codes der im Backend angelegten Aktionsgutscheine |

## Container-Verknüpfungen

- „Aktion 2: Script (Pflicht)“ → `Ceres::Script.AfterScriptsLoaded`
- „Aktion 2: Geschenk-Box Warenkorb“ → `Ceres::Basket.BeforeBasketTotals`
- „Aktion 2: Geschenk-Box Warenkorb-Vorschau“ → `Ceres::BasketPreview.BeforeBasketTotals`
