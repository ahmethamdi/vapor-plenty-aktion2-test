<?php

namespace VaporAktion2\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\ApiRouter;
use Plenty\Plugin\Routing\Router;

/**
 * REST rotaları (/rest/ öneki ApiRouter'dan gelir). Yazan rotalar IO'daki gibi 'csrf' middleware'i ile:
 * plentyShop LTS her AJAX isteğine #csrf-token değerini X-CSRF-TOKEN başlığı olarak ekler.
 */
class VaporAktion2RouteServiceProvider extends RouteServiceProvider
{
    public function map(Router $router, ApiRouter $api)
    {
        $api->version(['v1'], ['namespace' => 'VaporAktion2\Controllers'], function (ApiRouter $api) {
            $api->get('vapor-aktion2/geschenk', 'GeschenkController@status');
        });

        $api->version(['v1'], ['namespace' => 'VaporAktion2\Controllers', 'middleware' => ['csrf']], function (ApiRouter $api) {
            $api->post('vapor-aktion2/geschenk', 'GeschenkController@waehlen');
            $api->delete('vapor-aktion2/geschenk', 'GeschenkController@entfernen');
        });
    }
}
