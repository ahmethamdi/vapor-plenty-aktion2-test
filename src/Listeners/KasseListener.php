<?php

namespace VaporAktion2Test\Listeners;

use Plenty\Modules\Frontend\Events\ValidateCheckoutEvent;
use VaporAktion2Test\Services\GeschenkService;

/**
 * ValidateCheckoutEvent (IO CheckoutService::preparePayment, sipariş öncesi): son kontrol.
 * Kural sepeti değiştirdiyse (hediye/kupon kalktı, adet düzeldi) ödeme durdurulur ve müşteri
 * güncel sepeti görür. IO hata anahtarını Translator ile çevirip gösterir.
 */
class KasseListener
{
    public function handle(ValidateCheckoutEvent $event)
    {
        try {
            /** @var GeschenkService $service */
            $service = pluginApp(GeschenkService::class);
            if ($service->pruefen('kasse')) {
                $event->addErrorKey('VaporAktion2Test::Frontend.warenkorbAngepasst');
            }
        } catch (\Throwable $ex) {
            // sessiz
        }
    }
}
