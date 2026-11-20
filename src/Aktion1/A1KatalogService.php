<?php

namespace VaporAktion2Test\Aktion1;

use Plenty\Modules\Webshop\ItemSearch\Factories\VariationSearchFactory;
use Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService;
use Plenty\Plugin\Log\Loggable;

/**
 * Seçilebilir ELFA Basisgerät renkleri: webshop arama motorundan, yalnız aktif + bu Mandant'ta görünür +
 * satılabilir (stok) + müşteriye fiyatı olan varyasyonlar (Aktion 2 KatalogService ile aynı filtreler).
 */
class A1KatalogService
{
    use Loggable;

    const MAX_TREFFER = 100;

    /** @var array|null İstek içi önbellek */
    private $cache = null;

    /**
     * @return array Option[] = ['variationId', 'itemId', 'name', 'farbe', 'preis', 'bild']
     */
    public function optionen(A1Einstellungen $e): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $liste = [];
        if (count($e->geschenkArtikelIds) === 0) {
            $this->cache = $liste;
            return $liste;
        }

        try {
            /** @var VariationSearchFactory $f */
            $f = pluginApp(VariationSearchFactory::class);
            $f->withResultFields([
                'item.id',
                'variation.id',
                'variation.name',
                'variation.number',
                'texts.name1',
                'texts.*',
                'attributes.attribute.names.*',
                'attributes.value.names.*',
                'images.all.urlPreview',
                'images.variation.urlPreview',
            ])
                ->withLanguage()
                ->withPrices()
                ->isActive()
                ->isVisibleForClient()
                ->isSalable()
                ->hasPriceForCustomer()
                ->hasItemIds($e->geschenkArtikelIds)
                ->setPage(1, self::MAX_TREFFER);

            /** @var ItemSearchService $suche */
            $suche     = pluginApp(ItemSearchService::class);
            $antworten = $suche->getResults(['geraete' => $f]);
            $antwort   = $antworten['geraete'] ?? [];

            if (is_array($antwort) && isset($antwort['documents']) && is_array($antwort['documents'])) {
                foreach ($antwort['documents'] as $dok) {
                    if (is_array($dok) && isset($dok['data']) && is_array($dok['data'])) {
                        $option = $this->option($dok['data']);
                        if ($option !== null) {
                            $liste[$option['variationId']] = $option;
                        }
                    }
                }
            }
        } catch (\Throwable $ex) {
            $this->getLogger('A1KatalogService_optionen')->error('VaporAktion2Test::Aktion1Log.a1KatalogFehler', [
                'meldung' => $ex->getMessage(),
                'code'    => $ex->getCode(),
            ]);
        }

        $liste = array_values($liste);
        usort($liste, function ($a, $b) {
            return strcmp($a['farbe'], $b['farbe']);
        });

        $this->cache = $liste;
        return $liste;
    }

    /** @return array|null Option */
    public function optionFinden(A1Einstellungen $e, int $variationId)
    {
        foreach ($this->optionen($e) as $option) {
            if ($option['variationId'] === $variationId) {
                return $option;
            }
        }
        return null;
    }

    private function option(array $d)
    {
        $variationId = (int)($d['variation']['id'] ?? 0);
        $itemId      = (int)($d['item']['id'] ?? 0);
        if ($variationId <= 0) {
            return null;
        }

        $texte = $d['texts'] ?? [];
        if (is_array($texte) && isset($texte[0]) && is_array($texte[0])) {
            $texte = $texte[0];
        }
        $name = trim((string)($texte['name1'] ?? ''));
        if ($name === '') {
            $name = trim((string)($d['variation']['name'] ?? ''));
        }

        // Renk: varyasyon özelliği varsa o, yoksa addaki son " - " parçası
        // ("ELFBAR ELFA Pod Kit 500 mAh Akku - Black" → "Black")
        $farbe = '';
        if (isset($d['attributes']) && is_array($d['attributes'])) {
            foreach ($d['attributes'] as $attr) {
                $wert = trim((string)($attr['value']['names']['name'] ?? ''));
                if ($wert !== '') {
                    $farbe = $wert;
                    break;
                }
            }
        }
        if ($farbe === '' && strpos($name, ' - ') !== false) {
            $teile = explode(' - ', $name);
            $farbe = trim((string)end($teile));
        }
        if ($farbe === '') {
            $farbe = $name !== '' ? $name : (string)($d['variation']['number'] ?? $variationId);
        }

        $bild = '';
        foreach (['variation', 'all'] as $gruppe) {
            if ($bild === '' && isset($d['images'][$gruppe][0]['urlPreview'])) {
                $bild = (string)$d['images'][$gruppe][0]['urlPreview'];
            }
        }
        if ($bild !== '' && strpos($bild, 'https://') !== 0) {
            $bild = '';
        }

        return [
            'variationId' => $variationId,
            'itemId'      => $itemId,
            'name'        => $name,
            'farbe'       => $farbe,
            'preis'       => (string)($d['prices']['default']['unitPrice']['formatted'] ?? ''),
            'bild'        => $bild,
        ];
    }
}
