<?php

namespace VaporAktion2Test\Helpers;

/**
 * Eklenti ayarlarının (config.json) okunmuş, temizlenmiş hâli.
 * Plenty'ye bağımlı değil: birim testlerinde doğrudan dizi ile kurulur.
 */
class Einstellungen
{
    const GRUPPE_ELFX   = 'elfx';
    const GRUPPE_ELFLIQ = 'elfliq';

    /** Sipariş başına en fazla hediye (Plenty sepetinde aynı anda tek kupon olduğu için şimdilik 1). */
    const MAX_GESCHENKE = 1;

    /** @var bool */
    public $aktiv = false;

    /** @var bool */
    public $testmodus = true;

    /** @var int[] */
    public $testKontaktIds = [];

    /** @var int[] */
    public $testKundenklassenIds = [];

    /** @var int */
    public $benoetigteMenge = 6;

    /** @var int[] */
    public $aktionsArtikelIds = [];

    /** @var int[] */
    public $aktionsVariationIds = [];

    /** @var int[] */
    public $geschenkElfxArtikelIds = [];

    /** @var bool */
    public $geschenkElfliqErlaubt = true;

    /** @var string */
    public $gutscheinElfx = '';

    /** @var string */
    public $gutscheinElfliq = '';

    /**
     * @param array $werte Anahtar => ham ayar değeri (ConfigRepository'den, önek olmadan)
     */
    public static function ausArray(array $werte): self
    {
        // Plenty "allowedCalls": new/clone yasak → kapsayıcıdan al
        /** @var self $e */
        $e = pluginApp(self::class);
        $e->aktiv                  = self::bool($werte['aktiv'] ?? false, false);
        $e->testmodus              = self::bool($werte['testmodus'] ?? true, true);
        $e->testKontaktIds         = self::idListe($werte['testKontaktIds'] ?? '');
        $e->testKundenklassenIds   = self::idListe($werte['testKundenklassenIds'] ?? '');
        $menge                     = (int)trim((string)($werte['benoetigteMenge'] ?? '6'));
        $e->benoetigteMenge        = $menge >= 1 ? $menge : 6;
        $e->aktionsArtikelIds      = self::idListe($werte['aktionsArtikelIds'] ?? '');
        $e->aktionsVariationIds    = self::idListe($werte['aktionsVariationIds'] ?? '');
        $e->geschenkElfxArtikelIds = self::idListe($werte['geschenkElfxArtikelIds'] ?? '');
        $e->geschenkElfliqErlaubt  = self::bool($werte['geschenkElfliqErlaubt'] ?? true, true);
        $e->gutscheinElfx          = self::code($werte['gutscheinElfx'] ?? '');
        $e->gutscheinElfliq        = self::code($werte['gutscheinElfliq'] ?? '');
        return $e;
    }

    /** Eklentinin kendi kupon kodları (boş olanlar hariç). */
    public function unsereCodes(): array
    {
        $codes = [];
        foreach ([$this->gutscheinElfx, $this->gutscheinElfliq] as $code) {
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        return $codes;
    }

    public function istUnserCode(string $code): bool
    {
        $code = self::code($code);
        return $code !== '' && in_array($code, $this->unsereCodes(), true);
    }

    public function codeFuerGruppe(string $gruppe): string
    {
        if ($gruppe === self::GRUPPE_ELFX) {
            return $this->gutscheinElfx;
        }
        if ($gruppe === self::GRUPPE_ELFLIQ) {
            return $this->gutscheinElfliq;
        }
        return '';
    }

    /** ELFX hediyesi seçilebilir mi (cihaz listesi + kod dolu)? */
    public function elfxMoeglich(): bool
    {
        return count($this->geschenkElfxArtikelIds) > 0 && $this->gutscheinElfx !== '';
    }

    /** ELFLIQ hediyesi seçilebilir mi (ayar açık + aktions listesi + kod dolu)? */
    public function elfliqMoeglich(): bool
    {
        return $this->geschenkElfliqErlaubt
            && (count($this->aktionsArtikelIds) > 0 || count($this->aktionsVariationIds) > 0)
            && $this->gutscheinElfliq !== '';
    }

    public function istAktionsArtikel(int $itemId, int $variationId): bool
    {
        return ($itemId > 0 && in_array($itemId, $this->aktionsArtikelIds, true))
            || ($variationId > 0 && in_array($variationId, $this->aktionsVariationIds, true));
    }

    public function istElfxArtikel(int $itemId): bool
    {
        return $itemId > 0 && in_array($itemId, $this->geschenkElfxArtikelIds, true);
    }

    /** Kupon kodlarını karşılaştırılabilir yap: boşluksuz, büyük harf. */
    public static function code($wert): string
    {
        return strtoupper(trim((string)$wert));
    }

    /**
     * checkBox değeri "true"/"1"/true gelebilir (IO PluginConfig ile aynı yorum).
     */
    public static function bool($wert, bool $standard): bool
    {
        if ($wert === null || $wert === '') {
            return $standard;
        }
        if ($wert === true || $wert === 1 || $wert === '1' || $wert === 'true') {
            return true;
        }
        return false;
    }

    /**
     * "3, 7,12" → [3, 7, 12]; geçersiz parçalar atılır. Satır sonu ve noktalı virgül de ayırıcıdır.
     */
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
