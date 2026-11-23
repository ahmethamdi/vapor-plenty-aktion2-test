<?php
// Kullanım: php tests/run.php   (Plenty'siz yerel birim testleri)

require __DIR__ . '/stubs.php';

spl_autoload_register(function ($klasse) {
    $onek = 'VaporAktion2\\';
    if (strpos($klasse, $onek) === 0) {
        $dosya = __DIR__ . '/../src/' . str_replace('\\', '/', substr($klasse, strlen($onek))) . '.php';
        if (is_file($dosya)) {
            require $dosya;
        }
    }
});

use VaporAktion2\Helpers\Einstellungen;
use VaporAktion2\Helpers\Regel;
use VaporAktion2\Services\GeschenkService;
use VaporAktion2\Services\KatalogService;

class FakeKatalog extends KatalogService
{
    public $liste = ['elfx' => [], 'elfliq' => []];
    public function optionen(Einstellungen $e): array
    {
        $r = ['elfx' => [], 'elfliq' => []];
        if ($e->elfxMoeglich()) { $r['elfx'] = $this->liste['elfx']; }
        if ($e->elfliqMoeglich()) { $r['elfliq'] = $this->liste['elfliq']; }
        return $r;
    }
}

$basari = 0;
$hatalar = [];
function pruefe(string $ad, bool $kosul)
{
    global $basari, $hatalar;
    if ($kosul) { $basari++; } else { $hatalar[] = $ad; echo "  HATA: $ad\n"; }
}

// Lostmary benzeri kurulum: ELFLIQ 87138 (item 24534, 8,49) aktions; ELFX 7880 (item 14359, 16,99) hediye
const ELFLIQ_A = 87138;
const ELFLIQ_B = 87140;
const ELFX     = 7880;
const ELFX_2   = 7881;
const ANDERS   = 5000;

function welt(array $ayar = []): array
{
    $w = new FakeWelt();
    $w->artikelVon = [ELFLIQ_A => 24534, ELFLIQ_B => 24535, ELFX => 14359, ELFX_2 => 14359, ANDERS => 999];
    $w->kupons = [
        'ELFXCODE00000001' => ['itemIds' => [14359], 'betrag' => 16.99],
        'ELFLIQCODE000001' => ['itemIds' => [24534, 24535], 'betrag' => 8.49],
        'FREMD10'          => ['itemIds' => [999, 24534, 24535, 14359], 'betrag' => 1.0],
    ];
    $w->kontaktId = 591963;

    $config = new \Plenty\Plugin\ConfigRepository();
    $standard = [
        'aktiv' => 'true', 'testmodus' => 'true', 'testKontaktIds' => '591963', 'testKundenklassenIds' => '',
        'benoetigteMenge' => '6', 'aktionsArtikelIds' => '24534, 24535', 'aktionsVariationIds' => '',
        'geschenkElfxArtikelIds' => '14359', 'geschenkElfliqErlaubt' => 'true',
        'gutscheinElfx' => 'elfxcode00000001', 'gutscheinElfliq' => 'ELFLIQCODE000001',
    ];
    foreach (array_merge($standard, $ayar) as $k => $v) {
        $config->werte['VaporAktion2.' . $k] = $v;
    }
    $katalog = new FakeKatalog();
    $katalog->liste = [
        'elfx'   => [
            ['variationId' => ELFX, 'itemId' => 14359, 'gruppe' => 'elfx', 'name' => 'ELFX – Silver Blue', 'preis' => '16,99 €'],
            ['variationId' => ELFX_2, 'itemId' => 14359, 'gruppe' => 'elfx', 'name' => 'ELFX – Black', 'preis' => '16,99 €'],
        ],
        'elfliq' => [
            ['variationId' => ELFLIQ_A, 'itemId' => 24534, 'gruppe' => 'elfliq', 'name' => 'ELFLIQ DFBB 10 mg', 'preis' => '8,49 €'],
            ['variationId' => ELFLIQ_B, 'itemId' => 24535, 'gruppe' => 'elfliq', 'name' => 'ELFLIQ Mango 10 mg', 'preis' => '8,49 €'],
        ],
    ];
    $s = new GeschenkService($config, $w, $w, $w, $w, $katalog);
    return [$w, $s];
}

echo "Einstellungen\n";
$e = Einstellungen::ausArray(['aktiv' => '1', 'testKontaktIds' => " 5; 7,\n9, x, -3, 0, 7", 'benoetigteMenge' => '0', 'gutscheinElfx' => ' abc ']);
pruefe('idListe temiz', $e->testKontaktIds === [5, 7, 9]);
pruefe('menge 0 → 6', $e->benoetigteMenge === 6);
pruefe('kod büyük harf', $e->gutscheinElfx === 'ABC');
pruefe('aktiv 1', $e->aktiv === true);
pruefe('testmodus varsayılan açık', Einstellungen::ausArray([])->testmodus === true);
pruefe('aktiv varsayılan kapalı', Einstellungen::ausArray([])->aktiv === false);
pruefe('elfx kodsuz mümkün değil', Einstellungen::ausArray(['geschenkElfxArtikelIds' => '1'])->elfxMoeglich() === false);

echo "Regel\n";
$e = Einstellungen::ausArray(['aktiv' => 'true', 'aktionsArtikelIds' => '24534', 'geschenkElfxArtikelIds' => '14359', 'gutscheinElfx' => 'X1', 'gutscheinElfliq' => 'L1']);
$z = [['id' => 1, 'variationId' => ELFLIQ_A, 'itemId' => 24534, 'menge' => 5, 'typ' => 10]];
$b = Regel::bewerten($e, true, $z, null, '');
pruefe('5 → hak yok, 1 eksik', !$b['berechtigt'] && $b['fehlt'] === 1 && count($b['aktionen']) === 0);
$z[0]['menge'] = 6;
pruefe('6 → hak var', Regel::bewerten($e, true, $z, null, '')['berechtigt']);
$z2 = array_merge($z, [['id' => 2, 'variationId' => ELFLIQ_A, 'itemId' => 24534, 'menge' => 3, 'typ' => 12]]);
pruefe('paket bileşeni sayılmaz', Regel::aktionsMenge($e, $z2) == 6);
$b = Regel::bewerten($e, true, $z, null, 'x1');
pruefe('hediye yokken bizim kod → sil', $b['aktionen'][0]['typ'] === Regel::AKTION_GUTSCHEIN_WEG && $b['grund'] === 'gutscheinOhneGeschenk');
$b = Regel::bewerten($e, false, $z, null, 'X1');
pruefe('yetkisiz müşteride bizim kod → sil', $b['aktionen'][0]['typ'] === Regel::AKTION_GUTSCHEIN_WEG);
$b = Regel::bewerten(Einstellungen::ausArray(['gutscheinElfx' => 'X1']), true, $z, null, 'X1');
pruefe('eklenti kapalıyken bizim kod → sil', $b['grund'] === 'aktionInaktiv');
$b = Regel::bewerten($e, true, $z, null, 'FREMD');
pruefe('yabancı kupon → dokunma, işaretle', $b['fremderGutschein'] && count($b['aktionen']) === 0);
// ELFLIQ hediyesi aynı satırda: 7 adet = 6 ücretli + 1 hediye
$z[0]['menge'] = 7;
$b = Regel::bewerten($e, true, $z, ['variationId' => ELFLIQ_A, 'gruppe' => 'elfliq'], 'L1');
pruefe('elfliq 7 = 6+1 geçerli', $b['berechtigt'] && $b['bezahlt'] === 6 && count($b['aktionen']) === 0);
$z[0]['menge'] = 6;
$b = Regel::bewerten($e, true, $z, ['variationId' => ELFLIQ_A, 'gruppe' => 'elfliq'], 'L1');
pruefe('elfliq 6 = 5+1 → hediye düş (adet 5)', $b['aktionen'][0]['typ'] === Regel::AKTION_MENGE_SETZEN && $b['aktionen'][0]['menge'] == 5 && $b['grund'] === 'zuWenigArtikel');

echo "GeschenkService\n";
// 1) Hak yok → seçim reddedilir
[$w, $s] = welt();
$w->zeile(ELFLIQ_A, 5);
$r = $s->waehlen(ELFX);
pruefe('5 adette seçim reddedilir', !$r['ok'] && strpos($r['meldung'], 'fehlerNichtBerechtigt') !== false);
pruefe('status: noch 1', $r['sichtbar'] && $r['fehlt'] === 1 && strpos($r['texte']['nochFehlend'], 'nochFehlendEins') !== false);
pruefe('hak yokken seçenek yok', count($r['optionen']['elfx']) === 0);

// 2) 6 adet → ELFX seç → satır + kupon
[$w, $s] = welt();
$w->zeile(ELFLIQ_A, 4);
$w->zeile(ELFLIQ_B, 2);
$st = $s->status();
pruefe('6 adette seçenekler gelir', $st['berechtigt'] && count($st['optionen']['elfx']) === 2 && count($st['optionen']['elfliq']) === 2);
$r = $s->waehlen(ELFX);
pruefe('ELFX eklendi', $r['ok'] && $w->menge(ELFX) == 1.0);
pruefe('ELFX kuponu sepette', $w->coupon === 'ELFXCODE00000001');
pruefe('durum: hediye görünür', $r['geschenk']['variationId'] === ELFX && $r['geschenk']['name'] === 'ELFX – Silver Blue');

// 3) Müşteri ELFX adedini 3 yapar → 1'e çekilir
foreach ($w->zeilen as $zz) { if ($zz->variationId === ELFX) { $zz->quantity = 3; } }
$degisti = $s->pruefen();
pruefe('ELFX adedi 1e çekildi', $w->menge(ELFX) == 1.0 && $degisti);

// 4) Kupon bir şekilde düşerse geri konur
$w->coupon = '';
$s->pruefen();
pruefe('kupon yeniden kondu', $w->coupon === 'ELFXCODE00000001');

// 5) Liquid azalır (6 → 5) → hediye + kupon gider
foreach ($w->zeilen as $zz) { if ($zz->variationId === ELFLIQ_B) { $zz->quantity = 1; } }
$s->pruefen();
pruefe('hak düşünce ELFX satırı silindi', $w->menge(ELFX) == 0.0);
pruefe('hak düşünce kupon silindi', $w->coupon === '');
pruefe('oturum temiz', ($w->session['VaporAktion2_geschenk'] ?? '') === '');
pruefe('log: zuWenigArtikel', TestLogger::enthaelt('Log.zuWenigArtikel'));

// 6) ELFLIQ hediyesi aynı varyasyona eklenir (birleşir), sonra ELFX'e değiştirilir
[$w, $s] = welt();
$w->zeile(ELFLIQ_A, 6);
$r = $s->waehlen(ELFLIQ_A);
pruefe('ELFLIQ hediye: satır 7', $r['ok'] && $w->menge(ELFLIQ_A) == 7.0 && $w->coupon === 'ELFLIQCODE000001');
pruefe('ELFLIQ hediye sonrası bezahlt 6', $r['bezahlt'] === 6 && $r['berechtigt']);
$r = $s->waehlen(ELFX_2);
pruefe('değiştir: ELFLIQ 6ya döndü', $w->menge(ELFLIQ_A) == 6.0);
pruefe('değiştir: ELFX eklendi + kupon ELFX', $w->menge(ELFX_2) == 1.0 && $w->coupon === 'ELFXCODE00000001' && $r['ok']);
$r = $s->entfernen();
pruefe('müşteri kaldırdı: satır + kupon gitti', $w->menge(ELFX_2) == 0.0 && $w->coupon === '' && $r['geschenk'] === null);

// 7) Yabancı kupon varken seçim yapılamaz
[$w, $s] = welt();
$w->zeile(ELFLIQ_A, 6);
$w->coupon = 'FREMD10';
$r = $s->waehlen(ELFX);
pruefe('yabancı kupon: seçim reddedildi', !$r['ok'] && strpos($r['meldung'], 'fehlerFremderGutschein') !== false && $w->menge(ELFX) == 0.0);
pruefe('yabancı kupon: status işaretli', $r['fremderGutschein'] && $w->coupon === 'FREMD10');

// 8) Hediye varken müşteri yabancı kupon girer → hediye çıkar, yabancı kupon kalır
[$w, $s] = welt();
$w->zeile(ELFLIQ_A, 6);
$s->waehlen(ELFX);
$w->coupon = 'FREMD10';
$degisti = $s->pruefen('kasse');
pruefe('yabancı kupon sonrası hediye silindi', $w->menge(ELFX) == 0.0 && $w->coupon === 'FREMD10' && $degisti);

// 9) Sızıntı: test dışı müşteri bizim kodu girer → silinir (test modunda TÜM müşteriler)
[$w, $s] = welt();
$w->kontaktId = 12345;
$w->zeile(ELFX, 1);
$w->coupon = 'ELFXCODE00000001';
$degisti = $s->pruefen();
pruefe('test dışı müşteride bizim kod silindi', $w->coupon === '' && $degisti);
pruefe('test dışı müşteride kutu gizli', $s->status()['sichtbar'] === false);
pruefe('ücretli ELFX satırına dokunulmadı', $w->menge(ELFX) == 1.0);

// 10) Misafir, test modu kapalı → katılır
[$w, $s] = welt(['testmodus' => 'false']);
$w->kontaktId = 0;
$w->zeile(ELFLIQ_A, 6);
pruefe('test modu kapalı: misafir seçebilir', $s->waehlen(ELFX)['ok']);

// 11) Eklenti kapalı, bizim kod sepette → silinir; kutu yok
[$w, $s] = welt(['aktiv' => 'false']);
$w->zeile(ELFX, 1);
$w->coupon = 'ELFXCODE00000001';
$s->pruefen();
pruefe('eklenti kapalı: bizim kod silindi', $w->coupon === '');
pruefe('eklenti kapalı: kutu gizli', $s->status()['sichtbar'] === false);

// 12) Kupon geçersiz (kampanya kapalı) → hediye geri alınır
[$w, $s] = welt();
unset($w->kupons['ELFXCODE00000001']);
$w->zeile(ELFLIQ_A, 6);
$r = $s->waehlen(ELFX);
pruefe('kupon geçersiz: hediye eklenmedi', !$r['ok'] && $w->menge(ELFX) == 0.0 && strpos($r['meldung'], 'fehlerGutschein') !== false);
pruefe('kupon geçersiz: log', TestLogger::enthaelt('Log.gutscheinFehlgeschlagenGeschenkEntfernt'));

// 13) IO ekleme hatası (stok)
[$w, $s] = welt();
$w->zeile(ELFLIQ_A, 6);
$w->addFehler = 111;
$r = $s->waehlen(ELFX);
pruefe('IO hata: kibar mesaj, kupon yok', !$r['ok'] && $w->coupon === '' && strpos($r['meldung'], 'fehlerHinzufuegen') !== false);

// 14) Aynı ELFX ücretli olarak sepette → reddedilir
[$w, $s] = welt();
$w->zeile(ELFLIQ_A, 6);
$w->zeile(ELFX, 1);
$r = $s->waehlen(ELFX);
pruefe('ücretli aynı ELFX: red', !$r['ok'] && strpos($r['meldung'], 'fehlerGeraetImWarenkorb') !== false && $w->menge(ELFX) == 1.0);

// 15) Listede olmayan varyasyon
[$w, $s] = welt();
$w->zeile(ELFLIQ_A, 6);
$r = $s->waehlen(ANDERS);
pruefe('geçersiz varyasyon: red', !$r['ok'] && $w->menge(ANDERS) == 0.0);

// 16) Hediye satırını müşteri siler → oturum + kupon temizlenir
[$w, $s] = welt();
$w->zeile(ELFLIQ_A, 6);
$s->waehlen(ELFX);
foreach ($w->zeilen as $id => $zz) { if ($zz->variationId === ELFX) { unset($w->zeilen[$id]); } }
$w->coupon = 'ELFXCODE00000001'; // Plenty düşürmediyse bile
$s->pruefen();
pruefe('hediye satırı silinince kupon da gider', $w->coupon === '' && ($w->session['VaporAktion2_geschenk'] ?? '') === '');

// 17) Döngü kilidi: hata sepeti bozmaz
[$w, $s] = welt();
$w->zeile(ELFLIQ_A, 6);
$s->waehlen(ELFX);
$w->session['VaporAktion2_geschenk'] = '{bozuk json';
pruefe('bozuk oturum verisi patlatmaz', $s->pruefen() === true && $w->coupon === '');

echo "\n$basari test geçti, " . count($hatalar) . " hata.\n";
exit(count($hatalar) === 0 ? 0 : 1);
