# VaporAktion2Test

PlentyONE / plentyShop LTS 5 (IO 5.0.x) kampanya eklentisi: **6 Aktions-Artikel (ELFLIQ) al → 1 ELFLIQ ya da 1 ELFX Pod Kit hediye.**

Hediye gerçek varyasyon olarak tam fiyatla sepete girer; panelde açılan **Aktionsgutschein** (Festbetrag) fiyatı sıfırlar. Seçim, sepet sayfasındaki (ve sepet önizlemesindeki) kutudan yapılır.

- Kurulum, kuponlar ve test adımları: [KURULUM.md](KURULUM.md)
- Eklenti belgesi (Almanca): [meta/documents/user_guide_de.md](meta/documents/user_guide_de.md)
- Yerel testler: `php tests/run.php` (Plenty gerektirmez; sahte sınıflar `tests/stubs.php`)

Dal: yalnız `main` (Plenty her Bereitstellen'de main'in son hâlini çeker). main'e yalnız denenmiş kod.

**0.3.0'dan itibaren ikinci kampanya: Aktion 1 — 3 ELFA Pods al → 1 ELFA Basisgerät hediye** (`src/Aktion1/`, varsayılan kapalı). Kurulum: [KURULUM.md](KURULUM.md#aktion-1) · testler: `php tests/run_aktion1.php`.
