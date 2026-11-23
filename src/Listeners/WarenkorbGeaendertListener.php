<?php

namespace VaporAktion2\Listeners;

use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use VaporAktion2\Services\GeschenkService;

/**
 * AfterBasketChanged: her sepet değişiminden (ekleme, silme, adet, kupon) sonra kampanya kuralını uygular.
 */
class WarenkorbGeaendertListener
{
    public function handle(AfterBasketChanged $event)
    {
        try {
            /** @var GeschenkService $service */
            $service = pluginApp(GeschenkService::class);
            $service->pruefen('warenkorb');
        } catch (\Throwable $ex) {
            // Sepet akışı asla bizim yüzümüzden durmamalı (GeschenkService kendi içinde loglar)
        }
    }
}
