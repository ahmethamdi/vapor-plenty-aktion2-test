# VaporAktion2Test

Testplugin, das eine einzige Frage beantwortet: Kann ein Plugin eine Variation mit 0,00 € als echte Warenkorbposition anlegen, und landet diese Position mit 0,00 € im Auftrag?

## Funktionsweise

Nach jeder Warenkorbänderung (Ereignis `AfterBasketChanged`) prüft das Plugin:

1. Ist **Aktion aktiv** eingeschaltet und ist der Kunde eingeloggt und in einer der **Test-Kundenklassen**? Wenn nicht, passiert nichts.
2. Liegt die **Auslöser-Variation** im Warenkorb, wird die **Geschenk-Variation** mit Menge 1 hinzugefügt.
3. Wird der Auslöser entfernt, wird auch das Geschenk entfernt.
4. Die Menge des Geschenks bleibt immer 1.

## Einstellungen

| Einstellung | Bedeutung |
|---|---|
| Aktion aktiv | Standard: aus |
| Test-Kundenklassen | IDs, kommagetrennt. Leer = Plugin tut nichts |
| Auslöser-Variations-ID | Variation, die das Geschenk auslöst |
| Geschenk-Variations-ID | Variation, die als Geschenk hinzugefügt wird (muss sich vom Auslöser unterscheiden) |
| Preis-Modus | „Variante“: Geschenk-Variation hat selbst 0,00 € als Verkaufspreis. „givenPrice“: Experiment, das Plugin übergibt `givenPrice = 0` |

## Protokoll

Einträge erscheinen unter **Daten » Log** mit der Integration **VaporAktion2Test** (Level *Report*, standardmäßig sichtbar).
