/*
 * VaporAktion2 – Gratis-Geschenk-Box im Warenkorb (plentyShop LTS 5).
 *
 * - Füllt alle [data-va2-box] (Warenkorb-Seite + Warenkorb-Vorschau).
 * - Lädt den Status nur, wenn eine Box sichtbar ist (IntersectionObserver).
 * - Lädt neu nach dem plentyShop-Ereignis "afterBasketChanged" (BasketModule.setBasket).
 * - Schreibt über jQuery ($.ajax): plentyShop wertet in ajaxComplete die "events" der Antwort aus
 *   (AfterBasketChanged → Vuex-Warenkorb wird aktualisiert) und setzt den X-CSRF-TOKEN.
 */
(function () {
    "use strict";

    if (window.__va2Geladen) {
        return;
    }
    window.__va2Geladen = true;

    var URL = "/rest/vapor-aktion2/geschenk";
    var zustand = null;        // letzter Status vom Server
    var veraltet = true;       // Status muss neu geladen werden
    var laedt = false;
    var nochmal = false;       // während des Ladens kam ein neues Ereignis
    var auswahlOffen = false;  // "Ändern" geklickt
    var gewaehlteGruppe = "";  // elfx | elfliq
    var meldung = "";
    var meldungOk = false;
    var sendet = false;
    var sichtbare = [];        // aktuell sichtbare Boxen
    var beobachter = null;
    var zaehler = 0;

    function esc(text) {
        return String(text === undefined || text === null ? "" : text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function csrf() {
        var el = document.getElementById("csrf-token");
        return el ? el.value : "";
    }

    function anfrage(methode, daten, fertig) {
        // 20.11.: Ohne #csrf-token antwortet Plenty mit 500 "CSRF token mismatch" und meldet den Kunden ab.
        // Dann nicht senden, sondern die Seite neu laden – danach ist das Token da.
        if (methode !== "GET" && !csrf()) {
            window.location.reload();
            return;
        }
        var kopf = { "X-CSRF-TOKEN": csrf(), "X-Requested-With": "XMLHttpRequest", "Accept": "application/json", "Accept-Language": (window.App && window.App.language) || "de" };
        // 20.11.: NICHT über jQuery – plentyShop setzt X-CSRF-TOKEN per ajaxSetup selbst, unser Header kam doppelt
        // ("t, t") → Token-Fehler → 500 + "aus Sicherheitsgründen abgemeldet". fetch sendet den Header genau einmal.
        var opts = { method: methode, credentials: "same-origin", headers: kopf };
        var url = URL;
        if (methode === "GET") {
            url += "?_=" + Date.now();
        } else {
            opts.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            opts.body = Object.keys(daten || {}).map(function (k) {
                return encodeURIComponent(k) + "=" + encodeURIComponent(daten[k]);
            }).join("&");
        }
        window.fetch(url, opts).then(function (r) {
            if (!r.ok) { throw new Error("HTTP " + r.status); }
            return r.json();
        }).then(function (antwort) {
            fertig(null, antwort && antwort.data ? antwort.data : null);
            if (methode !== "GET" && antwort && antwort.data && antwort.data.ok) {
                // Warenkorb (Positionen, Gutschein, Summen) sicher aktualisieren
                window.setTimeout(function () { window.location.reload(); }, 600);
            }
        })["catch"](function () { fertig(true, null); });
    }

    function laden() {
        if (laedt) {
            nochmal = true;
            return;
        }
        laedt = true;
        veraltet = false;
        anfrage("GET", null, function (fehler, daten) {
            laedt = false;
            if (!fehler && daten) {
                zustand = daten;
            }
            alleZeichnen();
            if (nochmal) {
                nochmal = false;
                laden();
            }
        });
    }

    function senden(methode, daten) {
        if (sendet) {
            return;
        }
        sendet = true;
        meldung = "";
        alleZeichnen();
        anfrage(methode, daten, function (fehler, antwort) {
            sendet = false;
            if (antwort) {
                zustand = antwort;
                meldung = antwort.meldung || "";
                meldungOk = !!antwort.ok;
                if (antwort.ok) {
                    auswahlOffen = false;
                }
            } else {
                meldung = (zustand && zustand.texte && zustand.texte.fehlerAllgemein) || "Fehler";
                meldungOk = false;
            }
            alleZeichnen();
        });
    }

    // ---------------------------------------------------------------- Darstellung

    function optionenListe(gruppe) {
        if (!zustand || !zustand.optionen || !zustand.optionen[gruppe]) {
            return [];
        }
        return zustand.optionen[gruppe];
    }

    function svgGeschenk() {
        return '<svg class="va2-icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">' +
            '<path fill="currentColor" d="M20 7h-2.2A3 3 0 0 0 12 3.8 3 3 0 0 0 6.2 7H4a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h1v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8h1a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1Zm-5-2a1 1 0 1 1 0 2h-2V6a1 1 0 0 1 2-1ZM9 5a1 1 0 0 1 2 1v1H9a1 1 0 1 1 0-2ZM5 9h6v1H5V9Zm2 3h4v7H7v-7Zm10 7h-4v-7h4v7Zm2-9h-6V9h6v1Z"/>' +
            '</svg>';
    }

    function html(uid) {
        var z = zustand;
        var t = z.texte || {};
        var teile = [];

        teile.push('<div class="va2-kopf">' + svgGeschenk() + '<strong class="va2-titel">' + esc(t.titel) + "</strong></div>");

        if (z.fremderGutschein) {
            teile.push('<p class="va2-text va2-warnung">' + esc(t.fremderGutschein) + "</p>");
        } else if (z.geschenk && !auswahlOffen) {
            var g = z.geschenk;
            teile.push(
                '<div class="va2-geschenk">' +
                    '<span class="va2-label">' + esc(t.deinGeschenk) + "</span> " +
                    '<span class="va2-name">' + esc(g.name || (g.gruppe === "elfx" ? t.gruppeElfx : t.gruppeElfliq)) + "</span> " +
                    '<span class="va2-gratis">' + esc(t.gratis) + "</span>" +
                    (g.preis ? ' <span class="va2-statt">' + esc(t.statt) + " " + esc(g.preis) + "</span>" : "") +
                "</div>" +
                '<div class="va2-knoepfe">' +
                    '<button type="button" class="btn btn-sm btn-outline-primary va2-knopf" data-va2-aktion="aendern"' + (sendet ? " disabled" : "") + ">" + esc(t.aendern) + "</button>" +
                    '<button type="button" class="btn btn-sm btn-link va2-knopf" data-va2-aktion="entfernen"' + (sendet ? " disabled" : "") + ">" + esc(t.entfernen) + "</button>" +
                "</div>"
            );
        } else if (!z.berechtigt) {
            var prozent = z.benoetigt > 0 ? Math.min(100, Math.round((z.bezahlt / z.benoetigt) * 100)) : 0;
            teile.push(
                '<p class="va2-text">' + esc(t.nochFehlend) + "</p>" +
                '<div class="va2-balken" role="progressbar" aria-valuemin="0" aria-valuemax="' + esc(z.benoetigt) + '" aria-valuenow="' + esc(z.bezahlt) + '">' +
                    '<span style="width:' + prozent + '%"></span>' +
                "</div>" +
                '<p class="va2-klein">' + esc(t.fortschritt) + "</p>"
            );
        } else {
            teile.push(auswahlHtml(uid, t));
        }

        if (meldung) {
            teile.push('<p class="va2-meldung ' + (meldungOk ? "va2-ok" : "va2-fehler") + '" role="status">' + esc(meldung) + "</p>");
        }

        teile.push('<p class="va2-klein va2-hinweis">' + esc(t.hinweis) + "</p>");

        return '<div class="va2' + (sendet ? " va2-sendet" : "") + '">' + teile.join("") + "</div>";
    }

    function auswahlHtml(uid, t) {
        var gruppen = [];
        if (optionenListe("elfx").length) { gruppen.push("elfx"); }
        if (optionenListe("elfliq").length) { gruppen.push("elfliq"); }

        if (!gruppen.length) {
            return '<p class="va2-text">' + esc(t.keineOptionen) + "</p>";
        }

        if (gruppen.indexOf(gewaehlteGruppe) === -1) {
            gewaehlteGruppe = zustand.geschenk && gruppen.indexOf(zustand.geschenk.gruppe) !== -1 ? zustand.geschenk.gruppe : gruppen[0];
        }

        var teile = ['<p class="va2-text">' + esc(t.berechtigt) + "</p>"];

        if (gruppen.length > 1) {
            teile.push('<div class="va2-gruppen" role="radiogroup">');
            gruppen.forEach(function (g) {
                var id = "va2-g-" + uid + "-" + g;
                teile.push(
                    '<label class="va2-gruppe' + (g === gewaehlteGruppe ? " va2-aktiv" : "") + '" for="' + id + '">' +
                        '<input type="radio" id="' + id + '" name="va2-gruppe-' + uid + '" value="' + g + '" data-va2-gruppe' + (g === gewaehlteGruppe ? " checked" : "") + "> " +
                        esc(g === "elfx" ? t.gruppeElfx : t.gruppeElfliq) +
                    "</label>"
                );
            });
            teile.push("</div>");
        }

        var aktuell = zustand.geschenk ? zustand.geschenk.variationId : 0;
        var selectId = "va2-s-" + uid;
        teile.push('<label class="sr-only" for="' + selectId + '">' + esc(gewaehlteGruppe === "elfx" ? t.gruppeElfx : t.gruppeElfliq) + "</label>");
        teile.push('<select id="' + selectId + '" class="custom-select va2-select" data-va2-select>');
        teile.push('<option value="">' + esc(t.auswahlPlatzhalter) + "</option>");
        optionenListe(gewaehlteGruppe).forEach(function (o) {
            teile.push(
                '<option value="' + esc(o.variationId) + '"' + (o.variationId === aktuell ? " selected" : "") + ">" +
                    esc(o.name) + (o.preis ? " (" + esc(t.statt) + " " + esc(o.preis) + ")" : "") +
                "</option>"
            );
        });
        teile.push("</select>");

        teile.push('<div class="va2-knoepfe">');
        teile.push('<button type="button" class="btn btn-sm btn-primary va2-knopf" data-va2-aktion="hinzufuegen"' + (sendet ? " disabled" : "") + ">" + esc(sendet ? t.laden : t.hinzufuegen) + "</button>");
        if (zustand.geschenk) {
            teile.push('<button type="button" class="btn btn-sm btn-link va2-knopf" data-va2-aktion="abbrechen">' + esc(t.abbrechen) + "</button>");
        }
        teile.push("</div>");

        return teile.join("");
    }

    function zeichnen(box) {
        if (!zustand || !zustand.sichtbar) {
            // Nicht verstecken (display:none): sonst meldet der IntersectionObserver die Box nie wieder als sichtbar
            box.innerHTML = "";
            return;
        }
        // Auswahl im Select beim Neuzeichnen erhalten
        var select = box.querySelector("[data-va2-select]");
        var vorher = select ? select.value : "";

        box.innerHTML = html(box.getAttribute("data-va2-uid"));

        if (vorher) {
            var neu = box.querySelector("[data-va2-select]");
            if (neu && neu.querySelector('option[value="' + vorher.replace(/[^0-9]/g, "") + '"]')) {
                neu.value = vorher;
            }
        }
    }

    function alleZeichnen() {
        var boxen = document.querySelectorAll("[data-va2-box][data-va2-uid]");
        for (var i = 0; i < boxen.length; i++) {
            zeichnen(boxen[i]);
        }
    }

    // ---------------------------------------------------------------- Ereignisse

    function klick(ereignis) {
        var knopf = ereignis.target.closest ? ereignis.target.closest("[data-va2-aktion]") : null;
        if (!knopf || sendet) {
            return;
        }
        var box = knopf.closest("[data-va2-box]");
        var aktion = knopf.getAttribute("data-va2-aktion");

        if (aktion === "aendern") {
            auswahlOffen = true;
            meldung = "";
            alleZeichnen();
        } else if (aktion === "abbrechen") {
            auswahlOffen = false;
            meldung = "";
            alleZeichnen();
        } else if (aktion === "entfernen") {
            senden("DELETE", {});
        } else if (aktion === "hinzufuegen") {
            var select = box ? box.querySelector("[data-va2-select]") : null;
            var wert = select ? parseInt(select.value, 10) : 0;
            if (!wert) {
                if (select) { select.focus(); }
                return;
            }
            senden("POST", { variationId: wert });
        }
    }

    function aenderung(ereignis) {
        var ziel = ereignis.target;
        if (ziel && ziel.hasAttribute && ziel.hasAttribute("data-va2-gruppe")) {
            gewaehlteGruppe = ziel.value;
            alleZeichnen();
        }
    }

    function sichtbarkeit(eintraege) {
        eintraege.forEach(function (e) {
            var i = sichtbare.indexOf(e.target);
            if (e.isIntersecting && i === -1) {
                sichtbare.push(e.target);
            } else if (!e.isIntersecting && i !== -1) {
                sichtbare.splice(i, 1);
            }
        });
        if (sichtbare.length && veraltet) {
            laden();
        }
    }

    function boxenSuchen() {
        var boxen = document.querySelectorAll("[data-va2-box]:not([data-va2-uid])");
        for (var i = 0; i < boxen.length; i++) {
            var box = boxen[i];
            zaehler += 1;
            box.setAttribute("data-va2-uid", String(zaehler));
            box.addEventListener("click", klick);
            box.addEventListener("change", aenderung);
            if (zustand) {
                zeichnen(box);
            }
            if (beobachter) {
                beobachter.observe(box);
            } else if (veraltet) {
                laden();
            }
        }
    }

    var sucheGeplant = null;
    function sucheEinplanen() {
        if (sucheGeplant) {
            return;
        }
        sucheGeplant = window.setTimeout(function () {
            sucheGeplant = null;
            boxenSuchen();
        }, 250);
    }

    var ladenGeplant = null;
    function warenkorbGeaendert() {
        veraltet = true;
        if (ladenGeplant) {
            window.clearTimeout(ladenGeplant);
        }
        ladenGeplant = window.setTimeout(function () {
            ladenGeplant = null;
            if (!beobachter || sichtbare.length) {
                laden();
            }
        }, 300);
    }

    function start() {
        if ("IntersectionObserver" in window) {
            beobachter = new window.IntersectionObserver(sichtbarkeit);
        }
        boxenSuchen();

        // Boxen, die Vue später einfügt (Warenkorb-Vorschau, Neuaufbau der Warenkorb-Seite)
        if ("MutationObserver" in window) {
            new window.MutationObserver(sucheEinplanen).observe(document.body, { childList: true, subtree: true });
        }

        // plentyShop LTS: BasketModule.setBasket → document "afterBasketChanged"
        document.addEventListener("afterBasketChanged", warenkorbGeaendert);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start);
    } else {
        start();
    }
})();
