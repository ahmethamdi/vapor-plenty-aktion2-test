<?php

namespace VaporAktion2Test\Aktion1;

/**
 * Aktion 1 kuralı — saf mantık, Plenty'ye dokunmaz.
 *
 * Sepet satırı:   ['id' => int, 'variationId' => int, 'itemId' => int, 'menge' => float, 'typ' => int]
 * Oturum hediyesi: ['variationId' => int] ya da null
 *
 * Ölçülen satır tipleri (20.11.2026, misafir sepeti):
 *  - Lostmary (65060) paketleri böler → her pod tek tat varyasyonu, itemType 10
 *  - elfbargermany (62081) paketleri bölmez → Sammelartikel pod'u (item 12569/24839/24840) itemType 11
 * Bu yüzden 0/10/11 sayılır (Aktion 2 yalnız 0/10 sayar; ona dokunulmadı).
 *
 * Hediye cihaz satırı ücretli aynı renkle birleşebilir (IO aynı varyasyonu tek satırda toplar).
 * Festbetrag kupon tek seferlik olduğu için "satırdaki 1 adet" bedavadır; kaldırırken adet 1 azaltılır.
 */
class A1Regel
{
    const AKTION_ZEILE_ENTFERNEN  = 'zeileEntfernen';
    const AKTION_MENGE_SETZEN     = 'mengeSetzen';
    const AKTION_GUTSCHEIN_WEG    = 'gutscheinEntfernen';
    const AKTION_GUTSCHEIN_SETZEN = 'gutscheinSetzen';
    const AKTION_VERGESSEN        = 'geschenkVergessen';

    const ZAEHLBARE_TYPEN = [0, 10, 11];

    public static function bewerten(A1Einstellungen $e, bool $darfTeilnehmen, array $zeilen, $geschenk, string $coupon): array
    {
        $coupon         = A1Einstellungen::code($coupon);
        $couponIstUnser = $e->istUnserCode($coupon);
        $fremd          = $coupon !== '' && !$couponIstUnser;
        $geschenk       = self::geschenkNormalisieren($geschenk);

        $ergebnis = [
            'podMenge'         => 0,
            'benoetigt'        => $e->benoetigteMenge,
            'fehlt'            => $e->benoetigteMenge,
            'berechtigt'       => false,
            'fremderGutschein' => $fremd,
            'andereAktion'     => $fremd && $e->istAndereAktion($coupon),
            'geschenk'         => null,
            'geschenkZeile'    => null,
            'aktionen'         => [],
            'grund'            => 'keineAenderung',
        ];

        $geschenkZeile = $geschenk !== null ? self::zeileFinden($zeilen, $geschenk['variationId']) : null;

        // Kapalı / kurulmamış / katılamaz: bizim hediye + kupon kalmamalı
        if (!$e->aktiv || !$darfTeilnehmen || $e->gutschein === '') {
            if ($geschenk !== null && $geschenkZeile !== null) {
                $ergebnis['aktionen'][] = self::geschenkEntfernenAktion($geschenkZeile);
            }
            if ($geschenk !== null) {
                $ergebnis['aktionen'][] = ['typ' => self::AKTION_VERGESSEN];
            }
            if ($couponIstUnser) {
                $ergebnis['aktionen'][] = ['typ' => self::AKTION_GUTSCHEIN_WEG, 'code' => $coupon];
            }
            if (count($ergebnis['aktionen']) > 0) {
                $ergebnis['grund'] = !$e->aktiv || $e->gutschein === '' ? 'a1AktionInaktiv' : 'a1KeineBerechtigung';
            }
            return $ergebnis;
        }

        // Hediye satırı sepette yok (müşteri sildi): unut
        if ($geschenk !== null && $geschenkZeile === null) {
            $ergebnis['aktionen'][] = ['typ' => self::AKTION_VERGESSEN];
            $ergebnis['grund']      = 'a1GeschenkZeileFehlt';
            $geschenk               = null;
        }

        // Hediye artık cihaz listesinde değil (ayar değişti)
        if ($geschenk !== null && !$e->istGeschenkArtikel((int)$geschenkZeile['itemId'])) {
            $ergebnis['aktionen'][] = self::geschenkEntfernenAktion($geschenkZeile);
            $ergebnis['aktionen'][] = ['typ' => self::AKTION_VERGESSEN];
            $ergebnis['grund']      = 'a1GeschenkNichtMehrErlaubt';
            $geschenk               = null;
            $geschenkZeile          = null;
        }

        $menge = self::podMenge($e, $zeilen);
        $ergebnis['podMenge']   = (int)$menge;
        $ergebnis['berechtigt'] = $menge >= $e->benoetigteMenge;
        $ergebnis['fehlt']      = (int)max(0, $e->benoetigteMenge - $menge);

        if ($geschenk !== null) {
            if (!$ergebnis['berechtigt'] || $fremd) {
                $ergebnis['aktionen'][] = self::geschenkEntfernenAktion($geschenkZeile);
                $ergebnis['aktionen'][] = ['typ' => self::AKTION_VERGESSEN];
                if ($couponIstUnser) {
                    $ergebnis['aktionen'][] = ['typ' => self::AKTION_GUTSCHEIN_WEG, 'code' => $coupon];
                }
                $ergebnis['grund'] = !$ergebnis['berechtigt'] ? 'a1ZuWenigPods' : 'a1FremderGutscheinGeschenkEntfernt';
                return $ergebnis;
            }

            $ergebnis['geschenk']      = $geschenk;
            $ergebnis['geschenkZeile'] = $geschenkZeile;

            if ($coupon !== $e->gutschein) {
                $ergebnis['aktionen'][] = ['typ' => self::AKTION_GUTSCHEIN_SETZEN, 'code' => $e->gutschein];
                $ergebnis['grund']      = 'a1GutscheinNachgesetzt';
            }
            return $ergebnis;
        }

        // Hediye yok ama bizim kod sepette: sızıntı → sil
        if ($couponIstUnser) {
            $ergebnis['aktionen'][] = ['typ' => self::AKTION_GUTSCHEIN_WEG, 'code' => $coupon];
            $ergebnis['grund']      = 'a1GutscheinOhneGeschenk';
        }

        return $ergebnis;
    }

    /**
     * Müşterinin renk seçimini denetle.
     * @param array $option ['variationId' => int, 'itemId' => int] — katalogdan doğrulanmış
     * @return string Hata anahtarı (Aktion1.properties) ya da '' (geçerli)
     */
    public static function wahlPruefen(A1Einstellungen $e, bool $darfTeilnehmen, array $zeilen, $geschenkAlt, string $coupon, array $option): string
    {
        if (!$e->aktiv || !$darfTeilnehmen || !$e->vollstaendig()) {
            return 'fehlerNichtVerfuegbar';
        }
        $b = self::bewerten($e, true, $zeilen, $geschenkAlt, $coupon);
        if ($b['andereAktion']) {
            return 'fehlerAndereAktion';
        }
        if ($b['fremderGutschein']) {
            return 'fehlerFremderGutschein';
        }
        if (!$b['berechtigt']) {
            return 'fehlerNichtBerechtigt';
        }
        if (!$e->istGeschenkArtikel((int)($option['itemId'] ?? 0))) {
            return 'fehlerNichtVerfuegbar';
        }
        return '';
    }

    /** Sepetteki ELFA Pod adedi (2er Pack = 1). */
    public static function podMenge(A1Einstellungen $e, array $zeilen): float
    {
        $summe = 0.0;
        foreach ($zeilen as $z) {
            if (!in_array((int)$z['typ'], self::ZAEHLBARE_TYPEN, true)) {
                continue;
            }
            if ($e->istPod((int)$z['itemId'], (int)$z['variationId'])) {
                $summe += max(0.0, (float)$z['menge']);
            }
        }
        return $summe;
    }

    public static function zeileFinden(array $zeilen, int $variationId)
    {
        foreach ($zeilen as $z) {
            if ((int)$z['variationId'] === $variationId
                && in_array((int)$z['typ'], self::ZAEHLBARE_TYPEN, true)
                && (float)$z['menge'] > 0) {
                return $z;
            }
        }
        return null;
    }

    /** Hediyeyi çıkar: adet > 1 ise 1 azalt (kalan ücretli), değilse satırı sil. */
    public static function geschenkEntfernenAktion(array $zeile): array
    {
        if ((float)$zeile['menge'] > 1) {
            return [
                'typ'         => self::AKTION_MENGE_SETZEN,
                'zeilenId'    => $zeile['id'],
                'variationId' => $zeile['variationId'],
                'menge'       => (float)$zeile['menge'] - 1,
            ];
        }
        return ['typ' => self::AKTION_ZEILE_ENTFERNEN, 'zeilenId' => $zeile['id']];
    }

    public static function geschenkNormalisieren($geschenk)
    {
        if (!is_array($geschenk)) {
            return null;
        }
        $variationId = (int)($geschenk['variationId'] ?? 0);
        return $variationId > 0 ? ['variationId' => $variationId] : null;
    }
}
