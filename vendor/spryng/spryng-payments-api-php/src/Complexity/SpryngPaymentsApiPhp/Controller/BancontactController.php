<?php

namespace SpryngPaymentsApiPhp\Controller;

use SpryngPaymentsApiPhp\Helpers\BancontactHelper;
use SpryngPaymentsApiPhp\Helpers\TransactionHelper;

/**
 * Class BacontactController
 * @package SpryngPaymentsApiPhp\Controller
 */
class BancontactController extends BaseController
{
    const BANCONTACT_INITIATE_URL = '/transaction/bancontact/initiate';

    /**
     * Initiate a new Bancontact transaction.
     *
     * @param $parameters
     * @return \SpryngPaymentsApiPhp\Object\Transaction
     * @throws \SpryngPaymentsApiPhp\Exception\RequestException
     * @throws \SpryngPaymentsApiPhp\Exception\TransactionException
     */
    public function initiate($parameters)
    {
        BancontactHelper::validateInitiateArguments($parameters);

        $http = $this->initiateRequestHandler('POST', $this->api->getApiEndpoint(), static::BANCONTACT_INITIATE_URL,
            array('X-APIKEY' => $this->api->getApiKey()), $parameters);

        $http->doRequest();
        $transaction = TransactionHelper::fillTransaction(json_decode($http->getResponse()));

        return $transaction;
    }
}