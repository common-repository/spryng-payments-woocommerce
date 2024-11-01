<?php

namespace SpryngPaymentsApiPhp\Controller;

use SpryngPaymentsApiPhp\Client;
use SpryngPaymentsApiPhp\Helpers\ThreeDHelper;

/**
 * Class ThreeDController
 * @package SpryngPaymentsApiPhp\Controller
 */
class ThreeDController extends BaseController
{
    /**
     * The URI for the 3D Enrollment endpoint
     */
    const ENROLL_URI = '/3d/enroll';

    /**
     * The URI for the 3D Authorization endpoint
     */
    const AUTHORIZATION_URI = '/3d/authorization';

    /**
     * ThreeDController constructor.
     * @param Client $api
     */
    public function __construct(Client $api)
    {
        parent::__construct($api);
    }

    /**
     * Start a 3D Secure enrollment procedure
     *
     * $request is an array with elements:
     * account (ID)
     * amount
     * card (ID)
     * description
     *
     * @param $request array
     * @return \SpryngPaymentsApiPhp\Object\ThreeDEnrollment
     * @throws \SpryngPaymentsApiPhp\Exception\RequestException
     */
    public function enroll($request)
    {
        $http = $this->initiateRequestHandler('POST', $this->api->getApiEndpoint(), static::ENROLL_URI,
            array('X-APIKEY' => $this->api->getApiKey()), $request);
        $http->doRequest();

        return ThreeDHelper::fillThreeDEnrollment(json_decode($http->getResponse()));
    }

    /**
     * Authorize a card for 3D Secure
     *
     * @param $request
     * @return \SpryngPaymentsApiPhp\Object\ThreeDAuthorization
     * @throws \SpryngPaymentsApiPhp\Exception\RequestException
     */
    public function authorize($request)
    {
        $http = $this->initiateRequestHandler('POST', $this->api->getApiEndpoint(), static::AUTHORIZATION_URI,
            array('X-APIKEY' => $this->api->getApiKey()), $request);
        $http->doRequest();

        return ThreeDHelper::fillThreeDAuthorization(json_decode($http->getResponse()));
    }
}