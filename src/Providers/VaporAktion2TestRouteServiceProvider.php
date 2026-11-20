<?php

namespace VaporAktion2Test\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\ApiRouter;
use Plenty\Plugin\Routing\Router;

/**
 * REST rotaları (/rest/ öneki ApiRouter'dan gelir). Yazan rotalar IO'daki gibi 'csrf' middleware'i ile:
 * plentyShop LTS her AJAX isteğine #csrf-token değerini X-CSRF-TOKEN başlığı olarak ekler.
 */
class VaporAktion2TestRouteServiceProvider extends RouteServiceProvider
{
    public function map(Router $router, ApiRouter $api)
    {
        $api->version(['v1'], ['namespace' => 'VaporAktion2Test\Controllers'], function (ApiRouter $api) {
            $api->get('vapor-aktion2/geschenk', 'GeschenkController@status');
        });

        $api->version(['v1'], ['namespace' => 'VaporAktion2Test\Controllers', 'middleware' => ['csrf']], function (ApiRouter $api) {
            $api->post('vapor-aktion2/geschenk', 'GeschenkController@waehlen');
            $api->delete('vapor-aktion2/geschenk', 'GeschenkController@entfernen');
        });

        // Aktion 1 (3 ELFA Pods → 1 ELFA Basisgerät)
        $api->version(['v1'], ['namespace' => 'VaporAktion2Test\Aktion1'], function (ApiRouter $api) {
            $api->get('vapor-aktion1/geschenk', 'A1Controller@status');
        });

        $api->version(['v1'], ['namespace' => 'VaporAktion2Test\Aktion1', 'middleware' => ['csrf']], function (ApiRouter $api) {
            $api->post('vapor-aktion1/geschenk', 'A1Controller@waehlen');
            $api->delete('vapor-aktion1/geschenk', 'A1Controller@entfernen');
        });
    }
}
