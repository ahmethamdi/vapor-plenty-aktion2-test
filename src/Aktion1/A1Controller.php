<?php

namespace VaporAktion2Test\Aktion1;

use IO\Api\ApiResponse;
use IO\Api\ResponseCode;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;

/**
 * REST: /rest/vapor-aktion1/geschenk
 *  GET    ?itemId= → durum (itemId: ürün sayfasındaki item, "istPod" için)
 *  POST   {variationId} → renk seç / değiştir   (CSRF korumalı)
 *  DELETE → hediyeyi kaldır                     (CSRF korumalı)
 * ApiResponse, sepet işlemlerinden ÖNCE kurulmalı (olay dinleyicileri kurucuda bağlanır) → kurucu enjeksiyonu.
 */
class A1Controller extends Controller
{
    /** @var Request */
    private $request;

    /** @var ApiResponse */
    private $response;

    /** @var A1GeschenkService */
    private $service;

    public function __construct(Request $request, ApiResponse $response, A1GeschenkService $service)
    {
        $this->request  = $request;
        $this->response = $response;
        $this->service  = $service;
    }

    public function status(): Response
    {
        $itemId = (int)$this->request->get('itemId', 0);
        return $this->response->create($this->service->status($itemId), ResponseCode::OK);
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
