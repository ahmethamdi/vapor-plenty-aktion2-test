<?php

namespace VaporAktion2Test\Aktion1;

use Plenty\Plugin\Templates\Twig;

/**
 * DataProvider, Aktion 1: ürün sayfasında bilgi/seçim kutusu (Ceres::SingleItem.AfterAddToBasket).
 * plentyShop LTS bu container'a item.documents[0].data geçirir → item.id. Kutu yalnız ürün ayardaki
 * ELFA Pod listesindeyse görünür (sunucu denetler, yanıtta "istPod").
 */
class A1ArtikelBoxContainer
{
    public function call(Twig $twig, $arg = null): string
    {
        $itemId = 0;
        if (is_array($arg)) {
            if (isset($arg['item']['id'])) {
                $itemId = (int)$arg['item']['id'];
            } elseif (isset($arg['documents'][0]['data']['item']['id'])) {
                $itemId = (int)$arg['documents'][0]['data']['item']['id'];
            }
        }
        return $twig->render('VaporAktion2Test::Aktion1.Box', ['ort' => 'artikel', 'itemId' => $itemId]);
    }
}
