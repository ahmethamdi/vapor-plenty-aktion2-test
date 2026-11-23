<?php

namespace VaporAktion2\Providers;

use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Frontend\Events\ValidateCheckoutEvent;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\ServiceProvider;
use VaporAktion2\Listeners\KasseListener;
use VaporAktion2\Listeners\WarenkorbGeaendertListener;
use VaporAktion2\Services\GeschenkService;
use VaporAktion2\Services\KatalogService;

/**
 * Eklentinin giriş noktası: servisler, REST rotaları, sepet ve kasa olay dinleyicileri.
 * Sepet kutusu ve script, plugin.json'daki dataProviders ile plentyShop LTS container'larına bağlanır.
 */
class VaporAktion2ServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Tek örnek: döngü kilidi ($laeuft) ve arama önbelleği istek boyunca aynı nesnede durmalı.
        $this->getApplication()->singleton(KatalogService::class);
        $this->getApplication()->singleton(GeschenkService::class);
        $this->getApplication()->register(VaporAktion2RouteServiceProvider::class);
    }

    public function boot(Dispatcher $dispatcher)
    {
        $dispatcher->listen(AfterBasketChanged::class, WarenkorbGeaendertListener::class);
        $dispatcher->listen(ValidateCheckoutEvent::class, KasseListener::class);
    }
}
