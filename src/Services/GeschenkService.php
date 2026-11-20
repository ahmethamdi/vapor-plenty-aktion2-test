<?php

namespace VaporAktion2Test\Services;

use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\BasketItem;
use Plenty\Modules\Webshop\Contracts\ContactRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

/**
 * Hediye kuralı:
 *  - Eklenti aktif + giriş yapmış müşteri test müşteri sınıfında DEĞİLSE: hiçbir şey yapma.
 *  - Sepette tetikleyici varyasyon varsa: hediye varyasyonu 1 adet ekle (yoksa).
 *  - Tetikleyici yoksa: hediye satır(lar)ını sil.
 *  - Hediye adedi her zaman 1; fazladan hediye satırları silinir.
 *  - Hediye satırının sepetteki fiyatı loglanır (asıl test sorusu: 0,00 € mu?).
 */
class GeschenkService
{
    use Loggable;

    const MODUS_VARIANTE    = 'variante';
    const MODUS_GIVEN_PRICE = 'givenPrice';

    /** @var bool Kendi ekleme/silme işlemlerimiz yeni AfterBasketChanged ateşler → döngü kilidi */
    private $laeuft = false;

    /** @var ConfigRepository */
    private $config;

    /** @var BasketItemRepositoryContract */
    private $basketItemRepository;

    /** @var BasketRepositoryContract */
    private $basketRepository;

    /** @var ContactRepositoryContract */
    private $contactRepository;

    public function __construct(
        ConfigRepository $config,
        BasketItemRepositoryContract $basketItemRepository,
        BasketRepositoryContract $basketRepository,
        ContactRepositoryContract $contactRepository
    ) {
        $this->config               = $config;
        $this->basketItemRepository = $basketItemRepository;
        $this->basketRepository     = $basketRepository;
        $this->contactRepository    = $contactRepository;
    }

    public function pruefen()
    {
        if ($this->laeuft) {
            return;
        }

        if (!$this->istAktiv()) {
            return;
        }

        $klassen  = $this->idListe('VaporAktion2Test.testKundenklassenIds');
        $kontakte = $this->idListe('VaporAktion2Test.testKontaktIds');
        if (count($klassen) === 0 && count($kontakte) === 0) {
            // İki liste de boş = hiç çalışma (güvenlik varsayılanı)
            return;
        }

        $kontaktId = (int)$this->contactRepository->getContactId();
        if ($kontaktId <= 0) {
            return; // misafir: hiçbir zaman
        }

        // Test müşteri numarası ya da test müşteri sınıfı (sınıf değiştirmek ürün görünürlüğünü bozabildiği için
        // müşteri numarası tercih edilir — 20.11.2026 Lostmary'de sınıf 61 ürünleri gizledi)
        $klasseId = (int)$this->contactRepository->getContactClassId();
        if (!in_array($kontaktId, $kontakte, true) && !in_array($klasseId, $klassen, true)) {
            return;
        }

        $ausloeserId = (int)$this->config->get('VaporAktion2Test.ausloeserVariationId');
        $geschenkId  = (int)$this->config->get('VaporAktion2Test.geschenkVariationId');
        $modus       = $this->preisModus();

        $kontext = [
            'kontaktId'      => $kontaktId,
            'kundenklasseId' => $klasseId,
            'ausloeserId'    => $ausloeserId,
            'geschenkId'     => $geschenkId,
            'modus'          => $modus,
        ];

        if ($ausloeserId <= 0 || $geschenkId <= 0 || $ausloeserId === $geschenkId) {
            $this->getLogger('GeschenkService_pruefen')
                ->error('VaporAktion2Test::Log.konfigurationUngueltig', $kontext);
            return;
        }

        $this->laeuft = true;
        try {
            $this->anwenden($ausloeserId, $geschenkId, $modus, $kontext);
        } catch (\Throwable $e) {
            $kontext['meldung'] = $e->getMessage();
            $kontext['code']    = $e->getCode();
            $kontext['klasse']  = get_class($e);
            $this->getLogger('GeschenkService_pruefen')
                ->error('VaporAktion2Test::Log.fehler', $kontext);
        } finally {
            $this->laeuft = false;
        }
    }

    private function anwenden(int $ausloeserId, int $geschenkId, string $modus, array $kontext)
    {
        $hatAusloeser    = false;
        $geschenkZeilen  = [];

        /** @var BasketItem $zeile */
        foreach ($this->basketItemRepository->all() as $zeile) {
            $variationId = (int)$zeile->variationId;
            if ($variationId === $ausloeserId && (float)$zeile->quantity > 0) {
                $hatAusloeser = true;
            }
            if ($variationId === $geschenkId) {
                $geschenkZeilen[] = $zeile;
            }
        }

        // 1) Tetikleyici yok → tüm hediye satırlarını sil
        if (!$hatAusloeser) {
            foreach ($geschenkZeilen as $zeile) {
                $this->basketItemRepository->removeBasketItem((int)$zeile->id);
                $this->getLogger('GeschenkService_entfernen')
                    ->report('VaporAktion2Test::Log.geschenkEntfernt', $kontext + ['basketItemId' => (int)$zeile->id]);
            }
            return;
        }

        // 2) Tetikleyici var, hediye yok → ekle
        if (count($geschenkZeilen) === 0) {
            $daten = $this->zeilenDaten($geschenkId, $modus);
            try {
                $neu = $this->basketItemRepository->addBasketItem($daten);
            } catch (\Throwable $e) {
                $this->getLogger('GeschenkService_hinzufuegen')
                    ->error('VaporAktion2Test::Log.hinzufuegenFehlgeschlagen', $kontext + [
                        'daten'   => $daten,
                        'meldung' => $e->getMessage(),
                        'code'    => $e->getCode(),
                        'klasse'  => get_class($e),
                    ]);
                return;
            }

            $this->getLogger('GeschenkService_hinzufuegen')
                ->report('VaporAktion2Test::Log.geschenkHinzugefuegt', $kontext + [
                    'daten' => $daten,
                    'zeile' => $this->zeilenInfo($neu),
                ]);

            // Kayıttan yeniden oku: fiyat, sepet toplamı hesaplandıktan sonra ne oldu?
            $this->preisProtokollieren((int)$neu->id, $kontext);
            return;
        }

        // 3) Hediye var → ilk satır kalır, adet 1; fazlası silinir
        $erste = array_shift($geschenkZeilen);
        foreach ($geschenkZeilen as $zeile) {
            $this->basketItemRepository->removeBasketItem((int)$zeile->id);
            $this->getLogger('GeschenkService_doppelt')
                ->report('VaporAktion2Test::Log.geschenkDoppeltEntfernt', $kontext + ['basketItemId' => (int)$zeile->id]);
        }

        if ((float)$erste->quantity !== 1.0) {
            $daten = $this->zeilenDaten($geschenkId, $modus);
            $daten['id'] = (int)$erste->id;
            $this->basketItemRepository->updateBasketItem((int)$erste->id, $daten);
            $this->getLogger('GeschenkService_menge')
                ->report('VaporAktion2Test::Log.geschenkMengeKorrigiert', $kontext + [
                    'basketItemId' => (int)$erste->id,
                    'mengeVorher'  => (float)$erste->quantity,
                ]);
        }

        $this->preisProtokollieren((int)$erste->id, $kontext);
    }

    /**
     * addBasketItem/updateBasketItem için veri.
     * IO BasketService::addDataToBasket de variationId, quantity, referrerId gönderiyor.
     */
    private function zeilenDaten(int $geschenkId, string $modus): array
    {
        $daten = [
            'variationId' => $geschenkId,
            'quantity'    => 1,
            'referrerId'  => $this->basketRepository->load()->referrerId,
        ];

        if ($modus === self::MODUS_GIVEN_PRICE) {
            // Deney: BasketItem modelindeki givenPrice/useGivenPrice alanları
            // ("The given item price" / "The flag to use the given values").
            // Core'un bunu webshop sepetinde dikkate alıp almadığı belgelenmemiş → log söyleyecek.
            $daten['givenPrice']    = 0.0;
            $daten['useGivenPrice'] = true;
        }

        return $daten;
    }

    private function preisProtokollieren(int $basketItemId, array $kontext)
    {
        $zeile = $this->basketItemRepository->findOneById($basketItemId);
        if (!$zeile instanceof BasketItem) {
            return;
        }

        $info = $kontext + ['zeile' => $this->zeilenInfo($zeile)];

        if ((float)$zeile->price > 0) {
            $this->getLogger('GeschenkService_preis')
                ->warning('VaporAktion2Test::Log.geschenkPreisNichtNull', $info);
            // warning kapalıysa da görünsün diye report ile de yaz
            $this->getLogger('GeschenkService_preis')
                ->report('VaporAktion2Test::Log.geschenkPreisNichtNull', $info);
        } else {
            $this->getLogger('GeschenkService_preis')
                ->report('VaporAktion2Test::Log.geschenkPreisNull', $info);
        }
    }

    private function zeilenInfo($zeile): array
    {
        if (!$zeile instanceof BasketItem) {
            return [];
        }

        return [
            'id'            => $zeile->id,
            'variationId'   => $zeile->variationId,
            'itemType'      => $zeile->itemType,
            'quantity'      => $zeile->quantity,
            'price'         => $zeile->price,
            'givenPrice'    => $zeile->givenPrice,
            'useGivenPrice' => $zeile->useGivenPrice,
            'rebate'        => $zeile->rebate,
            'vat'           => $zeile->vat,
            'priceId'       => $zeile->priceId,
            'bundleVariationId' => $zeile->bundleVariationId,
        ];
    }

    private function istAktiv(): bool
    {
        $wert = $this->config->get('VaporAktion2Test.aktiv');
        // checkBox değeri "true"/"1" gelebilir (IO PluginConfig ile aynı yorum)
        return $wert === true || $wert === 'true' || $wert === '1' || $wert === 1;
    }

    /**
     * "3, 7,12" → [3, 7, 12]; geçersiz parçalar atılır.
     */
    private function idListe(string $schluessel): array
    {
        $roh = (string)$this->config->get($schluessel);
        $ids = [];
        foreach (explode(',', $roh) as $teil) {
            $teil = trim($teil);
            if ($teil !== '' && preg_match('/^[0-9]+$/', $teil) === 1 && (int)$teil > 0) {
                $ids[] = (int)$teil;
            }
        }
        return array_values(array_unique($ids));
    }

    private function preisModus(): string
    {
        $wert = (string)$this->config->get('VaporAktion2Test.preisModus');
        return $wert === self::MODUS_GIVEN_PRICE ? self::MODUS_GIVEN_PRICE : self::MODUS_VARIANTE;
    }
}
