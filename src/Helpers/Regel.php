<?php

namespace VaporAktion2Test\Helpers;

/**
 * Kampanya kuralı — saf mantık, Plenty'ye dokunmaz.
 *
 * Girdi: ayarlar, müşterinin katılım hakkı, sepet satırları, oturumdaki hediye, sepetteki kupon kodu.
 * Çıktı: durum + uygulanacak işlemler listesi (GeschenkService bunları Plenty'de yürütür).
 *
 * Sepet satırı biçimi: ['id' => int, 'variationId' => int, 'itemId' => int, 'menge' => float, 'typ' => int]
 * Oturumdaki hediye:  ['variationId' => int, 'gruppe' => 'elfx'|'elfliq'|'turbo'] ya da null
 * Cihaz hediyeleri (ELFX, ELFA Turbo) ayrı satırdır ve her zaman tam 1 adet.
 *
 * ELFLIQ hediyesi aynı varyasyonun ücretli satırıyla birleşebilir (IO BasketService::addDataToBasket aynı
 * varyasyonu tek satırda toplar). Bu yüzden ELFLIQ hediyesi "satırdaki 1 adet" olarak düşünülür:
 * sayımda 1 düşülür, kaldırırken adet 1 azaltılır (adet 1 ise satır silinir).
 */
class Regel
{
    const AKTION_ZEILE_ENTFERNEN  = 'zeileEntfernen';
    const AKTION_MENGE_SETZEN     = 'mengeSetzen';
    const AKTION_GUTSCHEIN_WEG    = 'gutscheinEntfernen';
    const AKTION_GUTSCHEIN_SETZEN = 'gutscheinSetzen';
    const AKTION_VERGESSEN        = 'geschenkVergessen';

    /** Sayılan satır tipleri: normal varyasyon (10) ve tipi boş eski satırlar (0). Paket/set bileşenleri sayılmaz. */
    const ZAEHLBARE_TYPEN = [0, 10, 11]; // 11 = ungeteiltes Paket (elfbargermany: ELFLIQ-V Sammelartikel 14158)

    /**
     * Sepeti kurala göre değerlendir.
     */
    public static function bewerten(Einstellungen $e, bool $darfTeilnehmen, array $zeilen, $geschenk, string $coupon): array
    {
        $coupon         = Einstellungen::code($coupon);
        $couponIstUnser = $coupon !== '' && $e->istUnserCode($coupon);
        $fremd          = $coupon !== '' && !$couponIstUnser;
        $geschenk       = self::geschenkNormalisieren($geschenk);

        $ergebnis = [
            'aktionsMenge'     => 0,
            'bezahlt'          => 0,
            'benoetigt'        => $e->benoetigteMenge,
            'fehlt'            => $e->benoetigteMenge,
            'berechtigt'       => false,
            'fremderGutschein' => $fremd,
            'geschenk'         => null,
            'geschenkZeile'    => null,
            'aktionen'         => [],
            'grund'            => 'keineAenderung',
        ];

        $geschenkZeile = $geschenk !== null ? self::zeileFinden($zeilen, $geschenk['variationId']) : null;

        // Eklenti kapalı ya da müşteri katılamaz: bizim hediye + kupon kalmamalı.
        if (!$e->aktiv || !$darfTeilnehmen) {
            if ($geschenk !== null && $geschenkZeile !== null) {
                $ergebnis['aktionen'][] = self::geschenkEntfernenAktion($geschenk, $geschenkZeile);
            }
            if ($geschenk !== null) {
                $ergebnis['aktionen'][] = ['typ' => self::AKTION_VERGESSEN];
            }
            if ($couponIstUnser) {
                $ergebnis['aktionen'][] = ['typ' => self::AKTION_GUTSCHEIN_WEG, 'code' => $coupon];
            }
            if (count($ergebnis['aktionen']) > 0) {
                $ergebnis['grund'] = !$e->aktiv ? 'aktionInaktiv' : 'keineBerechtigung';
            }
            return $ergebnis;
        }

        // Oturumda hediye var ama satır sepette yok (müşteri sildi): hediyeyi unut.
        if ($geschenk !== null && $geschenkZeile === null) {
            $ergebnis['aktionen'][] = ['typ' => self::AKTION_VERGESSEN];
            $ergebnis['grund']      = 'geschenkZeileFehlt';
            $geschenk               = null;
        }

        // Hediye artık geçerli bir grupta değil (ayar değişti): kaldır.
        if ($geschenk !== null && !self::geschenkPasstZuGruppe($e, $geschenk, $geschenkZeile)) {
            $ergebnis['aktionen'][] = self::geschenkEntfernenAktion($geschenk, $geschenkZeile);
            $ergebnis['aktionen'][] = ['typ' => self::AKTION_VERGESSEN];
            $ergebnis['grund']      = 'geschenkNichtMehrErlaubt';
            $geschenk               = null;
            $geschenkZeile          = null;
        }

        $menge = self::aktionsMenge($e, $zeilen);
        $bezahlt = $menge;
        if ($geschenk !== null && $geschenk['gruppe'] === Einstellungen::GRUPPE_ELFLIQ) {
            $bezahlt = $menge - 1;
        }

        $ergebnis['aktionsMenge'] = (int)$menge;
        $ergebnis['bezahlt']      = (int)max(0, $bezahlt);
        $ergebnis['berechtigt']   = $bezahlt >= $e->benoetigteMenge;
        $ergebnis['fehlt']        = (int)max(0, $e->benoetigteMenge - $bezahlt);

        if ($geschenk !== null) {
            $erwartet = $e->codeFuerGruppe($geschenk['gruppe']);

            if (!$ergebnis['berechtigt'] || $fremd || $erwartet === '') {
                $ergebnis['aktionen'][] = self::geschenkEntfernenAktion($geschenk, $geschenkZeile);
                $ergebnis['aktionen'][] = ['typ' => self::AKTION_VERGESSEN];
                if ($couponIstUnser) {
                    $ergebnis['aktionen'][] = ['typ' => self::AKTION_GUTSCHEIN_WEG, 'code' => $coupon];
                }
                $ergebnis['grund'] = !$ergebnis['berechtigt'] ? 'zuWenigArtikel'
                    : ($fremd ? 'fremderGutscheinGeschenkEntfernt' : 'gutscheinCodeFehlt');

                // Hediye kalktı: ELFLIQ ise sayım yeniden (düşülen 1 adet artık ücretli satırın parçası değil, satırdan çıktı)
                if ($geschenk['gruppe'] === Einstellungen::GRUPPE_ELFLIQ) {
                    $ergebnis['aktionsMenge'] = (int)max(0, $menge - 1);
                }
                return $ergebnis;
            }

            $ergebnis['geschenk']      = $geschenk;
            $ergebnis['geschenkZeile'] = $geschenkZeile;

            // Cihaz hediyesi (ELFX / Turbo) her zaman tam 1 adet
            if (self::istGeraet($geschenk['gruppe']) && (float)$geschenkZeile['menge'] !== 1.0) {
                $ergebnis['aktionen'][] = [
                    'typ'         => self::AKTION_MENGE_SETZEN,
                    'zeilenId'    => $geschenkZeile['id'],
                    'variationId' => $geschenkZeile['variationId'],
                    'menge'       => 1,
                    'vorher'   => (float)$geschenkZeile['menge'],
                ];
                $ergebnis['grund'] = 'mengeKorrigiert';
            }

            if ($coupon !== $erwartet) {
                if ($couponIstUnser) {
                    $ergebnis['aktionen'][] = ['typ' => self::AKTION_GUTSCHEIN_WEG, 'code' => $coupon];
                }
                $ergebnis['aktionen'][] = ['typ' => self::AKTION_GUTSCHEIN_SETZEN, 'code' => $erwartet];
                $ergebnis['grund'] = 'gutscheinNachgesetzt';
            }
            return $ergebnis;
        }

        // Hediye yok ama bizim kodumuz sepette: sızıntı → sil.
        if ($couponIstUnser) {
            $ergebnis['aktionen'][] = ['typ' => self::AKTION_GUTSCHEIN_WEG, 'code' => $coupon];
            $ergebnis['grund']      = 'gutscheinOhneGeschenk';
        }

        return $ergebnis;
    }

    /**
     * Müşterinin hediye seçimini (ya da değiştirmesini) denetle.
     *
     * @param array $option ['variationId' => int, 'itemId' => int, 'gruppe' => string] — katalogdan doğrulanmış seçenek
     * @return string Hata anahtarı (Frontend.properties) ya da '' (seçim geçerli)
     */
    public static function wahlPruefen(Einstellungen $e, bool $darfTeilnehmen, array $zeilen, $geschenkAlt, string $coupon, array $option): string
    {
        if (!$e->aktiv || !$darfTeilnehmen) {
            return 'fehlerNichtVerfuegbar';
        }

        $bewertung = self::bewerten($e, true, $zeilen, $geschenkAlt, $coupon);
        if ($bewertung['fremderGutschein']) {
            return 'fehlerFremderGutschein';
        }

        // Eski hediye çıkarılınca kalan ücretli adet yetiyor mu?
        if (!$bewertung['berechtigt']) {
            return 'fehlerNichtBerechtigt';
        }

        $gruppe = (string)($option['gruppe'] ?? '');
        if (!$e->gruppeMoeglich($gruppe)) {
            return 'fehlerNichtVerfuegbar';
        }

        // Aynı cihaz (ELFX / Turbo) zaten ücretli olarak sepette: satır birleşir, "adet 1" kuralı müşterinin cihazını keserdi.
        $alt = self::geschenkNormalisieren($geschenkAlt);
        if (self::istGeraet($gruppe)) {
            $vorhanden = self::zeileFinden($zeilen, (int)$option['variationId']);
            $istAltesGeschenk = $alt !== null && $alt['variationId'] === (int)$option['variationId'];
            if ($vorhanden !== null && !$istAltesGeschenk) {
                return 'fehlerGeraetImWarenkorb';
            }
        }

        // ELFLIQ hediyesi: hediyeden sonra da en az benoetigteMenge ücretli kalmalı → kural zaten bezahlt'a bakıyor.
        return '';
    }

    /** Sepetteki Aktions-Artikel adedi (tüm sayılabilir satırlar, ELFLIQ hediyesi dahil). */
    public static function aktionsMenge(Einstellungen $e, array $zeilen): float
    {
        $summe = 0.0;
        foreach ($zeilen as $z) {
            if (!in_array((int)$z['typ'], self::ZAEHLBARE_TYPEN, true)) {
                continue;
            }
            if ($e->istAktionsArtikel((int)$z['itemId'], (int)$z['variationId'])) {
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

    /** Hediyeyi sepetten çıkaran işlem: ELFLIQ ve adet > 1 ise 1 azalt, yoksa satırı sil. */
    public static function geschenkEntfernenAktion(array $geschenk, array $zeile): array
    {
        if ($geschenk['gruppe'] === Einstellungen::GRUPPE_ELFLIQ && (float)$zeile['menge'] > 1) {
            return [
                'typ'         => self::AKTION_MENGE_SETZEN,
                'zeilenId'    => $zeile['id'],
                'variationId' => $zeile['variationId'],
                'menge'       => (float)$zeile['menge'] - 1,
                'vorher'   => (float)$zeile['menge'],
            ];
        }
        return ['typ' => self::AKTION_ZEILE_ENTFERNEN, 'zeilenId' => $zeile['id']];
    }

    /** Cihaz grubu mu (ayrı satır, adet 1)? ELFLIQ hediyesi ücretli satırla birleşebildiği için cihaz değildir. */
    public static function istGeraet(string $gruppe): bool
    {
        return $gruppe === Einstellungen::GRUPPE_ELFX || $gruppe === Einstellungen::GRUPPE_TURBO;
    }

    private static function geschenkPasstZuGruppe(Einstellungen $e, array $geschenk, array $zeile): bool
    {
        if ($geschenk['gruppe'] === Einstellungen::GRUPPE_ELFX) {
            return $e->istElfxArtikel((int)$zeile['itemId']);
        }
        if ($geschenk['gruppe'] === Einstellungen::GRUPPE_ELFLIQ) {
            return $e->geschenkElfliqErlaubt && $e->istAktionsArtikel((int)$zeile['itemId'], (int)$zeile['variationId']);
        }
        if ($geschenk['gruppe'] === Einstellungen::GRUPPE_TURBO) {
            return $e->istTurboArtikel((int)$zeile['itemId']);
        }
        return false;
    }

    public static function geschenkNormalisieren($geschenk)
    {
        if (!is_array($geschenk)) {
            return null;
        }
        $variationId = (int)($geschenk['variationId'] ?? 0);
        $gruppe      = (string)($geschenk['gruppe'] ?? '');
        if ($variationId <= 0 || !in_array($gruppe, Einstellungen::GRUPPEN, true)) {
            return null;
        }
        return ['variationId' => $variationId, 'gruppe' => $gruppe];
    }
}
