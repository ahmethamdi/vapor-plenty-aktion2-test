# Release Notes für VaporAktion2Test

## 0.4.2
- Ungeteilte Pakete (itemType 11) zählen mit: elfbargermany verkauft ELFLIQ als Sammelartikel 14158 (ELFLIQ-V).

## 0.4.1
- Gleicher Code wie 0.2.5 (nur Aktion 2). Versionsnummer erhöht, damit Plenty nach 0.4.0 wieder baut.

## v0.2.5 (2026-11-20)

- Fehlt das CSRF-Token auf der Seite, wird die Seite neu geladen statt eine Anfrage ohne Token zu senden (500 + Abmeldung).

## v0.2.4 (2026-11-20)

### Behoben
- Cache-Busting für JS/CSS (Browser lud die alte geschenk.js).

## v0.2.3 (2026-11-20)

### Behoben
- „Gratis hinzufügen“ ergab 500 / Abmeldung: CSRF-Header wurde über jQuery doppelt gesendet. Anfrage jetzt per fetch; nach Auswahl wird die Seite neu geladen.

## v0.2.2 (2026-11-20)

### Behoben
- Geschenk-Auswahl zeigt den Sortennamen (vorher nur „– 10 mg/ml“).

## v0.2.1 (2026-11-20)

### Behoben
- Bereitstellung schlug fehl: `new` ist in Plenty-Plugins nicht erlaubt (Einstellungen::ausArray nutzt jetzt pluginApp).

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
