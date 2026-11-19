<?php

namespace VaporAktion2Test\Providers;

use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\ServiceProvider;
use VaporAktion2Test\Listeners\WarenkorbGeaendertListener;
use VaporAktion2Test\Services\GeschenkService;

/**
 * Eklentinin giriş noktası.
 * Tek iş: sepet değiştiğinde (AfterBasketChanged) hediye kuralını çalıştırmak.
 */
class VaporAktion2TestServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Tek örnek: özyineleme kilidi ($laeuft) istek boyunca aynı nesnede durmalı.
        $this->getApplication()->singleton(GeschenkService::class);
    }

    public function boot(Dispatcher $dispatcher)
    {
        $dispatcher->listen(AfterBasketChanged::class, WarenkorbGeaendertListener::class);
    }
}
