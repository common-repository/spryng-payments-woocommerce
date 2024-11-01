<?php

/**
 * @license         Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 * @author          Roemer Bakker
 * @copyright       Complexity Software
 */

namespace SpryngPaymentsApiPhp\Object;

/**
 * Class Spryng_Payments_Api_Object_Card
 * @package SpryngPaymentsApiPhp\Object
 */
class Card
{
    /**
     * The card's unique identifier.
     *
     * @var string
     */
    public $_id;

    /**
     * The The Bank Identification Number.
     *
     * @var string
     */
    public $bin;

    /**
     * The card brand e.g. Mastercard.
     *
     * @var string
     */
    public $brand;

    /**
     * Country of residence of the card holder
     */
    public $card_holder_country;

    /**
     * Name of the card holder.
     *
     * @var string
     */
    public $card_holder_name;

    /**
     * Indicates if the card is 3D secured
     *
     * @var bool
     */
    public $cvv_verified;

    /**
     * Indicates the customer the card belongs to.
     *
     * @var Customer
     */
    public $customer;

    /**
     * Card expiry month. Two digits in length.
     *
     * @var integer
     */
    public $expiry_month;

    /**
     * Card expiry year. Two digits in length.
     *
     * @var integer
     */
    public $expiry_year;

    /**
     * Two-letter ISO country code identifying the country of issuance.
     *
     * @var string
     */
    public $issuer_country;

    /**
     * The name of the card issuer.
     *
     * @var string
     */
    public $issuer_name;

    /**
     * Last four digits of the card.
     *
     * @var string
     */
    public $last_four;

    /**
     * The sub-type of the card value is either "credit", "debit" or "prepaid".
     *
     * @var string
     */
    public $type;

    /**
     * The date this card was last checked for updated by an AU module
     *
     * @var string
     */
    public $last_update_check;

    /**
     * The ID of the organisation this card belongs to
     *
     * @var string
     */
    public $organisation;

    /**
     * The currency of the card
     *
     * @var string
     */
    public $currency;

    /**
     * Indicated whether the card is prepaid
     *
     * @var bool
     */
    public $prepaid;

    /**
     * The card variant
     *
     * @var string
     */
    public $variant;
}