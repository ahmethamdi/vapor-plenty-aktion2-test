# VaporAktion2Test

PlentyONE / plentyShop LTS 5 (IO 5.0.x) kampanya eklentisi: **6 Aktions-Artikel (ELFLIQ) al → 1 ELFLIQ ya da 1 ELFX Pod Kit hediye.**

Hediye gerçek varyasyon olarak tam fiyatla sepete girer; panelde açılan **Aktionsgutschein** (Festbetrag) fiyatı sıfırlar. Seçim, sepet sayfasındaki (ve sepet önizlemesindeki) kutudan yapılır.

- Kurulum, kuponlar ve test adımları: [KURULUM.md](KURULUM.md)
- Eklenti belgesi (Almanca): [meta/documents/user_guide_de.md](meta/documents/user_guide_de.md)
- Yerel testler: `php tests/run.php` (Plenty gerektirmez; sahte sınıflar `tests/stubs.php`)

Dal düzeni: `main` = eski deneme (0.1.x), `kampagne` = bu sürüm. Plenty "Plugin Build 2" kurulu dalın son hâlini çeker.
