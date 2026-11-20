# Kurulum ve test — VaporAktion2Test 0.3.0 (dal `main`)

> 0.3.0: eklentide iki kampanya var. **Aktion 2** (6 ELFLIQ → hediye) aşağıda; **Aktion 1** (3 ELFA Pods → ELFA Basisgerät) en altta [Aktion 1](#aktion-1) bölümünde.
> Dal: artık hep `main` (aşağıdaki 1. adımdaki `kampagne` notu eski; dal değiştirme gerekmez).

# Aktion 2

Kampanya: **6 Aktions-Artikel (ELFLIQ) al → 1 ELFLIQ ya da 1 ELFX Pod Kit hediye.**

Nasıl çalışır (20.11.2026 canlı testiyle kanıtlanan yol):
hediye **gerçek varyasyon, tam fiyatla** sepete girer → eklenti aynı anda hediye grubunun **Aktionsgutschein** kodunu sepete uygular (Festbetrag = hediye fiyatı, Artikel filtresi = hediye ürünü) → hediye fiilen 0 €. Hak düşerse ya da müşteri hediyeyi kaldırırsa eklenti **hediyeyi + kuponu** kaldırır.

> Eklenti varsayılan **pasif** ve **test modunda**. Plugin set'ini bereitstellen etmek tüm seti yeniden derler: sakin bir saatte yap.

---

## 1. Dalı değiştir (Lostmary plugin set'i)

`main` eski deneme sürümü (0.1.x) olarak kalır; yeni sürüm `kampagne` dalında.

1. Plugins » Plugin-Set-Übersicht » **Lostmarygermany.de** setini aç » **VaporAktion2Test** satırı.
2. Git ayarlarında **Branch: `kampagne`** → Speichern.
3. Eklentiyi bu dalla güncelle (Plugin Build 2 dalın son hâlini çeker).
4. **Henüz bereitstellen etme**: önce 2. ve 3. adım (kuponlar + ayarlar).

## 2. Kuponları panelde aç (API ile açılamıyor, elle)

Her hediye grubu için **bir** kampanya. Yol: **Aufträge » Gutscheine** (Gutscheinkampagnen) » **Neue Kampagne**.

| Alan | ELFX kampanyası | ELFLIQ kampanyası |
|---|---|---|
| Name | Aktion 2 – ELFX gratis | Aktion 2 – ELFLIQ gratis |
| Typ | **Aktionsgutschein** | **Aktionsgutschein** |
| Rabatt | **Festbetrag 16,99 €** (ELFX satış fiyatı) | **Festbetrag 8,49 €** (1 ELFLIQ satış fiyatı) |
| Code-Länge | **16 Zeichen** | **16 Zeichen** |
| Code-Anzahl / Einlösung | **tek kod, sınırsız / çok kez kullanılabilir** (müşteri başına sınırsız) | aynı |
| Mindestbestellwert | 0 | 0 |
| Gültigkeit | kampanya süresi | kampanya süresi |
| **Mandant (Filter)** | **yalnız Lostmarygermany.de** | **yalnız Lostmarygermany.de** |
| **Artikel (Filter)** | **ELFX artikeli** (Lostmary: item **14359**) | **Aktions-ELFLIQ artikelleri** (Lostmary: **24534** + diğer tat artikelleri) |
| Versandkosten | etkilenmesin | etkilenmesin |

Kampanyayı kaydet → **Code**'u kopyala (16 karakter) → 3. adımdaki ayara yapıştır.

Hazır test kodu (ELFX): **`<ELFX-Gutscheincode aus Plenty>`** — item 14359, 16,99 €, yalnız Lostmary, 22.11.2026'ya kadar geçerli. Bu kod ELFX ayarı için hemen kullanılabilir; ELFLIQ için yeni kampanya aç.

Notlar:
- Plenty sepetinde aynı anda **tek kupon** olur. Müşterinin başka kuponu varsa hediye seçilemez (kutuda „Aktion nicht mit anderen Gutscheinen kombinierbar“).
- Kodlar gizli tutulmalı ama sızsa da sorun değil: eklenti, hakkı olmayan sepette bizim kodu **siler** (test modunda test dışı tüm müşteriler, eklenti kapalıyken herkes).
- Festbetrag sabittir: hediye fiyatı değişirse (ör. Black Deals) kampanya tutarını da değiştir. ELFLIQ satırı Lostmary'de **8,4901 €** ile giriyor, kupon 8,49 → hediye satırında **0,0001 €** fark kalır (gösterimde 0,00 €; sipariş toplamında yuvarlamaya bağlı, bkz. Test 5).

## 3. Eklenti ayarları (Lostmary test değerleri)

Plugin-Set » VaporAktion2Test » **Konfiguration**.

**Allgemein**

| Ayar | Değer |
|---|---|
| Aktion aktiv | **evet** |
| Testmodus | **evet** |
| Test-Kunden (Kontakt-IDs) | **591963** |
| Test-Kundenklassen | boş (sınıf değiştirmek Lostmary'de ürünleri gizledi) |

**Aktion & Gutscheine**

| Ayar | Değer |
|---|---|
| Benötigte Menge | **6** |
| Aktions-Artikel (Artikel-IDs) | **24534** (Dragon Fruit Blueberry) — canlıda: kategori **ELFBAR ELFLIQ Liquids**'teki **tüm tat artikelleri** virgülle |
| Aktions-Artikel (Variations-IDs) | boş (tek tek varyasyon gerekiyorsa buraya) |
| Geschenk ELFX (Artikel-IDs) | **14359** |
| ELFLIQ als Geschenk | **evet** |
| Gutscheincode ELFX | **<ELFX-Gutscheincode aus Plenty>** (ya da yeni kampanyanın kodu) |
| Gutscheincode ELFLIQ | ELFLIQ kampanyasının kodu |

Lostmary'de ölçülen: ELFLIQ **87138** (ELF-DFBLB-10, 10 mg) sepete 8,4901 € ile giriyor; **87139** (20 mg) bağlı ama stok negatif → seçim listesinde görünmez (satılabilir değil), ama müşteri sepetine koyabildiyse sayıma girer. ELFX **7880** (silver blue) aktif + stoklu.

Yedek: ELFLIQ'ler bir sebeple satılamazsa test için ELFA Master varyasyonları **8496–8499** „Aktions-Artikel (Variations-IDs)“ alanına yazılabilir (ELFLIQ kuponu filtresini o artikele çevir).

Kodları boş bırakılan grup kutuda görünmez (ör. yalnız ELFX test etmek için ELFLIQ kodunu boş bırak).

## 4. Container bağlantıları

Plugin-Set » VaporAktion2Test » **Container-Verknüpfungen** (eklenti varsayılanları getirir; kontrol et):

| Data Provider | Container |
|---|---|
| Aktion 2: Script (Pflicht) | `Ceres::Script.AfterScriptsLoaded` (Script: Nach dem Laden der Scripte) |
| Aktion 2: Geschenk-Box Warenkorb | `Ceres::Basket.BeforeBasketTotals` (Warenkorb: Vor den Warenkorb-Summen) |
| Aktion 2: Geschenk-Box Warenkorb-Vorschau | `Ceres::BasketPreview.BeforeBasketTotals` |

Script bağlantısı yoksa kutu boş kalır. ShopBuilder sepet sayfasında kutu „Warenkorb-Summen“ widget'ının üstünde çıkar.

Sonra seti **bereitstellen**. Bitince yeşil olmalı; hata varsa Daten » Log.

## 5. Test adımları (test kontağı 591963 ile giriş yap)

Mobil: tarayıcıda 360 px genişlikte de bak.

| # | Adım | Beklenen |
|---|---|---|
| 1 | Çıkış yapmış (misafir) sepete 87138 × 6 koy | Kutu **yok** (test modu) |
| 2 | 591963 ile giriş, sepette 87138 × 4 | Kutu: „Noch 2 Liquids bis zum Gratis-Geschenk“, ilerleme çubuğu |
| 3 | Adedi 6 yap | Kutu: „Glückwunsch! …“, ELFX / ELFLIQ seçimi, renk listesi |
| 4 | ELFX → Silver Blue (7880) → „Gratis hinzufügen“ | Sepette ELFX satırı **16,99 €** + kupon satırı **−16,99 €**; kutu „Dein Geschenk: … GRATIS“. Toplam = 6 × 8,49 (+ kargo) |
| 5 | ELFX adedini 3 yap | Sayfa güncellenince adet **1** |
| 6 | „Ändern“ → 1 ELFLIQ → 87138 → ekle | ELFX satırı + ELFX kuponu gider; 87138 satırı **7** adet, ELFLIQ kuponu **−8,49 €**. Toplam ≈ 6 × 8,49 (0,0001 € kuruş farkını not et) |
| 7 | 87138'i 6'ya düşür | Hediye düşer: satır 5 adet, kupon gider, kutu „Noch 1 Liquid …“ |
| 8 | Tekrar 6 + ELFX seç, sonra kupon alanına **başka bir kupon** gir | Hediye satırı **kaldırılır**, yabancı kupon kalır; kutu „Aktion nicht mit anderen Gutscheinen kombinierbar“ |
| 9 | Yabancı kuponu sil → ELFX seç | Çalışır |
| 10 | Hediye varken kuponu elle sil | Sonraki sepet değişiminde / sayfa yenilemede kupon geri gelir (ya da kasada tekrar uygulanır) |
| 11 | Sızıntı: başka hesapla (ya da misafir) ELFX'i sepete koy, ELFX kodunu kupon alanına gir | Kupon **silinir** (bir sonraki sepet değişiminde, en geç kasada „Dein Warenkorb wurde angepasst …“) |
| 12 | 4. adım durumunda sipariş ver (Vorkasse) | Siparişte ELFX satırı 16,99 €, kupon −16,99 €; stok **7880**'den düşer |
| 13 | Daten » Log » Integration **VaporAktion2Test** | „Kunde hat ein Geschenk gewählt“, „Zu wenige Aktions-Artikel …“, „Aktions-Gutschein ohne Geschenk … entfernt“ satırları |

Test siparişlerini sonra iptal et.

Bilinen sınırlar:
- Müşteri aynı ELFX rengini **ücretli** olarak da sepete koymuşsa o renk hediye seçilemez („Dieses Gerät liegt bereits im Warenkorb“). Hediye ELFX'in adedi her zaman 1'e çekilir.
- ELFLIQ hediyesi aynı varyasyonun ücretli satırıyla birleşir (Plenty aynı varyasyonu tek satırda tutar): 7 adet = 6 ücretli + 1 hediye, indirim kupon satırında görünür.
- Hediye seçimi oturumda tutulur. Oturum biterse sepet de biter; ama oturum kaybolup sepet kalırsa kupon silinir ve hediye satırı tam fiyatla kalır (müşteri kutudan yeniden seçebilir).

## 6. Canlıya alma (hepsi için)

1. Kampanyaların Artikel filtresine **tüm** Aktions-ELFLIQ tat artikellerini ekle.
2. Ayarlar: Aktions-Artikel listesini tamamla, **Testmodus = hayır** → bereitstellen.
3. İlk siparişleri ve Log'u kontrol et.

## 7. Geri alma / acil durum

1. Konfigürasyonda **Aktion aktiv = hayır** → bereitstellen. Kutu kaybolur; sepetlerde kalan bizim kupon bir sonraki sepet değişiminde silinir, kayıtlı hediye satırı da kaldırılır.
2. Kesin kapatma: panelde iki **kupon kampanyasını pasif yap** (en hızlı yol; bereitstellen gerekmez).
3. Gerekirse sette eklentinin **Aktiv** işaretini kaldır → bereitstellen.

## Teknik (geliştirici)

- `src/Helpers/Regel.php`: saf kural (sayım, hak, kaldır/kupon kararları); `tests/run.php` bunu ve servisi sahte Plenty sınıflarıyla test eder (`php tests/run.php`).
- `src/Services/GeschenkService.php`: kuralı Plenty'de yürütür. `AfterBasketChanged` (sepet), `ValidateCheckoutEvent` (kasa), REST durum sorgusu.
- Sepet/kupon: `BasketItemRepositoryContract` (all/update/remove), `BasketRepositoryContract::setCouponCode/removeCouponCode` (IO `CouponService` aynılarını çağırır), ekleme `IO\Services\BasketService::addBasketItem` (IO REST ile aynı yol).
- REST: `GET/POST/DELETE /rest/vapor-aktion2/geschenk` (POST/DELETE `csrf` middleware'li; plentyShop `X-CSRF-TOKEN` gönderir). Yanıt `IO\Api\ApiResponse`: sepet olayları dönünce plentyShop Vuex sepeti kendisi günceller.
- Seçenekler: `Plenty\Modules\Webshop\ItemSearch` — `isActive`, `isVisibleForClient`, `isSalable`, `hasPriceForCustomer`.

---

<a id="aktion-1"></a>
# Aktion 1 — 3 ELFA Pods al → 1 ELFA Basisgerät hediye (0.3.0)

Aynı yol (Aktion 2'de canlıda kanıtlandı): cihaz **gerçek tek renk varyasyonu, tam fiyatla (7,49 €)** sepete girer → eklenti Aktion 1 **Aktionsgutschein** kodunu uygular (Festbetrag 7,49 €) → cihaz fiilen 0 €. Pod 3'ün altına düşerse cihaz (1 adet) + kupon kalkar. Sipariş başına 1 cihaz. Tarih penceresi **yok**: kampanya yalnız „Aktion 1 aktiv“ ile açılır/kapanır.

Varsayılan: **Aktion 1 aktiv = hayır, Testmodus = evet, Gutscheincode boş** → bereitstellen edilse bile davranış değişmez. Kod boşken modül hiçbir şey yapmaz.

## A1-1. Ölçülen katalog (20.11.2026, Plenty API + mağazaların kendi IO uç noktası + misafir sepeti)

İki Mandant aynı Plenty sisteminde (57085) → **ID'ler iki mağazada aynı**.

**Hediye cihaz — ELFA Pod Kit 500 mAh, tek renk item'ları** (paket değil; `bundle_item`). Fiyat: satış fiyatı 1 „Aktionspreis Webshop“ = **7,49 €** (Lostmary + elfbargermany, müşteri sınıfı 2). İki mağazada da 9 renk görünür + satılabilir + misafir sepetine 7,49 € ile girdi ve kaldı (itemType 10).

| Renk | Item | Varyasyon | Nummer | Stok 113 | 115 | 107 |
|---|---|---|---|---|---|---|
| Aurora Blue | 11259 | 2548 | ELFA-KIT-AB | 901 | 240 | – |
| Aurora Purple | 11262 | 2551 | ELFA-KIT-AP | 852 | 350 | 36 |
| Black | 11272 | 2561 | ELFA-KIT-BL | 2891 | 160 | 84 |
| Navy Blue | 11273 | 2562 | ELFA-KIT-NB | 797 | 100 | 23 |
| White | 11278 | 2567 | ELFA-KIT-WH | 1121 | 210 | 31 |
| Twilight Blue | 11742 | 3095 | ELFA-KIT-TB | 996 | 300 | 23 |
| Aurora Green | 11743 | 3096 | ELFA-KIT-AG | 773 | 180 | 12 |
| Aurora Pink | 11744 | 3097 | ELFA-KIT-API | 1493 | 60 | 6 |
| Twilight Brown | 11745 | 3098 | ELFA-KIT-TBR | 4240 | 340 | 11 |

Not: 24vapestore'un Plenty karşılığı **24835 „ELFA Pod Kit – Alle Farben“** (varyasyonlar 109391–109399) **PAKET** ve yalnız 62081/62082'ye bağlı (Lostmary'de yok). Hediye olarak **kullanılmıyor**: elfbargermany paketleri bölmediği için sepette ayrı tipte (11) satır olurdu. Tek renk item'lar iki mağazada da aynı şekilde çalışıyor.

**Sepet satır tipleri (ölçüldü):** Lostmary paketleri **böler** (ör. 87121 → 86644 tek tat satırı, 109391 → 2561), elfbargermany **bölmez** (Sammelartikel pod'u itemType **11**). Aktion 1 tip 0/10/11 sayar.

**Sayılan ELFA Pods (Artikel-IDs, ayarda varsayılan dolu, 57 ID):**
- 52 tek tat item (20 mg + 0 mg), iki mağazada satılabilir: 11028 … 24529
- Filter Series tek item'lar 23695, 23696
- Sammelartikel (yalnız elfbargermany'de pod paketleri satılıyor): **12569** „ELFA Pods ALLE SORTEN“ (37 aktif paket), **24839** Filter Series, **24840** 0 mg

Sayılmayan: 10x/D10 B2B paketleri, EBAY 3er/5er, „Elfa Pods + Elfa Master/Turbo Bundle“ (24312, 24568), Refillable/Leer Pod, Turbo/Master cihazları.

## A1-2. Kuponları panelde aç (API ile açılamıyor)

Her Mandant için **bir** kampanya. Yol: **Aufträge » Gutscheine » Neue Kampagne**.

| Alan | Lostmary | elfbargermany |
|---|---|---|
| Name | Aktion 1 – ELFA Basisgerät gratis (Lostmary) | Aktion 1 – ELFA Basisgerät gratis (Elfbargermany) |
| Typ | **Aktionsgutschein** | **Aktionsgutschein** |
| Rabatt | **Festbetrag 7,49 €** | **Festbetrag 7,49 €** |
| Code-Länge | 16 Zeichen | 16 Zeichen |
| Code / Einlösung | **tek kod, sınırsız / çok kez** (müşteri başına sınırsız) | aynı |
| Mindestbestellwert | 0 | 0 |
| Gültigkeit | bugünden (test) → 01.12.2026 | 23.11.2026 → 01.12.2026 (test için bugünden) |
| **Mandant** | **yalnız Lostmarygermany.de (65060)** | **yalnız Elfbargermany (62081)** |
| **Filter** | **Kategorie 1282 „ELFA Basisgerät“** | **Kategorie 1282 „ELFA Basisgerät“** |
| Versandkosten | etkilenmesin | etkilenmesin |

- Neden kategori: Aktion 2'de ELFLIQ kuponunda **Artikel filtresi tutmadı** (24534), **Kategorie filtresine çevrilince çalıştı** (ELFX'te Artikel 14359 çalışmıştı). Kategori 1282 yalnız cihazları içeriyor (9 tek renk + 24835 + 128701 B2B), pod yok → güvenli. Kategori seçeneği yoksa: Artikel filtresi **11259, 11262, 11272, 11273, 11278, 11742, 11743, 11744, 11745**.
- Kaydet → 16 karakterlik **Code**'u kopyala → A1-3'teki ayara yapıştır. Kod repoya yazılmaz.
- Fiyat değişirse (Black Deals) Festbetrag'ı da değiştir.

## A1-3. Eklenti ayarları (sekme „Aktion 1: ELFA Pods → ELFA Gerät“)

| Ayar | Lostmary test | elfbargermany |
|---|---|---|
| Aktion 1 aktiv | **evet** | test: evet / canlı: evet |
| Aktion 1 Testmodus | **evet** | evet → canlıda hayır |
| Test-Kunden (Kontakt-IDs) | **591963** (varsayılan) | elfbargermany'de kayıtlı bir test kontağı (591963 Lostmary'ye kayıtlı; orada giriş yapamayabilir) |
| Anzahl ELFA Pods | **3** | 3 |
| ELFA Pods (Artikel-IDs) | varsayılan liste (dokunma) | aynı |
| ELFA Pods (Variations-IDs) | boş | boş |
| Geschenk ELFA Basisgerät (Artikel-IDs) | varsayılan **11259, 11262, 11272, 11273, 11278, 11742, 11743, 11744, 11745** | aynı |
| Aktion 1 Gutscheincode | Lostmary kampanyasının kodu | elfbargermany kampanyasının kodu |

Not: ayarlar plugin set başına. elfbargermany için ayrı set ise orada da girilir.

## A1-4. Container bağlantıları

Plugin-Set » VaporAktion2Test » **Container-Verknüpfungen** (yeni sağlayıcılar kendiliğinden bağlanmazsa elle):

| Data Provider | Container |
|---|---|
| Aktion 1: Script (Pflicht) | `Ceres::Script.AfterScriptsLoaded` |
| Aktion 1: Box Warenkorb | `Ceres::Basket.BeforeBasketTotals` |
| Aktion 1: Box Warenkorb-Vorschau | `Ceres::BasketPreview.BeforeBasketTotals` |
| Aktion 1: Box Artikelseite (optional) | `Ceres::SingleItem.AfterAddToBasket` |

Aktion 2 bağlantılarına dokunma. Ürün sayfası kutusu yalnız ayardaki pod item'larında görünür (öbür ürünlerde boş).

## A1-5. Aktion 2 ile birlikte

Plenty sepetinde **tek kupon** olur → iki kampanya aynı anda kupon isteyemez.
- Sepette Aktion 2 hediyesi (kodu) varken Aktion 1 kutusu: **„Nicht mit anderer Aktion kombinierbar …“**, renk seçtirmez. Tersi: Aktion 1 cihazı varken Aktion 2 kutusu kendi metnini gösterir („Aktion nicht mit anderen Gutscheinen kombinierbar …“) — Aktion 2'ye dokunulmadığı için metni değişmedi.
- Müşteri hangi hediyeyi önce seçerse o geçerli; diğerini isterse ilkini „Entfernen“ ile kaldırır, sonra öbür kutudan seçer.
- Biri elle diğerinin kodunu girerse: kupon değişir → ilk kampanya hediyesini kaldırır, ikinci kampanya „hediyesiz kod“ diye kodu siler → sepet hediyesiz/kuponsuz kalır (sızıntı koruması).

## A1-6. Test (591963 ile, Lostmary)

| # | Adım | Beklenen |
|---|---|---|
| 1 | Misafir: 3 pod (ör. 2294 ×2 + 2282) | Kutu yok (test modu) |
| 2 | 591963 ile giriş, 2 pod | „1/3 … Noch 1 ELFA Pod …“ + çubuk |
| 3 | 3. pod (farklı tat) | 9 renk kutucuğu, „7,49 € Gratis“ |
| 4 | Black → „Gratis-Gerät in den Warenkorb“ | Black 7,49 € + kupon −7,49 €; kutu „Dein Gratis ELFA Basisgerät …: Black“ |
| 5 | „Andere Farbe wählen“ → Navy Blue | Black gider, Navy Blue + kupon |
| 6 | Bir pod'u sil (2) | Cihaz + kupon gider |
| 7 | 3 pod + Black ücretli koy, sonra kutudan Black | Black 2 adet, kupon −7,49 (1 ücretli + 1 hediye); „Entfernen“ → 1 kalır |
| 8 | Aktion 2 hediyesi varken 3 pod | Aktion 1 kutusu „Nicht mit anderer Aktion kombinierbar“ |
| 9 | Hediye varken başka kupon gir | Cihaz kalkar, yabancı kupon kalır |
| 10 | Sepet önizlemesi (sağ panel) | Kompakt seçim (açılır liste + Hinzufügen) |
| 11 | Pod ürün sayfası (Artikelseite bağlıysa) | İlerleme/seçim kutusu; cihaz/başka ürün sayfasında kutu yok |
| 12 | Sipariş (Vorkasse) | Satır ELFA Kit 7,49 + kupon satırı −7,49; stok o renkten düşer; sonra iptal |
| 13 | Daten » Log » VaporAktion2Test | „Aktion 1: …“ satırları |
| 14 | Aktion 2 testleri (6 ELFLIQ + ELFX) | Değişmeden çalışır |

## A1-7. Canlıya alma / kapatma

- 23.11.: Testmodus = hayır (kod dolu, aktiv = evet) → bereitstellen.
- 30.11. akşam: **Aktion 1 aktiv = hayır** → bereitstellen (sepetlerde kalan Aktion 1 kodu bir sonraki değişimde silinir, kayıtlı cihaz satırı 1 adet azaltılır). En hızlı acil kapatma: panelde Aktion 1 kupon kampanyasını pasif yap.

## Teknik (Aktion 1)

- `src/Aktion1/`: `A1Regel` (saf kural), `A1GeschenkService`, `A1KatalogService` (yalnız aktif + görünür + satılabilir + fiyatlı renkler), `A1Controller`, dinleyiciler, container'lar.
- Oturum anahtarı `VaporAktion2Test_aktion1`; Aktion 2 kodları yalnız okunur (`gutscheinElfx`, `gutscheinElfliq`) → „başka kampanya“ mesajı.
- REST `GET/POST/DELETE /rest/vapor-aktion1/geschenk` (POST/DELETE `csrf`), JS fetch + tek `X-CSRF-TOKEN`, token yoksa sayfa yenilenir.
- Testler: `php tests/run_aktion1.php` (43) + `php tests/run.php` (Aktion 2, 50).
