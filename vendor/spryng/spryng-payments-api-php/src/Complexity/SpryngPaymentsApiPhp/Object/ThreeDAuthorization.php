<?php

namespace SpryngPaymentsApiPhp\Object;

/**
 * Class ThreeDAuthorization
 * @package SpryngPaymentsApiPhp\Object
 */
class ThreeDAuthorization
{
    /**
     * The decoded cavv2 used to verify the request
     *
     * @var string
     */
    public $cavv2;

    /**
     * A response code from the 3D-Secure service
     *
     * @var integer
     */
    public $code;

    /**
     * The decoded eci used to verify the request
     *
     * @var string
     */
    public $eci;

    /**
     * A single character which tells about the authorization
     * result: 'Y' means authorized, 'N' means rejected and '?'
     * means that the request may be rejected or authorized
     *
     * @var string
     */
    public $status;
}