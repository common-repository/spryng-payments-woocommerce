<?php

class Spryng_Payments_WC_Bancontact_Gateway extends Spryng_Payments_WC_Abstract_Gateway
{
    const METHOD_ID = 'spryng_payments_bancontact';

    const DEFAULT_TITLE = 'Bancontact';

    const DEFAULT_DESCRIPTION = 'Authorize the payment directly via your own bank.';

    const BANCONTACT_CARD_TOKEN_POST_KEY = 'spryng_payments_wc_bancontact_gateway-card-token';

    public $method_description = self::DEFAULT_DESCRIPTION;

    public function __construct()
    {
        $this->id = self::METHOD_ID;
        $this->has_fields = true;
        $this->icon = false;

        parent::__construct();

        $this->supports = array_merge($this->supports, array('products', 'refunds'));
    }

    public function is_available()
    {
        return parent::is_available();
    }

    public function payment_fields()
    {
        // Since 'default_credit_card_form' is deprecated, we have to use this class
        $cc = new WC_Payment_Gateway_CC();
        $cc->id = $this->id;
        $cc->form(); // Echo's the WC default credit card form

        require(SPRYNG_DIR . '/views/public/credit-card-payment-fields.php'); // Add tokenize JS
    }

    public function get_transaction($orderId)
    {
        $returnUrl = OrderUtil::get_return_url_for_order(OrderUtil::get_order_by_id($orderId));
        $transaction = array(
            'payment_product' => 'bancontact',
            'card' => $_POST[static::BANCONTACT_CARD_TOKEN_POST_KEY],
            'details' => array(
                'redirect_url' => $returnUrl
            )
        );

        return array_merge($this->get_default_transaction_parameters($orderId), $transaction);
    }

    /**
     * Returns the gateway's default title
     *
     * @return string
     */
    public function get_default_gateway_title()
    {
        return self::DEFAULT_TITLE;
    }

    /**
     * Returns the gateways default description
     *
     * @return string
     */
    public function get_default_gateway_description()
    {
        return self::DEFAULT_DESCRIPTION;
    }
}