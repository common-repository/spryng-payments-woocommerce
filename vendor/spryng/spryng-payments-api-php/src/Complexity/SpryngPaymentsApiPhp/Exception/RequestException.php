<?php

namespace SpryngPaymentsApiPhp\Exception;

use SpryngPaymentsApiPhp\SpryngPaymentsException;

/**
 * Class RequestException
 *
 * Thrown when a request receives a non-successful response
 *
 * @package SpryngPaymentsApiPhp\Exception
 */
class RequestException extends SpryngPaymentsException
{
    protected $responseCode;
    protected $response;

    const INVALID_RESPONSE          = 101;
    const INVALID_HTTP_METHOD       = 102;

    /**
     * Get the response code of the failed request
     *
     * @return int
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * Sets the response code for a failed request
     *
     * @param int $responseCode
     */
    public function setResponseCode($responseCode)
    {
        $this->responseCode = $responseCode;
    }

    /**
     * Get the response for the failed request
     *
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Sets the response for a failed request
     *
     * @param string $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }


}