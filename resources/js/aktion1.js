/*
 * VaporAktion2Test – Aktion 1: "Kaufe 3 ELFA Pods – 1 ELFA Basisgerät gratis" (plentyShop LTS 5).
 *
 * Aktion 2'den (geschenk.js) bağımsız: kendi rotası /rest/vapor-aktion1/geschenk, CSS öneki va1-.
 * - Tüm [data-va1-box] kutularını doldurur: sepet sayfası (warenkorb), sepet önizlemesi (vorschau), ürün sayfası (artikel).
 * - Ürün sayfası: kutu yalnız ürün ayardaki ELFA Pod listesindeyse görünür (sunucu: "istPod").
 * - Durumu yalnız kutu görünürken (IntersectionObserver) ve "afterBasketChanged" sonrası yükler.
 * - Yazma fetch ile, tam bir X-CSRF-TOKEN başlığıyla (Aktion 2'de jQuery çift gönderdi → 500 + oturum kapandı).
 *   #csrf-token yoksa istek atılmaz, sayfa yenilenir.
 */
(function () {
    "use strict";

    if (window.__va1Geladen) {
        return;
    }
    window.__va1Geladen = true;

    var URL = "/rest/vapor-aktion1/geschenk";
    var zustand = null;
    var veraltet = true;
    var laedt = false;
    var nochmal = false;
    var auswahlOffen = false;
    var meldung = "";
    var meldungOk = false;
    var sendet = false;
    var sichtbare = [];
    var beobachter = null;
    var zaehler = 0;
    var gewaehlt = 0;          // ızgarada tıklanan varyasyon (gönderilmeden önce)

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

    // Ürün sayfasının item ID'si: container'dan (Twig) ya da plentyShop store'undan
    function artikelId() {
        var box = document.querySelector('[data-va1-box][data-va1-ort="artikel"]');
        if (!box) {
            return 0;
        }
        var id = parseInt(box.getAttribute("data-va1-item"), 10) || 0;
        if (!id) {
            try {
                var v = window.ceresStore && window.ceresStore.getters && window.ceresStore.getters.currentItemVariation;
                id = v && v.item ? parseInt(v.item.id, 10) || 0 : 0;
            } catch (e) {
                id = 0;
            }
        }
        return id;
    }

    function anfrage(methode, daten, fertig) {
        if (methode !== "GET" && !csrf()) {
            window.location.reload();
            return;
        }
        var kopf = {
            "X-CSRF-TOKEN": csrf(),
            "X-Requested-With": "XMLHttpRequest",
            "Accept": "application/json",
            "Accept-Language": (window.App && window.App.language) || "de"
        };
        var opts = { method: methode, credentials: "same-origin", headers: kopf };
        var url = URL;
        if (methode === "GET") {
            var id = artikelId();
            url += "?_=" + Date.now() + (id ? "&itemId=" + id : "");
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
                // Sepeti (satırlar, kupon, toplamlar) güvenle yenile
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

    // ---------------------------------------------------------------- Görünüm

    function svgGeschenk() {
        return '<svg class="va1-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' +
            '<path fill="currentColor" d="M20 7h-2.2A3 3 0 0 0 12 3.8 3 3 0 0 0 6.2 7H4a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h1v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8h1a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1Zm-5-2a1 1 0 1 1 0 2h-2V6a1 1 0 0 1 2-1ZM9 5a1 1 0 0 1 2 1v1H9a1 1 0 1 1 0-2ZM5 9h6v1H5V9Zm2 3h4v7H7v-7Zm10 7h-4v-7h4v7Zm2-9h-6V9h6v1Z"/>' +
            "</svg>";
    }

    function optionen() {
        return zustand && zustand.optionen && zustand.optionen.length ? zustand.optionen : [];
    }

    function knopf(aktion, klasse, text, aus) {
        return '<button type="button" class="btn ' + klasse + ' va1-knopf" data-va1-aktion="' + aktion + '"' + (aus ? " disabled" : "") + ">" + esc(text) + "</button>";
    }

    function html(box) {
        var z = zustand;
        var t = z.texte || {};
        var ort = box.getAttribute("data-va1-ort") || "warenkorb";
        var kompakt = ort === "vorschau";
        var uid = box.getAttribute("data-va1-uid");
        var teile = [];

        teile.push('<div class="va1-kopf">' + svgGeschenk() + '<strong class="va1-titel">' + esc(t.titel) + "</strong></div>");

        if (z.fremderGutschein) {
            teile.push('<p class="va1-text va1-warnung">' + esc(z.andereAktion ? t.andereAktion : t.fremderGutschein) + "</p>");
        } else if (z.geschenk && !auswahlOffen) {
            var g = z.geschenk;
            teile.push(
                '<p class="va1-fertig"><span class="va1-haken" aria-hidden="true"></span><span>' + esc(t.deinGeschenk) + " " +
                    '<b class="va1-name">' + esc(g.farbe || g.name) + "</b> " +
                    (g.preis ? '<s class="va1-statt">' + esc(g.preis) + "</s> " : "") +
                    '<span class="va1-gratis">' + esc(t.gratis) + "</span></span></p>" +
                '<div class="va1-knoepfe">' +
                    (optionen().length > 1 ? knopf("aendern", "btn-sm btn-outline-primary", t.aendern, sendet) : "") +
                    knopf("entfernen", "btn-sm btn-link", t.entfernen, sendet) +
                "</div>"
            );
        } else if (!z.berechtigt) {
            var prozent = z.benoetigt > 0 ? Math.min(100, Math.round((z.podMenge / z.benoetigt) * 100)) : 0;
            if (ort === "artikel") {
                teile.push('<p class="va1-text">' + esc(t.intro) + "</p>");
            }
            teile.push(
                '<div class="va1-fortschritt">' +
                    '<span class="va1-balken" role="progressbar" aria-valuemin="0" aria-valuemax="' + esc(z.benoetigt) + '" aria-valuenow="' + esc(Math.min(z.podMenge, z.benoetigt)) + '">' +
                        '<span style="width:' + prozent + '%"></span>' +
                    "</span>" +
                    '<span class="va1-zahl">' + esc(t.fortschritt) + "</span>" +
                "</div>" +
                '<p class="va1-text va1-stark">' + esc(t.nochFehlend) + "</p>"
            );
        } else if (!optionen().length) {
            teile.push('<p class="va1-text">' + esc(t.keineOptionen) + "</p>");
        } else {
            teile.push(kompakt ? auswahlKompakt(uid, t) : auswahlRaster(uid, t));
        }

        if (meldung) {
            teile.push('<p class="va1-meldung ' + (meldungOk ? "va1-ok" : "va1-fehler") + '" role="status">' + esc(meldung) + "</p>");
        }

        teile.push('<p class="va1-klein">' + esc(t.hinweis) + "</p>");
        if (!kompakt && t.rechtlich) {
            teile.push('<p class="va1-klein">' + esc(t.rechtlich) + "</p>");
        }

        return '<div class="va1' + (kompakt ? " va1-kompakt" : "") + (sendet ? " va1-sendet" : "") + '">' + teile.join("") + "</div>";
    }

    function aktuelleWahl() {
        if (gewaehlt) {
            return gewaehlt;
        }
        return zustand.geschenk ? zustand.geschenk.variationId : 0;
    }

    function auswahlRaster(uid, t) {
        var wahl = aktuelleWahl();
        var name = "va1-farbe-" + uid;
        var teile = ['<p class="va1-text va1-stark" id="va1-l-' + uid + '">' + esc(t.berechtigt) + "</p>"];
        teile.push('<div class="va1-raster" role="radiogroup" aria-labelledby="va1-l-' + uid + '">');
        optionen().forEach(function (o) {
            var an = o.variationId === wahl;
            teile.push(
                '<label class="va1-kachel' + (an ? " va1-an" : "") + '">' +
                    '<input type="radio" name="' + name + '" value="' + esc(o.variationId) + '" data-va1-farbe' + (an ? " checked" : "") + ">" +
                    (o.bild ? '<img class="va1-bild" src="' + esc(o.bild) + '" alt="" width="44" height="44" loading="lazy">' : "") +
                    '<span class="va1-farbe">' + esc(o.farbe) + "</span>" +
                    '<span class="va1-preis">' + (o.preis ? "<s>" + esc(o.preis) + "</s> " : "") + "<b>" + esc(t.gratis) + "</b></span>" +
                "</label>"
            );
        });
        teile.push("</div>");
        teile.push('<div class="va1-knoepfe">');
        teile.push(knopf("hinzufuegen", "btn-primary va1-haupt", sendet ? t.laden : (zustand.geschenk ? t.farbeTauschen : t.hinzufuegen), sendet));
        if (zustand.geschenk) {
            teile.push(knopf("abbrechen", "btn-sm btn-link", t.abbrechen, false));
        }
        teile.push("</div>");
        return teile.join("");
    }

    function auswahlKompakt(uid, t) {
        var wahl = aktuelleWahl();
        var id = "va1-s-" + uid;
        var teile = ['<label class="va1-text va1-stark" for="' + id + '">' + esc(t.berechtigt) + "</label>"];
        teile.push('<div class="va1-zeile"><select id="' + id + '" class="custom-select va1-select" data-va1-select>');
        teile.push('<option value="">' + esc(t.auswahlPlatzhalter) + "</option>");
        optionen().forEach(function (o) {
            teile.push('<option value="' + esc(o.variationId) + '"' + (o.variationId === wahl ? " selected" : "") + ">" + esc(o.farbe) + "</option>");
        });
        teile.push("</select>");
        teile.push(knopf("hinzufuegen", "btn-primary", sendet ? t.laden : t.hinzufuegenKurz, sendet));
        teile.push("</div>");
        if (zustand.geschenk) {
            teile.push('<div class="va1-knoepfe">' + knopf("abbrechen", "btn-sm btn-link", t.abbrechen, false) + "</div>");
        }
        return teile.join("");
    }

    function sichtbarFuer(box) {
        if (!zustand || !zustand.sichtbar) {
            return false;
        }
        if (box.getAttribute("data-va1-ort") === "artikel" && !zustand.istPod) {
            return false;
        }
        return true;
    }

    function zeichnen(box) {
        if (!sichtbarFuer(box)) {
            // display:none değil – yoksa IntersectionObserver kutuyu bir daha görünür bildirmez
            box.innerHTML = "";
            return;
        }
        box.innerHTML = html(box);
    }

    function alleZeichnen() {
        var boxen = document.querySelectorAll("[data-va1-box][data-va1-uid]");
        for (var i = 0; i < boxen.length; i++) {
            zeichnen(boxen[i]);
        }
    }

    // ---------------------------------------------------------------- Olaylar

    function klick(ereignis) {
        var el = ereignis.target.closest ? ereignis.target.closest("[data-va1-aktion]") : null;
        if (!el || sendet) {
            return;
        }
        var box = el.closest("[data-va1-box]");
        var aktion = el.getAttribute("data-va1-aktion");

        if (aktion === "aendern") {
            auswahlOffen = true;
            gewaehlt = 0;
            meldung = "";
            alleZeichnen();
        } else if (aktion === "abbrechen") {
            auswahlOffen = false;
            gewaehlt = 0;
            meldung = "";
            alleZeichnen();
        } else if (aktion === "entfernen") {
            senden("DELETE", {});
        } else if (aktion === "hinzufuegen") {
            var wert = 0;
            var select = box ? box.querySelector("[data-va1-select]") : null;
            if (select) {
                wert = parseInt(select.value, 10) || 0;
            } else {
                var radio = box ? box.querySelector("[data-va1-farbe]:checked") : null;
                wert = radio ? parseInt(radio.value, 10) || 0 : 0;
            }
            if (!wert) {
                meldung = (zustand && zustand.texte && zustand.texte.fehlerKeineFarbe) || "";
                meldungOk = false;
                alleZeichnen();
                return;
            }
            senden("POST", { variationId: wert });
        }
    }

    function aenderung(ereignis) {
        var ziel = ereignis.target;
        if (ziel && ziel.hasAttribute && (ziel.hasAttribute("data-va1-farbe") || ziel.hasAttribute("data-va1-select"))) {
            gewaehlt = parseInt(ziel.value, 10) || 0;
            if (ziel.hasAttribute("data-va1-farbe")) {
                meldung = "";
                alleZeichnen();
            }
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
        var boxen = document.querySelectorAll("[data-va1-box]:not([data-va1-uid])");
        for (var i = 0; i < boxen.length; i++) {
            var box = boxen[i];
            zaehler += 1;
            box.setAttribute("data-va1-uid", String(zaehler));
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
        if ("MutationObserver" in window) {
            new window.MutationObserver(sucheEinplanen).observe(document.body, { childList: true, subtree: true });
        }
        document.addEventListener("afterBasketChanged", warenkorbGeaendert);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start);
    } else {
        start();
    }
})();
