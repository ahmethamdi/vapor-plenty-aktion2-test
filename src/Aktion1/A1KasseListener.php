<?php

namespace VaporAktion2Test\Aktion1;

use Plenty\Modules\Frontend\Events\ValidateCheckoutEvent;

/**
 * ValidateCheckoutEvent (sipariş öncesi): kural sepeti değiştirdiyse ödeme durur, müşteri güncel sepeti görür.
 */
class A1KasseListener
{
    public function handle(ValidateCheckoutEvent $event)
    {
        try {
            /** @var A1GeschenkService $service */
            $service = pluginApp(A1GeschenkService::class);
            if ($service->pruefen('kasse')) {
                $event->addErrorKey('VaporAktion2Test::Aktion1.warenkorbAngepasst');
            }
        } catch (\Throwable $ex) {
            // sessiz
        }
    }
}
