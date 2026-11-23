# Kurulum ve test — VaporAktion2 0.2.0 (dal `kampagne`)

Kampanya: **6 Aktions-Artikel (ELFLIQ) al → 1 ELFLIQ ya da 1 ELFX Pod Kit hediye.**

Nasıl çalışır (20.11.2026 canlı testiyle kanıtlanan yol):
hediye **gerçek varyasyon, tam fiyatla** sepete girer → eklenti aynı anda hediye grubunun **Aktionsgutschein** kodunu sepete uygular (Festbetrag = hediye fiyatı, Artikel filtresi = hediye ürünü) → hediye fiilen 0 €. Hak düşerse ya da müşteri hediyeyi kaldırırsa eklenti **hediyeyi + kuponu** kaldırır.

> Eklenti varsayılan **pasif** ve **test modunda**. Plugin set'ini bereitstellen etmek tüm seti yeniden derler: sakin bir saatte yap.

---

## 1. Dalı değiştir (Lostmary plugin set'i)

`main` eski deneme sürümü (0.1.x) olarak kalır; yeni sürüm `kampagne` dalında.

1. Plugins » Plugin-Set-Übersicht » **Lostmarygermany.de** setini aç » **VaporAktion2** satırı.
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

Plugin-Set » VaporAktion2 » **Konfiguration**.

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

Plugin-Set » VaporAktion2 » **Container-Verknüpfungen** (eklenti varsayılanları getirir; kontrol et):

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
| 13 | Daten » Log » Integration **VaporAktion2** | „Kunde hat ein Geschenk gewählt“, „Zu wenige Aktions-Artikel …“, „Aktions-Gutschein ohne Geschenk … entfernt“ satırları |

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
