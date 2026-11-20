<?php
// Kullanım: php tests/run_aktion1.php   (Plenty'siz yerel birim testleri, Aktion 1)

require __DIR__ . '/stubs.php';

spl_autoload_register(function ($klasse) {
    $onek = 'VaporAktion2Test\\';
    if (strpos($klasse, $onek) === 0) {
        $dosya = __DIR__ . '/../src/' . str_replace('\\', '/', substr($klasse, strlen($onek))) . '.php';
        if (is_file($dosya)) {
            require $dosya;
        }
    }
});

use VaporAktion2Test\Aktion1\A1Einstellungen;
use VaporAktion2Test\Aktion1\A1GeschenkService;
use VaporAktion2Test\Aktion1\A1KatalogService;
use VaporAktion2Test\Aktion1\A1Regel;

class FakeA1Katalog extends A1KatalogService
{
    public $liste = [];
    public function optionen(A1Einstellungen $e): array
    {
        return $this->liste;
    }
}

$basari = 0;
$hatalar = [];
function pruefe(string $ad, bool $kosul)
{
    global $basari, $hatalar;
    if ($kosul) { $basari++; } else { $hatalar[] = $ad; echo "  HATA: $ad\n"; }
}

// 20.11.2026 ölçümü: tek tat pod 2294 (item 11057, 7,99), Sammel paket 4040 (item 12569, itemType 11, elfbargermany),
// cihaz Black 2561 (item 11272, 7,49), Navy Blue 2562 (item 11273)
const POD_WM   = 2294;
const POD_MG   = 2282;
const POD_PAK  = 4040;
const GER_BL   = 2561;
const GER_NB   = 2562;
const ANDERS   = 5000;
const A1CODE   = 'A1CODE0000000001';
const A2CODE   = 'ELFXCODE00000001';

function welt(array $ayar = []): array
{
    $w = new FakeWelt();
    $w->artikelVon = [POD_WM => 11057, POD_MG => 11045, POD_PAK => 12569, GER_BL => 11272, GER_NB => 11273, ANDERS => 999];
    $w->kupons = [
        A1CODE    => ['itemIds' => [11272, 11273], 'betrag' => 7.49],
        A2CODE    => ['itemIds' => [14359, 11057], 'betrag' => 16.99],
        'FREMD10' => ['itemIds' => [11057, 11045, 11272], 'betrag' => 1.0],
    ];
    $w->kontaktId = 591963;

    $config = new \Plenty\Plugin\ConfigRepository();
    $standard = [
        'a1Aktiv' => 'true', 'a1Testmodus' => 'true', 'a1TestKontaktIds' => '591963', 'a1BenoetigteMenge' => '3',
        'a1PodArtikelIds' => "11057, 11045,\n12569", 'a1PodVariationIds' => '',
        'a1GeschenkArtikelIds' => '11272, 11273', 'a1Gutschein' => strtolower(A1CODE),
        'gutscheinElfx' => A2CODE, 'gutscheinElfliq' => '',
    ];
    foreach (array_merge($standard, $ayar) as $k => $v) {
        $config->werte['VaporAktion2Test.' . $k] = $v;
    }
    $katalog = new FakeA1Katalog();
    $katalog->liste = [
        ['variationId' => GER_BL, 'itemId' => 11272, 'name' => 'ELFBAR ELFA Pod Kit 500 mAh Akku - Black', 'farbe' => 'Black', 'preis' => '7,49 €', 'bild' => ''],
        ['variationId' => GER_NB, 'itemId' => 11273, 'name' => 'ELFBAR ELFA Pod Kit 500 mAh Akku - Navy Blue', 'farbe' => 'Navy Blue', 'preis' => '7,49 €', 'bild' => ''],
    ];
    $s = new A1GeschenkService($config, $w, $w, $w, $w, $katalog);
    return [$w, $s];
}

function gutschein($w): string { return $w->coupon; }

echo "A1Einstellungen\n";
$e = A1Einstellungen::ausArray(['a1Aktiv' => 'true', 'a1BenoetigteMenge' => '0', 'a1Gutschein' => ' x1 ', 'a1PodArtikelIds' => '1;2', 'a1GeschenkArtikelIds' => '3'], ['A2', '', 'x1']);
pruefe('menge 0 → 3', $e->benoetigteMenge === 3);
pruefe('kod büyük harf', $e->gutschein === 'X1');
pruefe('A2 kodları alındı, kendi kodu hariç', $e->andereCodes === ['A2']);
pruefe('vollstaendig', $e->vollstaendig());
pruefe('varsayılan kapalı + test modu', A1Einstellungen::ausArray([])->aktiv === false && A1Einstellungen::ausArray([])->testmodus === true);
pruefe('kodsuz eksik', !A1Einstellungen::ausArray(['a1PodArtikelIds' => '1', 'a1GeschenkArtikelIds' => '3'])->vollstaendig());

echo "A1Regel\n";
$e = A1Einstellungen::ausArray(['a1Aktiv' => 'true', 'a1PodArtikelIds' => '11057,12569', 'a1GeschenkArtikelIds' => '11272', 'a1Gutschein' => 'C1'], ['A2X']);
$z = [
    ['id' => 1, 'variationId' => POD_WM, 'itemId' => 11057, 'menge' => 1, 'typ' => 10],
    ['id' => 2, 'variationId' => POD_PAK, 'itemId' => 12569, 'menge' => 1, 'typ' => 11],
];
$b = A1Regel::bewerten($e, true, $z, null, '');
pruefe('2 pod (tip 10 + paket tip 11) → 1 eksik', $b['podMenge'] === 2 && $b['fehlt'] === 1 && !$b['berechtigt']);
$z[] = ['id' => 3, 'variationId' => POD_WM, 'itemId' => 11057, 'menge' => 5, 'typ' => 12];
pruefe('bilinmeyen tip (12) sayılmaz', A1Regel::podMenge($e, $z) == 2);
$z[2]['typ'] = 10; $z[2]['menge'] = 1;
pruefe('3 pod → hak', A1Regel::bewerten($e, true, $z, null, '')['berechtigt']);
$b = A1Regel::bewerten($e, true, $z, null, 'a2x');
pruefe('A2 kodu → başka kampanya işareti', $b['fremderGutschein'] && $b['andereAktion'] && count($b['aktionen']) === 0);
$b = A1Regel::bewerten($e, true, $z, null, 'FREMD');
pruefe('yabancı kupon → başka kampanya değil', $b['fremderGutschein'] && !$b['andereAktion']);
$b = A1Regel::bewerten($e, true, $z, null, 'c1');
pruefe('hediye yokken bizim kod → sil', $b['aktionen'][0]['typ'] === A1Regel::AKTION_GUTSCHEIN_WEG && $b['grund'] === 'a1GutscheinOhneGeschenk');
$zg = array_merge($z, [['id' => 9, 'variationId' => GER_BL, 'itemId' => 11272, 'menge' => 2, 'typ' => 10]]);
$b = A1Regel::bewerten($e, true, $zg, ['variationId' => GER_BL], 'C1');
pruefe('cihaz 2 adet (1 ücretli + 1 hediye) geçerli, işlem yok', count($b['aktionen']) === 0 && $b['geschenk']['variationId'] === GER_BL);
$zg2 = $zg; unset($zg2[2]);
$b = A1Regel::bewerten($e, true, array_values($zg2), ['variationId' => GER_BL], 'C1');
pruefe('2 pod\'a düşünce: cihaz adedi 1 azalır + kupon gider', $b['aktionen'][0]['typ'] === A1Regel::AKTION_MENGE_SETZEN && $b['aktionen'][0]['menge'] == 1 && $b['aktionen'][2]['typ'] === A1Regel::AKTION_GUTSCHEIN_WEG && $b['grund'] === 'a1ZuWenigPods');
$b = A1Regel::bewerten(A1Einstellungen::ausArray(['a1Gutschein' => 'C1']), true, $z, null, 'C1');
pruefe('kapalıyken bizim kod → sil', $b['grund'] === 'a1AktionInaktiv');

echo "A1GeschenkService\n";
// 1) 2 pod → seçim reddedilir, durum 1 eksik
[$w, $s] = welt();
$w->zeile(POD_WM, 2);
$r = $s->waehlen(GER_BL);
pruefe('2 podda seçim red', !$r['ok'] && strpos($r['meldung'], 'fehlerNichtBerechtigt') !== false && $w->menge(GER_BL) == 0.0);
pruefe('durum: 1 eksik, seçenek yok', $r['sichtbar'] && $r['fehlt'] === 1 && count($r['optionen']) === 0);

// 2) Karışık 3 pod (tek tat + elfbargermany paketi) → Black seç
[$w, $s] = welt();
$w->zeile(POD_WM, 1);
$w->zeile(POD_MG, 1);
$w->zeile(POD_PAK, 1, 11);
$st = $s->status(11057);
pruefe('3 podda seçenekler + ürün sayfası istPod', $st['berechtigt'] && count($st['optionen']) === 2 && $st['istPod'] === true);
pruefe('cihaz ürünü istPod değil', $s->status(11272)['istPod'] === false);
$r = $s->waehlen(GER_BL);
pruefe('Black eklendi + kupon', $r['ok'] && $w->menge(GER_BL) == 1.0 && gutschein($w) === A1CODE);
pruefe('durum: hediye Black', $r['geschenk']['farbe'] === 'Black');

// 3) Renk değiştir → Navy Blue
$r = $s->waehlen(GER_NB);
pruefe('renk değişti', $r['ok'] && $w->menge(GER_BL) == 0.0 && $w->menge(GER_NB) == 1.0 && gutschein($w) === A1CODE);

// 4) Kupon düşerse geri konur
$w->coupon = '';
$s->pruefen();
pruefe('kupon yeniden kondu', gutschein($w) === A1CODE);

// 5) Pod 2'ye düşer → cihaz + kupon gider
foreach ($w->zeilen as $id => $zz) { if ($zz->variationId === POD_PAK) { unset($w->zeilen[$id]); } }
$s->pruefen();
pruefe('hak düşünce cihaz silindi', $w->menge(GER_NB) == 0.0);
pruefe('hak düşünce kupon silindi', gutschein($w) === '');
pruefe('oturum temiz', ($w->session['VaporAktion2Test_aktion1'] ?? '') === '');
pruefe('Aktion 2 oturumuna dokunulmadı', !isset($w->session['VaporAktion2Test_geschenk']));

// 6) Aynı renk ücretli sepette → hediye adedi +1, kaldırınca ücretli kalır
[$w, $s] = welt();
$w->zeile(POD_WM, 3);
$w->zeile(GER_BL, 1);
$r = $s->waehlen(GER_BL);
pruefe('ücretli Black + hediye Black = 2', $r['ok'] && $w->menge(GER_BL) == 2.0 && gutschein($w) === A1CODE);
$r = $s->entfernen();
pruefe('hediye kaldırıldı: ücretli 1 kaldı, kupon yok', $w->menge(GER_BL) == 1.0 && gutschein($w) === '' && $r['geschenk'] === null);

// 7) Aktion 2 kuponu sepette → "başka kampanya", seçim yok
[$w, $s] = welt();
$w->zeile(POD_WM, 3);
$w->coupon = A2CODE;
$r = $s->waehlen(GER_BL);
pruefe('A2 kuponu varken red (fehlerAndereAktion)', !$r['ok'] && strpos($r['meldung'], 'fehlerAndereAktion') !== false && $w->menge(GER_BL) == 0.0);
pruefe('A2 kuponuna dokunulmadı', gutschein($w) === A2CODE && $r['andereAktion'] === true);

// 8) Yabancı kupon → red; hediye varken yabancı kupon gelirse hediye gider
[$w, $s] = welt();
$w->zeile(POD_WM, 3);
$w->coupon = 'FREMD10';
$r = $s->waehlen(GER_BL);
pruefe('yabancı kupon: red', !$r['ok'] && strpos($r['meldung'], 'fehlerFremderGutschein') !== false);
[$w, $s] = welt();
$w->zeile(POD_WM, 3);
$s->waehlen(GER_BL);
$w->coupon = 'FREMD10';
$degisti = $s->pruefen('kasse');
pruefe('yabancı kupon sonrası hediye silindi', $degisti && $w->menge(GER_BL) == 0.0 && gutschein($w) === 'FREMD10');

// 9) Test dışı müşteri bizim kodu girer → silinir; kutu gizli
[$w, $s] = welt();
$w->kontaktId = 12345;
$w->zeile(GER_BL, 1);
$w->coupon = A1CODE;
$s->pruefen();
pruefe('test dışı: kod silindi, ücretli cihaz kaldı', gutschein($w) === '' && $w->menge(GER_BL) == 1.0);
pruefe('test dışı: kutu gizli', $s->status()['sichtbar'] === false);

// 10) Varsayılan (kapalı) → hiçbir şey; Aktion 2 kodu dokunulmaz
[$w, $s] = welt(['a1Aktiv' => 'false']);
$w->zeile(POD_WM, 3);
$w->zeile(14359, 1);
$w->coupon = A2CODE;
pruefe('kapalı: değişiklik yok', $s->pruefen() === false && gutschein($w) === A2CODE && $s->status()['sichtbar'] === false);

// 11) Kod boş → kutu yok, seçim yok
[$w, $s] = welt(['a1Gutschein' => '']);
$w->zeile(POD_WM, 3);
pruefe('kod boş: kutu gizli', $s->status()['sichtbar'] === false && !$s->waehlen(GER_BL)['ok'] && $w->menge(GER_BL) == 0.0);

// 12) Kupon kampanyası çalışmıyor → cihaz geri alınır
[$w, $s] = welt();
unset($w->kupons[A1CODE]);
$w->zeile(POD_WM, 3);
$r = $s->waehlen(GER_BL);
pruefe('kupon geçersiz: cihaz eklenmedi', !$r['ok'] && $w->menge(GER_BL) == 0.0 && strpos($r['meldung'], 'fehlerGutschein') !== false);

// 13) IO ekleme hatası
[$w, $s] = welt();
$w->zeile(POD_WM, 3);
$w->addFehler = 111;
$r = $s->waehlen(GER_BL);
pruefe('IO hata: mesaj, kupon yok', !$r['ok'] && gutschein($w) === '' && strpos($r['meldung'], 'fehlerHinzufuegen') !== false);

// 14) Listede olmayan varyasyon
[$w, $s] = welt();
$w->zeile(POD_WM, 3);
pruefe('geçersiz varyasyon red', !$s->waehlen(ANDERS)['ok'] && $w->menge(ANDERS) == 0.0);

// 15) Misafir, test modu kapalı → katılır
[$w, $s] = welt(['a1Testmodus' => 'false']);
$w->kontaktId = 0;
$w->zeile(POD_MG, 3);
pruefe('test modu kapalı: misafir seçer', $s->waehlen(GER_NB)['ok'] && $w->menge(GER_NB) == 1.0);

// 16) Müşteri cihaz satırını siler → oturum + kupon temizlenir
[$w, $s] = welt();
$w->zeile(POD_WM, 3);
$s->waehlen(GER_BL);
foreach ($w->zeilen as $id => $zz) { if ($zz->variationId === GER_BL) { unset($w->zeilen[$id]); } }
$w->coupon = A1CODE;
$s->pruefen();
pruefe('cihaz silinince kupon da gider', gutschein($w) === '' && ($w->session['VaporAktion2Test_aktion1'] ?? '') === '');

// 17) Bozuk oturum verisi patlatmaz
[$w, $s] = welt();
$w->zeile(POD_WM, 3);
$s->waehlen(GER_BL);
$w->session['VaporAktion2Test_aktion1'] = '{bozuk';
pruefe('bozuk oturum: kupon silinir, hata yok', $s->pruefen() === true && gutschein($w) === '');

echo "\n$basari test geçti, " . count($hatalar) . " hata.\n";
exit(count($hatalar) === 0 ? 0 : 1);
