<?php

namespace VaporAktion2Test\Aktion1;

use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Basket\Models\BasketItem;
use Plenty\Modules\Webshop\Contracts\ContactRepositoryContract;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Translation\Translator;

/**
 * Aktion 1: "Kaufe 3 ELFA Pods und erhalte 1 ELFA Basisgerät gratis".
 *
 * Aktion 2 ile aynı kanıtlanmış yol: cihaz gerçek varyasyon olarak tam fiyatla sepete girer + panelde açılmış
 * Aktionsgutschein (Festbetrag = cihaz fiyatı, filtre = ELFA Basisgerät) otomatik uygulanır.
 * Aktion 2'den tamamen ayrı: kendi ayarları (a1…), oturum anahtarı, rota, JS/CSS (va1-).
 *
 * Plenty sepetinde tek kupon olur → iki kampanya aynı anda kupon isteyemez. Sepette Aktion 2'nin kodu
 * (ya da başka bir kupon) varsa bu kutu seçim yaptırmaz ("Nicht mit anderer Aktion kombinierbar").
 *
 * Kural: hata asla sepeti bozmamalı → her yol try/catch, hata loglanır, sessizce çıkılır.
 */
class A1GeschenkService
{
    use Loggable;

    const SESSION_SCHLUESSEL = 'VaporAktion2Test_aktion1';
    const PLUGIN             = 'VaporAktion2Test';

    /** @var bool Döngü kilidi (kendi sepet işlemlerimiz yeni AfterBasketChanged ateşler) */
    private $laeuft = false;

    /** @var ConfigRepository */
    private $config;

    /** @var BasketItemRepositoryContract */
    private $basketItemRepository;

    /** @var BasketRepositoryContract */
    private $basketRepository;

    /** @var ContactRepositoryContract */
    private $contactRepository;

    /** @var SessionStorageRepositoryContract */
    private $sessionStorage;

    /** @var A1KatalogService */
    private $katalog;

    /** @var A1Einstellungen|null */
    private $einstellungen = null;

    public function __construct(
        ConfigRepository $config,
        BasketItemRepositoryContract $basketItemRepository,
        BasketRepositoryContract $basketRepository,
        ContactRepositoryContract $contactRepository,
        SessionStorageRepositoryContract $sessionStorage,
        A1KatalogService $katalog
    ) {
        $this->config               = $config;
        $this->basketItemRepository = $basketItemRepository;
        $this->basketRepository     = $basketRepository;
        $this->contactRepository    = $contactRepository;
        $this->sessionStorage       = $sessionStorage;
        $this->katalog              = $katalog;
    }

    // ------------------------------------------------------------------
    // Giriş noktaları
    // ------------------------------------------------------------------

    /**
     * Sepeti kurala uydur.
     * @return bool true = sepet içeriği / kupon değişti
     */
    public function pruefen(string $anlass = 'warenkorb'): bool
    {
        if ($this->laeuft) {
            return false;
        }
        $this->laeuft = true;
        try {
            return $this->abgleichen($anlass);
        } catch (\Throwable $ex) {
            $this->fehlerLoggen('A1GeschenkService_pruefen', $ex, ['anlass' => $anlass]);
            return false;
        } finally {
            $this->laeuft = false;
        }
    }

    /**
     * Kutu durumu.
     * @param int $itemId Ürün sayfasındaki item (0 = sepet); yanıtta "istPod" olarak döner
     */
    public function status(int $itemId = 0): array
    {
        $this->pruefen('status');
        try {
            return $this->statusBauen('', $itemId);
        } catch (\Throwable $ex) {
            $this->fehlerLoggen('A1GeschenkService_status', $ex, []);
            return ['sichtbar' => false];
        }
    }

    /** Müşteri bir renk seçti (ya da rengi değiştirdi). */
    public function waehlen(int $variationId): array
    {
        if ($this->laeuft) {
            return $this->antwort(false, 'fehlerAllgemein');
        }
        $this->laeuft = true;
        try {
            $meldung = $this->waehlenIntern($variationId);
        } catch (\Throwable $ex) {
            $this->fehlerLoggen('A1GeschenkService_waehlen', $ex, ['variationId' => $variationId]);
            $meldung = 'fehlerAllgemein';
        } finally {
            $this->laeuft = false;
        }
        return $this->antwort($meldung === 'erfolg', $meldung);
    }

    /** Müşteri hediyeyi kaldırdı. */
    public function entfernen(): array
    {
        if ($this->laeuft) {
            return $this->antwort(false, 'fehlerAllgemein');
        }
        $this->laeuft = true;
        try {
            $geschenk = $this->geschenkLesen();
            if ($geschenk !== null) {
                $this->geschenkAusWarenkorb($geschenk, $this->zeilen());
                $this->geschenkVergessen();
                $this->unserenGutscheinEntfernen();
                $this->getLogger('A1GeschenkService_entfernen')
                    ->report(self::PLUGIN . '::Aktion1Log.a1GeschenkVomKundenEntfernt', $this->kontext() + ['geschenk' => $geschenk]);
            }
        } catch (\Throwable $ex) {
            $this->fehlerLoggen('A1GeschenkService_entfernen', $ex, []);
        } finally {
            $this->laeuft = false;
        }
        return $this->antwort(true, '');
    }

    // ------------------------------------------------------------------
    // Kural uygulama
    // ------------------------------------------------------------------

    private function abgleichen(string $anlass): bool
    {
        $e        = $this->einstellungen();
        $geschenk = $this->geschenkLesen();
        $coupon   = $this->couponImKorb();

        // Hızlı çıkış: kapalı, oturumda hediye yok, sepetteki kupon bizim değil
        if (!$e->aktiv && $geschenk === null && !$e->istUnserCode($coupon)) {
            return false;
        }

        $zeilen    = $this->zeilen();
        $bewertung = A1Regel::bewerten($e, $this->darfTeilnehmen($e), $zeilen, $geschenk, $coupon);
        if (count($bewertung['aktionen']) === 0) {
            return false;
        }

        $kontext = $this->kontext() + [
            'anlass'    => $anlass,
            'grund'     => $bewertung['grund'],
            'podMenge'  => $bewertung['podMenge'],
            'benoetigt' => $bewertung['benoetigt'],
            'coupon'    => $coupon,
            'geschenk'  => $geschenk,
            'aktionen'  => $bewertung['aktionen'],
        ];

        $geaendert = $this->ausfuehren($bewertung['aktionen'], $kontext);

        $this->getLogger('A1GeschenkService_abgleichen')
            ->report(self::PLUGIN . '::Aktion1Log.' . $bewertung['grund'], $kontext + ['geaendert' => $geaendert]);

        return $geaendert;
    }

    private function ausfuehren(array $aktionen, array $kontext): bool
    {
        $geaendert = false;
        foreach ($aktionen as $aktion) {
            try {
                switch ($aktion['typ']) {
                    case A1Regel::AKTION_ZEILE_ENTFERNEN:
                        $this->basketItemRepository->removeBasketItem((int)$aktion['zeilenId']);
                        $geaendert = true;
                        break;

                    case A1Regel::AKTION_MENGE_SETZEN:
                        $this->mengeSetzen((int)$aktion['zeilenId'], (int)$aktion['variationId'], (float)$aktion['menge']);
                        $geaendert = true;
                        break;

                    case A1Regel::AKTION_GUTSCHEIN_WEG:
                        // Yalnız gerçekten bizim kod duruyorsa (önceki işlem Plenty'de kuponu zaten düşürmüş olabilir)
                        $this->unserenGutscheinEntfernen();
                        $geaendert = true;
                        break;

                    case A1Regel::AKTION_GUTSCHEIN_SETZEN:
                        if (!$this->gutscheinSetzen((string)$aktion['code'])) {
                            // Kupon tutmadı: cihaz tam fiyatla kalmasın
                            $geschenk = $this->geschenkLesen();
                            if ($geschenk !== null) {
                                $this->geschenkAusWarenkorb($geschenk, $this->zeilen());
                                $this->geschenkVergessen();
                            }
                            $this->unserenGutscheinEntfernen();
                            $this->getLogger('A1GeschenkService_ausfuehren')
                                ->error(self::PLUGIN . '::Aktion1Log.a1GutscheinFehlgeschlagenGeschenkEntfernt', $kontext);
                            $geaendert = true;
                        }
                        break;

                    case A1Regel::AKTION_VERGESSEN:
                        $this->geschenkVergessen();
                        break;
                }
            } catch (\Throwable $ex) {
                $this->fehlerLoggen('A1GeschenkService_ausfuehren', $ex, $kontext + ['aktion' => $aktion]);
            }
        }
        return $geaendert;
    }

    private function waehlenIntern(int $variationId): string
    {
        $e    = $this->einstellungen();
        $darf = $this->darfTeilnehmen($e);
        if (!$e->aktiv || !$darf || !$e->vollstaendig() || $variationId <= 0) {
            return 'fehlerNichtVerfuegbar';
        }

        // Önce sepeti kurala uydur (eski, geçersiz hediye vb. temizlensin)
        $this->abgleichen('waehlen');

        $option = $this->katalog->optionFinden($e, $variationId);
        if ($option === null) {
            $this->getLogger('A1GeschenkService_waehlen')
                ->info(self::PLUGIN . '::Aktion1Log.a1OptionUngueltig', $this->kontext() + ['variationId' => $variationId]);
            return 'fehlerNichtVerfuegbar';
        }

        $coupon = $this->couponImKorb();
        $zeilen = $this->zeilen();
        $alt    = $this->geschenkLesen();

        $fehler = A1Regel::wahlPruefen($e, $darf, $zeilen, $alt, $coupon, $option);
        if ($fehler !== '') {
            $this->getLogger('A1GeschenkService_waehlen')
                ->info(self::PLUGIN . '::Aktion1Log.a1WahlAbgelehnt', $this->kontext() + [
                    'variationId' => $variationId,
                    'fehler'      => $fehler,
                    'coupon'      => $coupon,
                ]);
            return $fehler;
        }

        if ($alt !== null && $alt['variationId'] === $option['variationId']) {
            return 'erfolg'; // aynı renk zaten hediye
        }

        // Eski hediyeyi çıkar (kupon kalabilir: yeni renk de aynı kampanyada; yine de temiz başla)
        if ($alt !== null) {
            $this->geschenkAusWarenkorb($alt, $zeilen);
            $this->geschenkVergessen();
            $this->unserenGutscheinEntfernen();
        }

        /** @var \IO\Services\BasketService $ioBasket */
        $ioBasket = pluginApp(\IO\Services\BasketService::class);
        $sonuc    = $ioBasket->addBasketItem(['variationId' => $option['variationId'], 'quantity' => 1]);
        if (is_array($sonuc) && array_key_exists('code', $sonuc)) {
            $this->getLogger('A1GeschenkService_waehlen')
                ->error(self::PLUGIN . '::Aktion1Log.a1HinzufuegenFehlgeschlagen', $this->kontext() + [
                    'option' => $option,
                    'code'   => $sonuc['code'],
                ]);
            return 'fehlerHinzufuegen';
        }

        $geschenk = ['variationId' => $option['variationId']];
        $this->geschenkSchreiben($geschenk);

        if (!$this->gutscheinSetzen($e->gutschein)) {
            $this->geschenkAusWarenkorb($geschenk, $this->zeilen());
            $this->geschenkVergessen();
            $this->unserenGutscheinEntfernen();
            $this->getLogger('A1GeschenkService_waehlen')
                ->error(self::PLUGIN . '::Aktion1Log.a1GutscheinFehlgeschlagenGeschenkEntfernt', $this->kontext() + ['option' => $option]);
            return 'fehlerGutschein';
        }

        $this->getLogger('A1GeschenkService_waehlen')
            ->report(self::PLUGIN . '::Aktion1Log.a1GeschenkGewaehlt', $this->kontext() + [
                'option' => $option,
                'vorher' => $alt,
            ]);

        return 'erfolg';
    }

    /** Kuponu uygula ve gerçekten indirim doğurduğunu doğrula. */
    private function gutscheinSetzen(string $code): bool
    {
        $code = A1Einstellungen::code($code);
        if ($code === '') {
            return false;
        }

        try {
            $basket = $this->basketRepository->setCouponCode($code);
        } catch (\Throwable $ex) {
            $this->fehlerLoggen('A1GeschenkService_gutscheinSetzen', $ex, ['code' => $code]);
            return false;
        }

        if (!$basket instanceof Basket) {
            $basket = $this->basketRepository->load();
        }

        $gesetzt = $basket instanceof Basket ? A1Einstellungen::code($basket->couponCode) : '';
        $rabatt  = $basket instanceof Basket ? abs((float)$basket->couponDiscount) : 0.0;

        if ($gesetzt !== $code || $rabatt <= 0.0) {
            $this->getLogger('A1GeschenkService_gutscheinSetzen')
                ->error(self::PLUGIN . '::Aktion1Log.a1GutscheinOhneWirkung', $this->kontext() + [
                    'code'   => $code,
                    'imKorb' => $gesetzt,
                    'rabatt' => $rabatt,
                ]);
            return false;
        }
        return true;
    }

    /** Sepetteki kupon bizimse kaldır (Aktion 2'nin ya da yabancı kupona dokunma). */
    private function unserenGutscheinEntfernen()
    {
        if ($this->einstellungen()->istUnserCode($this->couponImKorb())) {
            $this->basketRepository->removeCouponCode();
        }
    }

    private function geschenkAusWarenkorb(array $geschenk, array $zeilen)
    {
        $zeile = A1Regel::zeileFinden($zeilen, (int)$geschenk['variationId']);
        if ($zeile === null) {
            return;
        }
        $aktion = A1Regel::geschenkEntfernenAktion($zeile);
        if ($aktion['typ'] === A1Regel::AKTION_MENGE_SETZEN) {
            $this->mengeSetzen((int)$aktion['zeilenId'], (int)$aktion['variationId'], (float)$aktion['menge']);
        } else {
            $this->basketItemRepository->removeBasketItem((int)$aktion['zeilenId']);
        }
    }

    private function mengeSetzen(int $zeilenId, int $variationId, float $menge)
    {
        $basket = $this->basketRepository->load();
        $this->basketItemRepository->updateBasketItem($zeilenId, [
            'id'          => $zeilenId,
            'variationId' => $variationId,
            'quantity'    => $menge,
            'referrerId'  => $basket instanceof Basket ? $basket->referrerId : null,
        ]);
    }

    // ------------------------------------------------------------------
    // Durum (kutu)
    // ------------------------------------------------------------------

    private function statusBauen(string $meldung, int $itemId = 0): array
    {
        $e = $this->einstellungen();
        if (!$e->aktiv || !$this->darfTeilnehmen($e) || !$e->vollstaendig()) {
            return ['sichtbar' => false];
        }

        $coupon    = $this->couponImKorb();
        $geschenk  = $this->geschenkLesen();
        $bewertung = A1Regel::bewerten($e, true, $this->zeilen(), $geschenk, $coupon);

        $optionen = [];
        if ($bewertung['berechtigt'] && !$bewertung['fremderGutschein']) {
            $optionen = $this->katalog->optionen($e);
        }

        $geschenkInfo = null;
        if ($bewertung['geschenk'] !== null) {
            $vid    = $bewertung['geschenk']['variationId'];
            $option = $this->katalog->optionFinden($e, $vid);
            $geschenkInfo = [
                'variationId' => $vid,
                'farbe'       => $option !== null ? $option['farbe'] : '',
                'name'        => $option !== null ? $option['name'] : '',
                'preis'       => $option !== null ? $option['preis'] : '',
            ];
        }

        return [
            'sichtbar'         => true,
            'istPod'           => $itemId > 0 && $e->istPod($itemId, 0),
            'benoetigt'        => $bewertung['benoetigt'],
            'podMenge'         => $bewertung['podMenge'],
            'fehlt'            => $bewertung['fehlt'],
            'berechtigt'       => $bewertung['berechtigt'],
            'fremderGutschein' => $bewertung['fremderGutschein'],
            'andereAktion'     => $bewertung['andereAktion'],
            'maxGeschenke'     => A1Einstellungen::MAX_GESCHENKE,
            'geschenk'         => $geschenkInfo,
            'optionen'         => $optionen,
            'texte'            => $this->texte($bewertung),
            'meldung'          => $meldung !== '' ? $this->text($meldung, ['anzahl' => $e->benoetigteMenge]) : '',
        ];
    }

    private function antwort(bool $ok, string $meldung): array
    {
        try {
            $status = $this->statusBauen($ok ? 'erfolg' : $meldung);
        } catch (\Throwable $ex) {
            $this->fehlerLoggen('A1GeschenkService_antwort', $ex, []);
            $status = ['sichtbar' => false];
        }
        $status['ok'] = $ok;
        if (!isset($status['meldung'])) {
            $status['meldung'] = $meldung !== '' ? $this->text($meldung) : '';
        }
        return $status;
    }

    private function texte(array $b): array
    {
        $fehlt  = (int)$b['fehlt'];
        $anzahl = (int)$b['benoetigt'];
        $schluessel = [
            'berechtigt', 'auswahlPlatzhalter', 'hinzufuegen', 'hinzufuegenKurz', 'deinGeschenk', 'gratis',
            'aendern', 'farbeTauschen', 'entfernen', 'abbrechen', 'fremderGutschein', 'andereAktion',
            'keineOptionen', 'laden', 'fehlerAllgemein', 'fehlerKeineFarbe', 'rechtlich', 'zumWarenkorb',
        ];
        $t = [
            'titel'       => $this->text('titel', ['anzahl' => $anzahl]),
            'intro'       => $this->text('intro', ['anzahl' => $anzahl]),
            'nochFehlend' => $fehlt === 1 ? $this->text('nochFehlendEins') : $this->text('nochFehlend', ['fehlt' => $fehlt]),
            'fortschritt' => $this->text('fortschritt', ['menge' => min((int)$b['podMenge'], $anzahl), 'anzahl' => $anzahl]),
            'hinweis'     => $this->text('hinweis', ['max' => A1Einstellungen::MAX_GESCHENKE]),
        ];
        foreach ($schluessel as $s) {
            $t[$s] = $this->text($s);
        }
        return $t;
    }

    private function text(string $schluessel, array $parameter = []): string
    {
        try {
            /** @var Translator $translator */
            $translator = pluginApp(Translator::class);
            return (string)$translator->trans(self::PLUGIN . '::Aktion1.' . $schluessel, $parameter);
        } catch (\Throwable $ex) {
            return $schluessel;
        }
    }

    // ------------------------------------------------------------------
    // Yardımcılar
    // ------------------------------------------------------------------

    public function einstellungen(): A1Einstellungen
    {
        if ($this->einstellungen === null) {
            $werte = [];
            foreach (A1Einstellungen::SCHLUESSEL as $s) {
                $werte[$s] = $this->config->get(self::PLUGIN . '.' . $s);
            }
            // Aktion 2'nin kodları yalnız okunur ("Nicht mit anderer Aktion kombinierbar" mesajı için)
            $andere = [
                (string)$this->config->get(self::PLUGIN . '.gutscheinElfx'),
                (string)$this->config->get(self::PLUGIN . '.gutscheinElfliq'),
            ];
            $this->einstellungen = A1Einstellungen::ausArray($werte, $andere);
        }
        return $this->einstellungen;
    }

    /** Test modunda yalnız giriş yapmış test kontakları; test modu kapalıysa herkes (misafir dahil). */
    public function darfTeilnehmen(A1Einstellungen $e): bool
    {
        if (!$e->testmodus) {
            return true;
        }
        $kontaktId = (int)$this->contactRepository->getContactId();
        return $kontaktId > 0 && in_array($kontaktId, $e->testKontaktIds, true);
    }

    private function couponImKorb(): string
    {
        $basket = $this->basketRepository->load();
        return $basket instanceof Basket ? (string)$basket->couponCode : '';
    }

    private function zeilen(): array
    {
        $zeilen = [];
        foreach ($this->basketItemRepository->all() as $zeile) {
            if (!$zeile instanceof BasketItem) {
                continue;
            }
            $zeilen[] = [
                'id'          => (int)$zeile->id,
                'variationId' => (int)$zeile->variationId,
                'itemId'      => (int)$zeile->itemId,
                'menge'       => (float)$zeile->quantity,
                'typ'         => (int)$zeile->itemType,
            ];
        }
        return $zeilen;
    }

    private function geschenkLesen()
    {
        $roh = $this->sessionStorage->getSessionValue(self::SESSION_SCHLUESSEL);
        if (!is_string($roh) || $roh === '') {
            return null;
        }
        return A1Regel::geschenkNormalisieren(json_decode($roh, true));
    }

    private function geschenkSchreiben(array $geschenk)
    {
        $this->sessionStorage->setSessionValue(self::SESSION_SCHLUESSEL, json_encode($geschenk));
    }

    private function geschenkVergessen()
    {
        $this->sessionStorage->setSessionValue(self::SESSION_SCHLUESSEL, '');
    }

    private function kontext(): array
    {
        $kontaktId = 0;
        try {
            $kontaktId = (int)$this->contactRepository->getContactId();
        } catch (\Throwable $ex) {
            // yalnız log bağlamı
        }
        return ['kontaktId' => $kontaktId, 'aktion' => 1];
    }

    private function fehlerLoggen(string $kennung, \Throwable $ex, array $kontext)
    {
        try {
            $this->getLogger($kennung)->error(self::PLUGIN . '::Aktion1Log.a1Fehler', $kontext + [
                'meldung' => $ex->getMessage(),
                'code'    => $ex->getCode(),
                'zeile'   => $ex->getLine(),
            ]);
        } catch (\Throwable $egal) {
            // log da patlarsa sepeti etkileme
        }
    }
}
