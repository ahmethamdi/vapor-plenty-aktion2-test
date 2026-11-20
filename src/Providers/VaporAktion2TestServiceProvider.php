<?php

namespace VaporAktion2Test\Providers;

use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Frontend\Events\ValidateCheckoutEvent;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\ServiceProvider;
use VaporAktion2Test\Aktion1\A1GeschenkService;
use VaporAktion2Test\Aktion1\A1KasseListener;
use VaporAktion2Test\Aktion1\A1KatalogService;
use VaporAktion2Test\Aktion1\A1WarenkorbListener;
use VaporAktion2Test\Listeners\KasseListener;
use VaporAktion2Test\Listeners\WarenkorbGeaendertListener;
use VaporAktion2Test\Services\GeschenkService;
use VaporAktion2Test\Services\KatalogService;

/**
 * Eklentinin giriş noktası: servisler, REST rotaları, sepet ve kasa olay dinleyicileri (Aktion 2 + Aktion 1).
 * Sepet kutusu ve script, plugin.json'daki dataProviders ile plentyShop LTS container'larına bağlanır.
 */
class VaporAktion2TestServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Tek örnek: döngü kilidi ($laeuft) ve arama önbelleği istek boyunca aynı nesnede durmalı.
        $this->getApplication()->singleton(KatalogService::class);
        $this->getApplication()->singleton(GeschenkService::class);
        // Aktion 1 (3 ELFA Pods → 1 ELFA Basisgerät): kendi tek örnekleri, Aktion 2'den bağımsız
        $this->getApplication()->singleton(A1KatalogService::class);
        $this->getApplication()->singleton(A1GeschenkService::class);
        $this->getApplication()->register(VaporAktion2TestRouteServiceProvider::class);
    }

    public function boot(Dispatcher $dispatcher)
    {
        $dispatcher->listen(AfterBasketChanged::class, WarenkorbGeaendertListener::class);
        $dispatcher->listen(ValidateCheckoutEvent::class, KasseListener::class);
        $dispatcher->listen(AfterBasketChanged::class, A1WarenkorbListener::class);
        $dispatcher->listen(ValidateCheckoutEvent::class, A1KasseListener::class);
    }
}
