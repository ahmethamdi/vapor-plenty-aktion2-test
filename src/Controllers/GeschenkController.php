<?php

namespace VaporAktion2\Controllers;

use IO\Api\ApiResponse;
use IO\Api\ResponseCode;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use VaporAktion2\Services\GeschenkService;

/**
 * REST: /rest/vapor-aktion2/geschenk
 *  GET    → durum (sepet kutusu)
 *  POST   → {variationId} hediye seç / değiştir   (CSRF korumalı)
 *  DELETE → hediyeyi kaldır                        (CSRF korumalı)
 *
 * Yanıt IO ApiResponse ile kurulur: sepet değiştiyse "events.AfterBasketChanged" (sepet + satırlar) eklenir,
 * plentyShop LTS'in ApiService'i (jQuery ajaxComplete) bunu okuyup Vuex sepetini kendisi günceller.
 * ApiResponse kurucu içinde olay dinleyicilerini bağladığı için sepet işlemlerinden ÖNCE oluşturulmalı
 * (bu yüzden kurucu enjeksiyonu).
 */
class GeschenkController extends Controller
{
    /** @var Request */
    private $request;

    /** @var ApiResponse */
    private $response;

    /** @var GeschenkService */
    private $service;

    public function __construct(Request $request, ApiResponse $response, GeschenkService $service)
    {
        $this->request  = $request;
        $this->response = $response;
        $this->service  = $service;
    }

    public function status(): Response
    {
        return $this->response->create($this->service->status(), ResponseCode::OK);
    }

    public function waehlen(): Response
    {
        $variationId = (int)$this->request->get('variationId', 0);
        return $this->response->create($this->service->waehlen($variationId), ResponseCode::OK);
    }

    public function entfernen(): Response
    {
        return $this->response->create($this->service->entfernen(), ResponseCode::OK);
    }
}
