<?php

namespace VaporAktion2\Services;

use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Basket\Models\BasketItem;
use Plenty\Modules\Webshop\Contracts\ContactRepositoryContract;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Translation\Translator;
use VaporAktion2\Helpers\Einstellungen;
use VaporAktion2\Helpers\Regel;

/**
 * "6 Aktions-Artikel al → 1 hediye" kampanyasını Plenty sepetinde yürütür.
 *
 * Hediye = gerçek varyasyon, tam fiyatla sepette + panelde açılmış Aktionsgutschein (Festbetrag = hediye fiyatı,
 * Artikel filtresi = hediye ürünü). 20.11.2026 canlı testinde kanıtlanan tek çalışan yol bu.
 *
 * Giriş noktaları:
 *  - pruefen():   AfterBasketChanged / ValidateCheckoutEvent / durum sorgusu → sepeti kurala uydur
 *  - status():    sepet kutusu için durum + seçenekler + metinler
 *  - waehlen():   müşteri hediye seçti/değiştirdi
 *  - entfernen(): müşteri hediyeyi kaldırdı
 *
 * Kural: hata asla sepeti bozmamalı → her yol try/catch, hata loglanır, sessizce çıkılır.
 */
class GeschenkService
{
    use Loggable;

    const SESSION_SCHLUESSEL = 'VaporAktion2_geschenk';
    const PLUGIN             = 'VaporAktion2';

    /** @var bool Kendi sepet işlemlerimiz yeni AfterBasketChanged ateşler → döngü kilidi */
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

    /** @var KatalogService */
    private $katalog;

    /** @var Einstellungen|null */
    private $einstellungen = null;

    public function __construct(
        ConfigRepository $config,
        BasketItemRepositoryContract $basketItemRepository,
        BasketRepositoryContract $basketRepository,
        ContactRepositoryContract $contactRepository,
        SessionStorageRepositoryContract $sessionStorage,
        KatalogService $katalog
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
     * @return bool true = sepet içeriği/kuponu müşteri aleyhine ya da lehine değişti (kasada uyarı gerekir)
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
            $this->fehlerLoggen('GeschenkService_pruefen', $ex, ['anlass' => $anlass]);
            return false;
        } finally {
            $this->laeuft = false;
        }
    }

    /**
     * Sepet kutusu için durum.
     */
    public function status(): array
    {
        $this->pruefen('status');

        try {
            return $this->statusBauen('');
        } catch (\Throwable $ex) {
            $this->fehlerLoggen('GeschenkService_status', $ex, []);
            return ['sichtbar' => false];
        }
    }

    /**
     * Müşteri bir hediye seçti (ya da mevcut hediyeyi değiştirdi).
     */
    public function waehlen(int $variationId): array
    {
        if ($this->laeuft) {
            return $this->antwort(false, 'fehlerAllgemein');
        }

        $this->laeuft = true;
        try {
            $meldung = $this->waehlenIntern($variationId);
        } catch (\Throwable $ex) {
            $this->fehlerLoggen('GeschenkService_waehlen', $ex, ['variationId' => $variationId]);
            $meldung = 'fehlerAllgemein';
        } finally {
            $this->laeuft = false;
        }

        return $this->antwort($meldung === 'erfolg', $meldung);
    }

    /**
     * Müşteri hediyeyi kaldırdı: hediye satırı (ELFLIQ'te 1 adet) + kupon + oturum kaydı.
     */
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
                $this->getLogger('GeschenkService_entfernen')
                    ->report(self::PLUGIN . '::Log.geschenkVomKundenEntfernt', $this->kontext() + ['geschenk' => $geschenk]);
            }
        } catch (\Throwable $ex) {
            $this->fehlerLoggen('GeschenkService_entfernen', $ex, []);
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
        $basket   = $this->basketRepository->load();
        $coupon   = $basket instanceof Basket ? (string)$basket->couponCode : '';

        // Hızlı çıkış: eklenti kapalı, oturumda hediye yok, sepetteki kupon bizim değil → hiçbir şey yapma
        if (!$e->aktiv && $geschenk === null && !$e->istUnserCode($coupon)) {
            return false;
        }

        $darf      = $this->darfTeilnehmen($e);
        $zeilen    = $this->zeilen();
        $bewertung = Regel::bewerten($e, $darf, $zeilen, $geschenk, $coupon);

        if (count($bewertung['aktionen']) === 0) {
            return false;
        }

        $kontext = $this->kontext() + [
            'anlass'       => $anlass,
            'grund'        => $bewertung['grund'],
            'aktionsMenge' => $bewertung['aktionsMenge'],
            'bezahlt'      => $bewertung['bezahlt'],
            'benoetigt'    => $bewertung['benoetigt'],
            'coupon'       => $coupon,
            'geschenk'     => $geschenk,
            'aktionen'     => $bewertung['aktionen'],
        ];

        $geaendert = $this->ausfuehren($bewertung['aktionen'], $zeilen, $kontext);

        $this->getLogger('GeschenkService_abgleichen')
            ->report(self::PLUGIN . '::Log.' . $bewertung['grund'], $kontext + ['geaendert' => $geaendert]);

        return $geaendert;
    }

    /**
     * Regel'in işlem listesini Plenty'de yürüt. Her işlem kendi try/catch'inde: biri patlarsa diğerleri yine denenir.
     * @return bool Sepet içeriği ya da kupon değişti mi (yalnız oturum kaydını unutmak sayılmaz)
     */
    private function ausfuehren(array $aktionen, array $zeilen, array $kontext): bool
    {
        $geaendert = false;

        foreach ($aktionen as $aktion) {
            try {
                switch ($aktion['typ']) {
                    case Regel::AKTION_ZEILE_ENTFERNEN:
                        $this->basketItemRepository->removeBasketItem((int)$aktion['zeilenId']);
                        $geaendert = true;
                        break;

                    case Regel::AKTION_MENGE_SETZEN:
                        $this->mengeSetzen((int)$aktion['zeilenId'], (int)$aktion['variationId'], (float)$aktion['menge']);
                        $geaendert = true;
                        break;

                    case Regel::AKTION_GUTSCHEIN_WEG:
                        $this->basketRepository->removeCouponCode();
                        $geaendert = true;
                        break;

                    case Regel::AKTION_GUTSCHEIN_SETZEN:
                        if (!$this->gutscheinSetzen((string)$aktion['code'])) {
                            // Kupon tutmadı: hediye tam fiyatla kalmasın
                            $geschenk = $this->geschenkLesen();
                            if ($geschenk !== null) {
                                $this->geschenkAusWarenkorb($geschenk, $this->zeilen());
                                $this->geschenkVergessen();
                            }
                            $this->unserenGutscheinEntfernen();
                            $this->getLogger('GeschenkService_ausfuehren')
                                ->error(self::PLUGIN . '::Log.gutscheinFehlgeschlagenGeschenkEntfernt', $kontext);
                            $geaendert = true;
                        }
                        break;

                    case Regel::AKTION_VERGESSEN:
                        $this->geschenkVergessen();
                        break;
                }
            } catch (\Throwable $ex) {
                $this->fehlerLoggen('GeschenkService_ausfuehren', $ex, $kontext + ['aktion' => $aktion]);
            }
        }

        return $geaendert;
    }

    private function waehlenIntern(int $variationId): string
    {
        $e = $this->einstellungen();
        $darf = $this->darfTeilnehmen($e);
        if (!$e->aktiv || !$darf || $variationId <= 0) {
            return 'fehlerNichtVerfuegbar';
        }

        // Önce sepeti kurala uydur (eski, geçersiz hediye vb. temizlensin)
        $this->abgleichen('waehlen');

        $option = $this->katalog->optionFinden($e, $variationId);
        if ($option === null) {
            $this->getLogger('GeschenkService_waehlen')
                ->info(self::PLUGIN . '::Log.optionUngueltig', $this->kontext() + ['variationId' => $variationId]);
            return 'fehlerNichtVerfuegbar';
        }

        $basket = $this->basketRepository->load();
        $coupon = $basket instanceof Basket ? (string)$basket->couponCode : '';
        $zeilen = $this->zeilen();
        $alt    = $this->geschenkLesen();

        $fehler = Regel::wahlPruefen($e, $darf, $zeilen, $alt, $coupon, $option);
        if ($fehler !== '') {
            $this->getLogger('GeschenkService_waehlen')
                ->info(self::PLUGIN . '::Log.wahlAbgelehnt', $this->kontext() + [
                    'variationId' => $variationId,
                    'fehler'      => $fehler,
                    'coupon'      => $coupon,
                ]);
            return $fehler;
        }

        if ($alt !== null && $alt['variationId'] === $option['variationId'] && $alt['gruppe'] === $option['gruppe']) {
            return 'erfolg'; // aynı hediye zaten sepette
        }

        // Eski hediyeyi çıkar
        if ($alt !== null) {
            $this->geschenkAusWarenkorb($alt, $zeilen);
            $this->geschenkVergessen();
            $this->unserenGutscheinEntfernen();
        }

        // Yeni hediyeyi IO üzerinden ekle (IO paket bölme, aynı varyasyonu birleştirme ve stok kontrolünü yapar)
        /** @var \IO\Services\BasketService $ioBasket */
        $ioBasket = pluginApp(\IO\Services\BasketService::class);
        $sonuc    = $ioBasket->addBasketItem(['variationId' => $option['variationId'], 'quantity' => 1]);
        if (is_array($sonuc) && array_key_exists('code', $sonuc)) {
            $this->getLogger('GeschenkService_waehlen')
                ->error(self::PLUGIN . '::Log.hinzufuegenFehlgeschlagen', $this->kontext() + [
                    'option' => $option,
                    'code'   => $sonuc['code'],
                ]);
            return 'fehlerHinzufuegen';
        }

        $geschenk = ['variationId' => $option['variationId'], 'gruppe' => $option['gruppe']];
        $this->geschenkSchreiben($geschenk);

        if (!$this->gutscheinSetzen($e->codeFuerGruppe($option['gruppe']))) {
            $this->geschenkAusWarenkorb($geschenk, $this->zeilen());
            $this->geschenkVergessen();
            $this->unserenGutscheinEntfernen();
            $this->getLogger('GeschenkService_waehlen')
                ->error(self::PLUGIN . '::Log.gutscheinFehlgeschlagenGeschenkEntfernt', $this->kontext() + ['option' => $option]);
            return 'fehlerGutschein';
        }

        $this->getLogger('GeschenkService_waehlen')
            ->report(self::PLUGIN . '::Log.geschenkGewaehlt', $this->kontext() + [
                'option'  => $option,
                'vorher'  => $alt,
            ]);

        return 'erfolg';
    }

    /**
     * Kupon kodunu sepete uygula ve gerçekten indirim doğurduğunu doğrula.
     */
    private function gutscheinSetzen(string $code): bool
    {
        $code = Einstellungen::code($code);
        if ($code === '') {
            return false;
        }

        try {
            $basket = $this->basketRepository->setCouponCode($code);
        } catch (\Throwable $ex) {
            $this->fehlerLoggen('GeschenkService_gutscheinSetzen', $ex, ['code' => $code]);
            return false;
        }

        if (!$basket instanceof Basket) {
            $basket = $this->basketRepository->load();
        }

        $gesetzt  = $basket instanceof Basket ? Einstellungen::code($basket->couponCode) : '';
        $rabatt   = $basket instanceof Basket ? abs((float)$basket->couponDiscount) : 0.0;

        if ($gesetzt !== $code || $rabatt <= 0.0) {
            $this->getLogger('GeschenkService_gutscheinSetzen')
                ->error(self::PLUGIN . '::Log.gutscheinOhneWirkung', $this->kontext() + [
                    'code'       => $code,
                    'imKorb'     => $gesetzt,
                    'rabatt'     => $rabatt,
                ]);
            return false;
        }

        return true;
    }

    /** Sepetteki kupon bizimse kaldır (yabancı kupona dokunma). */
    private function unserenGutscheinEntfernen()
    {
        $basket = $this->basketRepository->load();
        $coupon = $basket instanceof Basket ? (string)$basket->couponCode : '';
        if ($this->einstellungen()->istUnserCode($coupon)) {
            $this->basketRepository->removeCouponCode();
        }
    }

    /** Hediyeyi sepetten çıkar (ELFLIQ: 1 adet azalt; ELFX: satırı sil). */
    private function geschenkAusWarenkorb(array $geschenk, array $zeilen)
    {
        $zeile = Regel::zeileFinden($zeilen, (int)$geschenk['variationId']);
        if ($zeile === null) {
            return;
        }
        $aktion = Regel::geschenkEntfernenAktion($geschenk, $zeile);
        if ($aktion['typ'] === Regel::AKTION_MENGE_SETZEN) {
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
    // Durum (sepet kutusu)
    // ------------------------------------------------------------------

    private function statusBauen(string $meldung): array
    {
        $e = $this->einstellungen();
        if (!$e->aktiv || !$this->darfTeilnehmen($e) || (!$e->elfxMoeglich() && !$e->elfliqMoeglich())) {
            return ['sichtbar' => false];
        }

        $basket    = $this->basketRepository->load();
        $coupon    = $basket instanceof Basket ? (string)$basket->couponCode : '';
        $geschenk  = $this->geschenkLesen();
        $bewertung = Regel::bewerten($e, true, $this->zeilen(), $geschenk, $coupon);

        $optionen = [Einstellungen::GRUPPE_ELFX => [], Einstellungen::GRUPPE_ELFLIQ => []];
        if ($bewertung['berechtigt'] && !$bewertung['fremderGutschein']) {
            $optionen = $this->katalog->optionen($e);
        }

        $geschenkInfo = null;
        if ($bewertung['geschenk'] !== null) {
            $g = $bewertung['geschenk'];
            $option = $this->katalog->optionFinden($e, $g['variationId']);
            $geschenkInfo = [
                'variationId' => $g['variationId'],
                'gruppe'      => $g['gruppe'],
                'name'        => $option !== null ? $option['name'] : '',
                'preis'       => $option !== null ? $option['preis'] : '',
            ];
        }

        return [
            'sichtbar'         => true,
            'benoetigt'        => $bewertung['benoetigt'],
            'bezahlt'          => $bewertung['bezahlt'],
            'fehlt'            => $bewertung['fehlt'],
            'berechtigt'       => $bewertung['berechtigt'],
            'fremderGutschein' => $bewertung['fremderGutschein'],
            'maxGeschenke'     => Einstellungen::MAX_GESCHENKE,
            'geschenk'         => $geschenkInfo,
            'optionen'         => $optionen,
            'texte'            => $this->texte($bewertung),
            'meldung'          => $meldung !== '' ? $this->text($meldung) : '',
        ];
    }

    private function antwort(bool $ok, string $meldung): array
    {
        try {
            $status = $this->statusBauen($ok ? 'erfolg' : $meldung);
        } catch (\Throwable $ex) {
            $this->fehlerLoggen('GeschenkService_antwort', $ex, []);
            $status = ['sichtbar' => false];
        }
        $status['ok'] = $ok;
        if (!isset($status['meldung'])) {
            $status['meldung'] = $meldung !== '' ? $this->text($meldung) : '';
        }
        return $status;
    }

    private function texte(array $bewertung): array
    {
        $fehlt = (int)$bewertung['fehlt'];
        return [
            'titel'              => $this->text('titel', ['anzahl' => $bewertung['benoetigt']]),
            'nochFehlend'        => $fehlt === 1 ? $this->text('nochFehlendEins') : $this->text('nochFehlend', ['fehlt' => $fehlt]),
            'fortschritt'        => $this->text('fortschritt', ['bezahlt' => $bewertung['bezahlt'], 'anzahl' => $bewertung['benoetigt']]),
            'berechtigt'         => $this->text('berechtigt'),
            'gruppeElfx'         => $this->text('gruppeElfx'),
            'gruppeElfliq'       => $this->text('gruppeElfliq'),
            'auswahlPlatzhalter' => $this->text('auswahlPlatzhalter'),
            'hinzufuegen'        => $this->text('hinzufuegen'),
            'deinGeschenk'       => $this->text('deinGeschenk'),
            'gratis'             => $this->text('gratis'),
            'statt'              => $this->text('statt'),
            'aendern'            => $this->text('aendern'),
            'entfernen'          => $this->text('entfernen'),
            'abbrechen'          => $this->text('abbrechen'),
            'fremderGutschein'   => $this->text('fremderGutschein'),
            'keineOptionen'      => $this->text('keineOptionen'),
            'hinweis'            => $this->text('hinweis'),
            'laden'              => $this->text('laden'),
            'fehlerAllgemein'    => $this->text('fehlerAllgemein'),
        ];
    }

    private function text(string $schluessel, array $parameter = []): string
    {
        try {
            /** @var Translator $translator */
            $translator = pluginApp(Translator::class);
            return (string)$translator->trans(self::PLUGIN . '::Frontend.' . $schluessel, $parameter);
        } catch (\Throwable $ex) {
            return $schluessel;
        }
    }

    // ------------------------------------------------------------------
    // Yardımcılar
    // ------------------------------------------------------------------

    public function einstellungen(): Einstellungen
    {
        if ($this->einstellungen === null) {
            $werte = [];
            foreach ([
                'aktiv', 'testmodus', 'testKontaktIds', 'testKundenklassenIds', 'benoetigteMenge',
                'aktionsArtikelIds', 'aktionsVariationIds', 'geschenkElfxArtikelIds', 'geschenkElfliqErlaubt',
                'gutscheinElfx', 'gutscheinElfliq',
            ] as $schluessel) {
                $werte[$schluessel] = $this->config->get(self::PLUGIN . '.' . $schluessel);
            }
            $this->einstellungen = Einstellungen::ausArray($werte);
        }
        return $this->einstellungen;
    }

    /**
     * Test modunda yalnız test kontakları / test müşteri sınıfları (giriş yapmış) katılır.
     * Test modu kapalıysa herkes (misafir dahil).
     */
    public function darfTeilnehmen(Einstellungen $e): bool
    {
        if (!$e->testmodus) {
            return true;
        }
        $kontaktId = (int)$this->contactRepository->getContactId();
        if ($kontaktId <= 0) {
            return false;
        }
        if (in_array($kontaktId, $e->testKontaktIds, true)) {
            return true;
        }
        $klasseId = (int)$this->contactRepository->getContactClassId();
        return $klasseId > 0 && in_array($klasseId, $e->testKundenklassenIds, true);
    }

    /** Sepet satırları, Regel'in beklediği sade biçimde. */
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
        $daten = json_decode($roh, true);
        return Regel::geschenkNormalisieren($daten);
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
            // log bağlamı için; hata önemsiz
        }
        return ['kontaktId' => $kontaktId];
    }

    private function fehlerLoggen(string $kennung, \Throwable $ex, array $kontext)
    {
        try {
            $this->getLogger($kennung)->error(self::PLUGIN . '::Log.fehler', $kontext + [
                'meldung' => $ex->getMessage(),
                'code'    => $ex->getCode(),
                'zeile'   => $ex->getLine(),
            ]);
        } catch (\Throwable $egal) {
            // log da patlarsa sepeti etkileme
        }
    }
}
