# Release Notes für VaporAktion2Test

## v0.1.1 (2026-11-20)

### Hinzugefügt
- Test-Kunden per Kontakt-ID (`testKontaktIds`), zusätzlich zu Test-Kundenklassen. Eine eigene Kundenklasse kann Artikel im Shop ausblenden.

## v0.1.0 (2026-11-19)

### Hinzugefügt

- Erste Testversion: Liegt die Auslöser-Variation im Warenkorb, wird die Geschenk-Variation mit Menge 1 als echte Warenkorbposition hinzugefügt; ohne Auslöser wird sie entfernt.
- Nur für eingeloggte Kunden der konfigurierten Test-Kundenklassen. Standardmäßig deaktiviert.
- Preis-Modus „Variante“ (eigene Geschenk-Variation mit 0,00 €) und Experiment „givenPrice“.
- Protokollierung des Warenkorbpreises der Geschenk-Position unter **Daten » Log**.
