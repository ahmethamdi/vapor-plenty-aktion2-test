<?php
// Yerel test için sahte Plenty / IO sınıfları (yalnız eklentinin kullandığı yüzey).
// Plenty'de bu dosya çalışmaz; tests/ klasörü src/ dışında, IO eklentisinde de aynı düzen var.

namespace Plenty\Plugin\Log {
    trait Loggable
    {
        protected function getLogger($kennung)
        {
            return new \TestLogger($kennung);
        }
    }
}

namespace Plenty\Plugin {
    class ConfigRepository
    {
        public $werte = [];
        public function get($schluessel, $standard = null)
        {
            return array_key_exists($schluessel, $this->werte) ? $this->werte[$schluessel] : $standard;
        }
    }
}

namespace Plenty\Plugin\Translation {
    class Translator
    {
        public function trans($schluessel, $parameter = [])
        {
            $text = $schluessel;
            foreach ($parameter as $k => $v) {
                $text .= ' ' . $k . '=' . $v;
            }
            return $text;
        }
    }
}

namespace Plenty\Modules\Basket\Models {
    class Basket
    {
        public $couponCode = '';
        public $couponDiscount = 0.0;
        public $referrerId = 1;
    }
    class BasketItem
    {
        const BASKET_ITEM_TYPE_VARIATION = 10;
        public $id;
        public $variationId;
        public $itemId;
        public $quantity;
        public $itemType = 10;
    }
}

namespace Plenty\Modules\Basket\Contracts {
    use Plenty\Modules\Basket\Models\Basket;
    use Plenty\Modules\Basket\Models\BasketItem;

    interface BasketRepositoryContract
    {
        public function load(bool $forceCreate = false): Basket;
        public function setCouponCode(string $couponCode): Basket;
        public function removeCouponCode(): Basket;
    }
    interface BasketItemRepositoryContract
    {
        public function all(): array;
        public function addBasketItem(array $data, bool $fireEvents = true): BasketItem;
        public function updateBasketItem(int $basketItemId, array $data, bool $fireEvents = true);
        public function removeBasketItem(int $basketItemId, bool $dispatchAfterBasketChangedEvent = true);
    }
}

namespace Plenty\Modules\Webshop\Contracts {
    interface ContactRepositoryContract
    {
        public function getContactId(): int;
        public function getContactClassId();
    }
    interface SessionStorageRepositoryContract
    {
        public function setSessionValue(string $key, $value);
        public function getSessionValue(string $key);
    }
}

namespace IO\Services {
    class BasketService
    {
        public function addBasketItem(array $data, $fireEvent = true): array
        {
            return \FakeWelt::$ich->ioAdd($data);
        }
    }
}

namespace {
    use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
    use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
    use Plenty\Modules\Basket\Models\Basket;
    use Plenty\Modules\Basket\Models\BasketItem;
    use Plenty\Modules\Webshop\Contracts\ContactRepositoryContract;
    use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;

    class TestLogger
    {
        public static $eintraege = [];
        private $kennung;
        public function __construct($kennung) { $this->kennung = $kennung; }
        public function __call($stufe, $args)
        {
            self::$eintraege[] = [$stufe, $this->kennung, $args[0] ?? '', $args[1] ?? []];
            return $this;
        }
        public static function enthaelt(string $teil): bool
        {
            foreach (self::$eintraege as $e) {
                if (strpos($e[2], $teil) !== false) {
                    return true;
                }
            }
            return false;
        }
    }

    function pluginApp($klasse)
    {
        if ($klasse === \Plenty\Plugin\Translation\Translator::class) {
            return new \Plenty\Plugin\Translation\Translator();
        }
        if ($klasse === \IO\Services\BasketService::class) {
            return new \IO\Services\BasketService();
        }
        throw new \RuntimeException('pluginApp: ' . $klasse);
    }

    /**
     * Sahte sepet + oturum + kupon motoru.
     * Kupon kuralı (Plenty'deki Aktionsgutschein'ı taklit eder): kod biliniyorsa ve filtre artikeli sepetteyse
     * couponDiscount = -festbetrag, yoksa setCouponCode istisna fırlatır.
     */
    class FakeWelt implements BasketRepositoryContract, BasketItemRepositoryContract, ContactRepositoryContract, SessionStorageRepositoryContract
    {
        public static $ich;
        public $zeilen = [];       // id => BasketItem
        public $naechsteId = 100;
        public $coupon = '';
        public $kupons = [];       // CODE => ['itemId' => int, 'betrag' => float]
        public $session = [];
        public $kontaktId = 0;
        public $klasseId = 0;
        public $addFehler = null;  // IO addBasketItem hata kodu
        public $artikelVon = [];   // variationId => itemId
        public $ereignisse = 0;    // removeBasketItem/updateBasketItem çağrı sayısı

        public function __construct() { self::$ich = $this; }

        public function zeile(int $variationId, float $menge, int $typ = 10): int
        {
            $z = new BasketItem();
            $z->id = $this->naechsteId++;
            $z->variationId = $variationId;
            $z->itemId = $this->artikelVon[$variationId] ?? $variationId * 10;
            $z->quantity = $menge;
            $z->itemType = $typ;
            $this->zeilen[$z->id] = $z;
            return $z->id;
        }

        public function menge(int $variationId): float
        {
            foreach ($this->zeilen as $z) {
                if ($z->variationId === $variationId) {
                    return (float)$z->quantity;
                }
            }
            return 0.0;
        }

        // BasketRepositoryContract
        public function load(bool $forceCreate = false): Basket
        {
            $b = new Basket();
            $b->couponCode = $this->coupon;
            $b->couponDiscount = $this->rabatt();
            return $b;
        }
        public function setCouponCode(string $couponCode): Basket
        {
            $code = strtoupper($couponCode);
            if (!isset($this->kupons[$code]) || !$this->hatArtikel($this->kupons[$code]['itemIds'])) {
                throw new \Exception('Coupon invalid', 1);
            }
            $this->coupon = $code;
            return $this->load();
        }
        public function removeCouponCode(): Basket
        {
            $this->coupon = '';
            return $this->load();
        }
        private function rabatt(): float
        {
            if ($this->coupon === '' || !isset($this->kupons[$this->coupon])) {
                return 0.0;
            }
            return $this->hatArtikel($this->kupons[$this->coupon]['itemIds']) ? -$this->kupons[$this->coupon]['betrag'] : 0.0;
        }
        private function hatArtikel(array $itemIds): bool
        {
            foreach ($this->zeilen as $z) {
                if (in_array((int)$z->itemId, $itemIds, true)) {
                    return true;
                }
            }
            return false;
        }

        // BasketItemRepositoryContract
        public function all(): array { return array_values($this->zeilen); }
        public function addBasketItem(array $data, bool $fireEvents = true): BasketItem
        {
            $id = $this->zeile((int)$data['variationId'], (float)$data['quantity']);
            return $this->zeilen[$id];
        }
        public function updateBasketItem(int $basketItemId, array $data, bool $fireEvents = true)
        {
            $this->ereignisse++;
            $this->zeilen[$basketItemId]->quantity = (float)$data['quantity'];
        }
        public function removeBasketItem(int $basketItemId, bool $dispatchAfterBasketChangedEvent = true)
        {
            $this->ereignisse++;
            unset($this->zeilen[$basketItemId]);
            if ($this->coupon !== '' && $this->rabatt() == 0.0) {
                $this->coupon = ''; // Plenty düşürür (canlıda gözlendi)
            }
        }

        // IO BasketService::addBasketItem taklidi (aynı varyasyon → adet toplanır)
        public function ioAdd(array $data): array
        {
            if ($this->addFehler !== null) {
                return ['code' => $this->addFehler];
            }
            foreach ($this->zeilen as $z) {
                if ($z->variationId === (int)$data['variationId']) {
                    $z->quantity += (float)$data['quantity'];
                    return [];
                }
            }
            $this->zeile((int)$data['variationId'], (float)$data['quantity']);
            return [];
        }

        // ContactRepositoryContract
        public function getContactId(): int { return $this->kontaktId; }
        public function getContactClassId() { return $this->klasseId; }

        // SessionStorageRepositoryContract
        public function setSessionValue(string $key, $value) { $this->session[$key] = $value; }
        public function getSessionValue(string $key) { return $this->session[$key] ?? null; }
    }
}
