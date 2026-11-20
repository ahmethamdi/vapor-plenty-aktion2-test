# Release Notes für VaporAktion2Test

## v0.2.0 (2026-11-20)

### Geändert
- Kompletter Umbau zur Aktion „6 Aktions-Artikel → 1 ELFLIQ oder 1 ELFX Pod Kit gratis“.
- Geschenk = echte Variation zum Normalpreis + Aktionsgutschein (Festbetrag). 0-€-Position/givenPrice entfernt (im Live-Test wirkungslos).

### Hinzugefügt
- Geschenk-Box im Warenkorb und in der Warenkorb-Vorschau (Container), REST-Route `/rest/vapor-aktion2/geschenk`.
- Testmodus, Aktions-Artikel (Artikel-/Variations-IDs), ELFX-Artikel, Gutscheincodes.
- Entfernt Aktions-Gutscheine ohne Berechtigung; Prüfung vor der Zahlung (ValidateCheckoutEvent).

## v0.1.1 (2026-11-20)

### Hinzugefügt
- Test-Kunden per Kontakt-ID (`testKontaktIds`), zusätzlich zu Test-Kundenklassen. Eine eigene Kundenklasse kann Artikel im Shop ausblenden.

## v0.1.0 (2026-11-19)

### Hinzugefügt

- Erste Testversion: Liegt die Auslöser-Variation im Warenkorb, wird die Geschenk-Variation mit Menge 1 als echte Warenkorbposition hinzugefügt; ohne Auslöser wird sie entfernt.
- Nur für eingeloggte Kunden der konfigurierten Test-Kundenklassen. Standardmäßig deaktiviert.
- Preis-Modus „Variante“ (eigene Geschenk-Variation mit 0,00 €) und Experiment „givenPrice“.
- Protokollierung des Warenkorbpreises der Geschenk-Position unter **Daten » Log**.
