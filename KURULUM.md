# Kurulum ve test — VaporAktion2Test

Amaç tek soru: eklenti sepete **0,00 € gerçek ürün satırı** koyabiliyor mu, bu satır siparişe 0,00 € olarak geçiyor mu, stok nereden düşüyor?

> Eklenti varsayılan **pasif** ve yalnız **test müşteri sınıfında** çalışır. Yine de plugin set'i bereitstellen etmek tüm seti yeniden derler: sakin bir saatte yap.

## 1. Hazırlık (Plenty arka ofisi)

1. **Test müşteri sınıfı:** Einrichtung » CRM » Kundenklassen → yeni sınıf (ör. „Test Aktion2“) → **ID'sini not et**.
2. **Test müşterisi:** CRM » Kontakte → test kontağı aç (ya da mevcut test kontağı) → Kundenklasse = „Test Aktion2“. Bu kontakla webshop girişi olmalı.
3. **Tetikleyici:** normal bir ELFLIQ varyasyonu seç → **Variations-ID**'sini not et.
4. **Hediye varyasyonu (Test A — ayrı 0 € varyasyon):** yeni bir varyasyon (ör. „ELFLIQ Gratis – Aktion 2 TEST“):
   - aktiv, Mandant **Lostmarygermany.de** için verfügbar
   - Verkaufspreis **0,00 €** (Mandant/Herkunft webshop'u kapsasın)
   - kategoriye bağlama, listelerde görünmesin
   - Bestand: Netto-Warenbestand'a ör. 5 adet koy, **önce/sonra** sayısını not et
   - **Variations-ID**'sini not et (tetikleyiciden farklı olmalı)

## 2. Eklentiyi Git ile bağlama

1. Plugins » Plugin-Set-Übersicht » **Lostmarygermany.de** setini aç » **Plugin hinzufügen** » **Git**.
2. Remote-URL: `https://github.com/ahmethamdi/vapor-plenty-aktion2-test.git`
3. Benutzername: GitHub kullanıcı adın. Passwort: **GitHub kişisel erişim token'ın** (fine-grained, yalnız bu depo, *Contents: Read-only*). Token'ı sen gir — depoya, bu dosyaya, sohbete yazma.
4. Branch: `main` → Speichern.
5. Sette eklentiyi seç → **Installieren** (branch `main`), sonra **Aktiv** işaretle.
6. Eklentinin **Konfiguration** sekmesi:
   - Aktion aktiv: **evet**
   - Test-Kundenklassen: 1. adımdaki ID (birden çoksa `9, 12`)
   - Auslöser-Variations-ID: tetikleyici
   - Geschenk-Variations-ID: hediye
   - Preis-Modus: **Variante**
7. Seti **bereitstellen** (Plugin-Set bereitstellen). Bitince yeşil olmalı; hata varsa Daten » Log'a bak.

## 3. Test A — ayrı 0 € varyasyon

1. Webshop'a **test müşterisiyle giriş yap** (misafir ya da başka sınıf: eklenti hiçbir şey yapmaz).
2. Tetikleyiciyi sepete ekle → sepette ikinci satır olarak **hediye, 0,00 €** görünmeli.
3. Hediye adedini 3 yap → sayfayı yenile → 1'e dönmeli.
4. Tetikleyiciyi sil → hediye de silinmeli. Tekrar ekle.
5. Daten » Log → Integration **VaporAktion2Test** → „Geschenk-Variation wurde … hinzugefügt“ ve „… Preis 0,00 €“ satırı. Ek bilgide `price`, `itemType`, `priceId` yazar. **„ACHTUNG … größer als 0,00 €“** görürsen fiyat 0 değil: kaydet, dur.
6. Siparişi ver (ör. Vorkasse).
7. Aufträge » Auftrag öffnen → **Positionen**: hediye satırı 0,00 € mi, tipi ne (Variante)? Toplam doğru mu?
8. Stok: hediye varyasyonunun **reserviert** / Netto-Warenbestand'ı değişti mi? (Fiziksel düşüş Warenausgang'da olur.)
9. Test siparişini **iptal et/sil**.

## 4. Test B — hediye = Artikelpaket (isteğe bağlı)

Amaç: stok gerçek ELFLIQ'ten düşsün.
1. Paket varyasyon oluştur: Paket-Typ **Artikelpaket**, içinde **1× gerçek ELFLIQ varyasyonu**, paket Verkaufspreis **0,00 €**.
2. Konfigürasyonda Geschenk-Variations-ID = paket varyasyonu → bereitstellen.
3. Test A'nın 2–8 adımlarını tekrarla. Sepette paket satırı mı yoksa bileşen satırı mı göründüğünü, siparişte paket + Paketbestandteil satırlarının fiyatlarını ve **hangi varyasyonun stoğunun** (paket mi, bileşen mi) değiştiğini not et.
4. Not: Ceres'in kendi „sepete ekle“si, webshop ayarında paketler bölünüyorsa paketi bileşenlerine ayırıp **bileşenin normal fiyatıyla** ekler. Eklenti çekirdek depoyu doğrudan çağırdığı için bu bölmeden geçmez; ama sonuç yine testle görülmeli.

## 5. Test C — givenPrice deneyi (isteğe bağlı)

1. Preis-Modus = **givenPrice**, Geschenk-Variations-ID = **fiyatı olan** normal bir varyasyon (tetikleyiciden farklı) → bereitstellen.
2. Tetikleyiciyi sepete ekle → log'da `price` 0 mı? Sepette 0,00 € mi?
3. 0 ise sipariş ver ve sipariş satırına bak. 0 değilse: çekirdek `givenPrice`'ı webshop sepetinde dikkate almıyor demektir. Ayrı varyasyon (A) ya da paket (B) kullanılır.

## 6. Geri alma

1. Konfigürasyonda **Aktion aktiv = hayır** ya da sette eklentinin **Aktiv** işaretini kaldır.
2. **Plugin-Set bereitstellen**.
3. Test siparişlerini iptal et, test müşterisinin sınıfını geri al, hediye/paket varyasyonunu pasif yap.
4. İstersen eklentiyi setten sil ve GitHub token'ını iptal et (GitHub › Settings › Developer settings › Tokens).

## Sonucu bildirirken

Şunları yaz: Test A/B/C için sepet fiyatı, sipariş satırı fiyatı ve tipi, stoğu değişen varyasyon, log satırları (ekran görüntüsü yeter).
