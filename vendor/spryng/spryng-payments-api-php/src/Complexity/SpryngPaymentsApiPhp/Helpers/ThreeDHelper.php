<?php

namespace SpryngPaymentsApiPhp\Helpers;

use SpryngPaymentsApiPhp\Object\ThreeDAuthorization;
use SpryngPaymentsApiPhp\Object\ThreeDEnrollment;

class ThreeDHelper
{
    /**
     * Takes a raw API response and turns it into a ThreeDEnrollment object
     *
     * @param $response
     * @return ThreeDEnrollment
     */
    public static function fillThreeDEnrollment($response)
    {
        $enrollment = new ThreeDEnrollment();

        $enrollment->pareq  = (isset($response->pareq)) ? $response->pareq : null;
        $enrollment->url    = (isset($response->url)) ? $response->url : null;

        return $enrollment;
    }

    /**
     * Takes a raw API response and turns it into a ThreeDAuthorization object
     *
     * @param $response
     * @return ThreeDAuthorization
     */
    public static function fillThreeDAuthorization($response)
    {
        $authorization = new ThreeDAuthorization();

        $authorization->cavv2    = (isset($response->cavv2)) ? $response->cavv2 : null;
        $authorization->code     = (isset($response->code)) ? $response->code : null;
        $authorization->eci      = (isset($response->eci)) ? $response->eci : null;
        $authorization->status   = (isset($response->status)) ? $response->status : null;

        return $authorization;
    }

}