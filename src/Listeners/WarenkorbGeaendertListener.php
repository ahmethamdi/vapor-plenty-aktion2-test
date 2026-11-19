<?php

namespace VaporAktion2Test\Listeners;

use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use VaporAktion2Test\Services\GeschenkService;

/**
 * AfterBasketChanged dinleyicisi: her sepet değişiminden sonra hediye kuralını uygular.
 * Ekleme, silme ve adet değişimi bu olayı tetikler (IO BasketService / Core).
 */
class WarenkorbGeaendertListener
{
    public function handle(AfterBasketChanged $event)
    {
        /** @var GeschenkService $service */
        $service = pluginApp(GeschenkService::class);
        $service->pruefen();
    }
}
