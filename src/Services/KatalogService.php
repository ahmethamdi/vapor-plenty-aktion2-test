<?php

namespace VaporAktion2Test\Services;

use Plenty\Modules\Webshop\ItemSearch\Factories\VariationSearchFactory;
use Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService;
use Plenty\Plugin\Log\Loggable;
use VaporAktion2Test\Helpers\Einstellungen;

/**
 * Hediye seçeneklerini webshop arama motorundan (Elasticsearch) okur.
 * Yalnız aktif, bu Mandant'ta görünür, satılabilir ve müşteriye fiyatı olan varyasyonlar gelir
 * (IO VariationList preset'iyle aynı filtreler; kategori listesinde gizli olanlar da kabul).
 */
class KatalogService
{
    use Loggable;

    const MAX_TREFFER = 250;

    /** @var array|null İstek içi önbellek */
    private $cache = null;

    /**
     * @return array ['elfx' => Option[], 'elfliq' => Option[]]
     *               Option = ['variationId', 'itemId', 'gruppe', 'name', 'preis']
     */
    public function optionen(Einstellungen $e): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $ergebnis = [Einstellungen::GRUPPE_ELFX => [], Einstellungen::GRUPPE_ELFLIQ => []];

        try {
            $suchen = [];
            if ($e->elfxMoeglich()) {
                $suchen['elfx'] = $this->fabrik()->hasItemIds($e->geschenkElfxArtikelIds);
            }
            if ($e->elfliqMoeglich()) {
                if (count($e->aktionsArtikelIds) > 0) {
                    $suchen['elfliqArtikel'] = $this->fabrik()->hasItemIds($e->aktionsArtikelIds);
                }
                if (count($e->aktionsVariationIds) > 0) {
                    $suchen['elfliqVariationen'] = $this->fabrik()->hasVariationIds($e->aktionsVariationIds);
                }
            }

            if (count($suchen) === 0) {
                $this->cache = $ergebnis;
                return $ergebnis;
            }

            /** @var ItemSearchService $suche */
            $suche     = pluginApp(ItemSearchService::class);
            $antworten = $suche->getResults($suchen);

            foreach ($antworten as $schluessel => $antwort) {
                $gruppe = $schluessel === 'elfx' ? Einstellungen::GRUPPE_ELFX : Einstellungen::GRUPPE_ELFLIQ;
                foreach ($this->dokumente($antwort) as $daten) {
                    $option = $this->option($daten, $gruppe);
                    if ($option !== null) {
                        $ergebnis[$gruppe][$option['variationId']] = $option;
                    }
                }
            }
        } catch (\Throwable $ex) {
            $this->getLogger('KatalogService_optionen')->error('VaporAktion2Test::Log.katalogFehler', [
                'meldung' => $ex->getMessage(),
                'code'    => $ex->getCode(),
            ]);
        }

        foreach ($ergebnis as $gruppe => $liste) {
            $liste = array_values($liste);
            usort($liste, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            $ergebnis[$gruppe] = $liste;
        }

        $this->cache = $ergebnis;
        return $ergebnis;
    }

    /**
     * Seçilen varyasyon geçerli bir hediye seçeneği mi?
     * @return array|null Option
     */
    public function optionFinden(Einstellungen $e, int $variationId)
    {
        foreach ($this->optionen($e) as $liste) {
            foreach ($liste as $option) {
                if ($option['variationId'] === $variationId) {
                    return $option;
                }
            }
        }
        return null;
    }

    public function name(Einstellungen $e, int $variationId): string
    {
        $option = $this->optionFinden($e, $variationId);
        return $option !== null ? $option['name'] : '';
    }

    private function fabrik()
    {
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
        ])
            ->withLanguage()
            ->withPrices()
            ->isActive()
            ->isVisibleForClient()
            ->isSalable()
            ->hasPriceForCustomer()
            ->setPage(1, self::MAX_TREFFER);
        return $f;
    }

    private function dokumente($antwort): array
    {
        $liste = [];
        if (!is_array($antwort) || !isset($antwort['documents']) || !is_array($antwort['documents'])) {
            return $liste;
        }
        foreach ($antwort['documents'] as $dok) {
            if (is_array($dok) && isset($dok['data']) && is_array($dok['data'])) {
                $liste[] = $dok['data'];
            }
        }
        return $liste;
    }

    private function option(array $d, string $gruppe)
    {
        $variationId = (int)($d['variation']['id'] ?? 0);
        $itemId      = (int)($d['item']['id'] ?? 0);
        if ($variationId <= 0) {
            return null;
        }

        // texts tek nesne ya da dil listesi olarak gelebilir (20.11.: Lostmary'de name1 boş geldi → "– 10 mg/ml")
        $texte = $d['texts'] ?? [];
        if (is_array($texte) && isset($texte[0]) && is_array($texte[0])) {
            $texte = $texte[0];
        }
        $name = trim((string)($texte['name1'] ?? ''));
        if ($name === '') {
            $name = trim((string)($d['variation']['name'] ?? ''));
        }
        $werte = [];
        if (isset($d['attributes']) && is_array($d['attributes'])) {
            foreach ($d['attributes'] as $attr) {
                $wert = trim((string)($attr['value']['names']['name'] ?? ''));
                if ($wert !== '') {
                    $werte[] = $wert;
                }
            }
        }
        if (count($werte) > 0) {
            $name = trim($name . ' – ' . implode(' / ', $werte));
        }
        if ($name === '') {
            $name = (string)($d['variation']['number'] ?? $variationId);
        }

        $preis = (string)($d['prices']['default']['unitPrice']['formatted'] ?? '');

        return [
            'variationId' => $variationId,
            'itemId'      => $itemId,
            'gruppe'      => $gruppe,
            'name'        => $name,
            'preis'       => $preis,
        ];
    }
}
