<?php

namespace SpryngPaymentsApiPhp\Controller;

use SpryngPaymentsApiPhp\Client;

class MandateController extends BaseController
{
    const MANDATE_URI = "/mandate";

    public function __construct(Client $api)
    {
        parent::__construct($api);
    }

    /**
     * @param null $customerId
     * @return array
     * @throws \SpryngPaymentsApiPhp\Exception\RequestException
     */
    public function list($customerId = null)
    {
        $uri = self::MANDATE_URI;
        if (!is_null($customerId))
            $uri .= '?customer='.$customerId;

        $http = $this->initiateRequestHandler('GET', $this->api->getApiEndpoint(), $uri,
            array('X-APIKEY' => $this->api->getApiKey()));

        $http->doRequest();
        $response = $http->getResponse();
        $jsonResponse = json_decode($response);

        return $jsonResponse;
    }
}