<?php

class Spryng_Payments_WC_Paypal_Gateway extends Spryng_Payments_WC_Abstract_Gateway
{
    const METHOD_ID = 'spryng_payments_paypal';

    const DEFAULT_TITLE = 'PayPal';

    const DEFAULT_DESCRIPTION = 'Pay securely using your own PayPal account.';

    public function __construct()
    {
        $this->supports = array(
            'products',
            'refunds'
        );

        $this->id = self::METHOD_ID;
        $this->has_fields = false;
        $this->icon = false;

        parent::__construct();
    }

    public function getReturnUrl(WC_Order $order)
    {
        $returnUrl = WC()->api_request_url('spryng_payments_return');
        $returnUrl = add_query_arg(array(
            'order_id' => $order->get_id(),
            'key' => $order->get_order_key()
        ), $returnUrl);

        return apply_filters(Spryng_Payments_WC_Plugin::PLUGIN_ID.'_return_url', $returnUrl, $order);
    }

    public function get_transaction($orderId)
    {
        $returnUrl = OrderUtil::get_return_url_for_order(OrderUtil::get_order_by_id($orderId));
        $transaction = array(
            'payment_product' => 'paypal',
            'details' => array(
                'redirect_url' => $returnUrl,
                'capture_now'  => true
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