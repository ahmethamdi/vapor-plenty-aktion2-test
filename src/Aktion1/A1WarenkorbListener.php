<?php

namespace VaporAktion2Test\Aktion1;

use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;

/**
 * AfterBasketChanged: her sepet değişiminden sonra Aktion 1 kuralını uygular (Aktion 2 dinleyicisinden bağımsız).
 */
class A1WarenkorbListener
{
    public function handle(AfterBasketChanged $event)
    {
        try {
            /** @var A1GeschenkService $service */
            $service = pluginApp(A1GeschenkService::class);
            $service->pruefen('warenkorb');
        } catch (\Throwable $ex) {
            // Sepet akışı asla bizim yüzümüzden durmamalı
        }
    }
}
