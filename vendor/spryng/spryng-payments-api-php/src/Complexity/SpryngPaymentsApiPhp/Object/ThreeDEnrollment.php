<?php

namespace SpryngPaymentsApiPhp\Object;

/**
 * Class ThreeDEnrollment
 * @package SpryngPaymentsApiPhp\Object
 */
class ThreeDEnrollment
{
    /**
     * The payment enrollment request
     *
     * @var string
     */
    public $pareq;

    /**
     * The URL the user will be redirected to
     *
     * @var string
     */
    public $url;
}