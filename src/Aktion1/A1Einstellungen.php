<?php

namespace VaporAktion2Test\Aktion1;

/**
 * Aktion 1 ayarları ("Kaufe 3 ELFA Pods – 1 ELFA Basisgerät gratis").
 * Aktion 2'nin Einstellungen sınıfından bağımsız; config.json'daki "a1…" anahtarları.
 * Plenty'ye bağımlı değil: birim testlerinde doğrudan dizi ile kurulur.
 */
class A1Einstellungen
{
    /** Sipariş başına en fazla 1 hediye (Plenty sepetinde aynı anda tek kupon olur). */
    const MAX_GESCHENKE = 1;

    /** config.json anahtarları (önek olmadan) */
    const SCHLUESSEL = [
        'a1Aktiv', 'a1Testmodus', 'a1TestKontaktIds', 'a1BenoetigteMenge',
        'a1PodArtikelIds', 'a1PodVariationIds', 'a1GeschenkArtikelIds', 'a1Gutschein',
    ];

    /** @var bool */
    public $aktiv = false;

    /** @var bool */
    public $testmodus = true;

    /** @var int[] */
    public $testKontaktIds = [];

    /** @var int */
    public $benoetigteMenge = 3;

    /** @var int[] */
    public $podArtikelIds = [];

    /** @var int[] */
    public $podVariationIds = [];

    /** @var int[] */
    public $geschenkArtikelIds = [];

    /** @var string */
    public $gutschein = '';

    /** @var string[] Aktion 2'nin kupon kodları (yalnız "başka kampanya" mesajı için okunur) */
    public $andereCodes = [];

    /**
     * @param array $werte  a1… anahtarı => ham ayar değeri
     * @param array $andereCodes Aktion 2 kodları
     */
    public static function ausArray(array $werte, array $andereCodes = []): self
    {
        // Plenty "allowedCalls": new/clone yasak → kapsayıcıdan al
        /** @var self $e */
        $e = pluginApp(self::class);
        $e->aktiv              = self::bool($werte['a1Aktiv'] ?? false, false);
        $e->testmodus          = self::bool($werte['a1Testmodus'] ?? true, true);
        $e->testKontaktIds     = self::idListe($werte['a1TestKontaktIds'] ?? '');
        $menge                 = (int)trim((string)($werte['a1BenoetigteMenge'] ?? '3'));
        $e->benoetigteMenge    = $menge >= 1 ? $menge : 3;
        $e->podArtikelIds      = self::idListe($werte['a1PodArtikelIds'] ?? '');
        $e->podVariationIds    = self::idListe($werte['a1PodVariationIds'] ?? '');
        $e->geschenkArtikelIds = self::idListe($werte['a1GeschenkArtikelIds'] ?? '');
        $e->gutschein          = self::code($werte['a1Gutschein'] ?? '');

        $codes = [];
        foreach ($andereCodes as $c) {
            $c = self::code($c);
            if ($c !== '' && $c !== $e->gutschein) {
                $codes[] = $c;
            }
        }
        $e->andereCodes = $codes;
        return $e;
    }

    /** Kampanya kurulmuş mu (pod listesi + cihaz listesi + kupon kodu)? Kod boşsa modül hiçbir şey yapmaz. */
    public function vollstaendig(): bool
    {
        return $this->gutschein !== ''
            && count($this->geschenkArtikelIds) > 0
            && (count($this->podArtikelIds) > 0 || count($this->podVariationIds) > 0);
    }

    public function istUnserCode(string $code): bool
    {
        $code = self::code($code);
        return $code !== '' && $code === $this->gutschein;
    }

    public function istAndereAktion(string $code): bool
    {
        $code = self::code($code);
        return $code !== '' && in_array($code, $this->andereCodes, true);
    }

    public function istPod(int $itemId, int $variationId): bool
    {
        return ($itemId > 0 && in_array($itemId, $this->podArtikelIds, true))
            || ($variationId > 0 && in_array($variationId, $this->podVariationIds, true));
    }

    public function istGeschenkArtikel(int $itemId): bool
    {
        return $itemId > 0 && in_array($itemId, $this->geschenkArtikelIds, true);
    }

    public static function code($wert): string
    {
        return strtoupper(trim((string)$wert));
    }

    public static function bool($wert, bool $standard): bool
    {
        if ($wert === null || $wert === '') {
            return $standard;
        }
        return $wert === true || $wert === 1 || $wert === '1' || $wert === 'true';
    }

    /** "3, 7,12" → [3, 7, 12]; satır sonu / noktalı virgül / boşluk da ayırıcı. */
    public static function idListe($roh): array
    {
        $roh = str_replace([';', "\n", "\r", "\t", ' '], ',', (string)$roh);
        $ids = [];
        foreach (explode(',', $roh) as $teil) {
            $teil = trim($teil);
            if ($teil !== '' && preg_match('/^[0-9]+$/', $teil) === 1 && (int)$teil > 0) {
                $ids[] = (int)$teil;
            }
        }
        return array_values(array_unique($ids));
    }
}
