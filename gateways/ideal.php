<?php

class Spryng_Payments_WC_iDeal_Gateway extends Spryng_Payments_WC_Abstract_Gateway
{
    const METHOD_ID = 'spryng_payments_ideal';

    const DEFAULT_TITLE = 'iDEAL';

    const DEFAULT_DESCRIPTION = 'Selecteer uw bank';

    static $ISSUERS = array(
        'ABNANL2A' => 'ABN Amro',
        'ASNBNL21' => 'ASN Bank',
        'BUNQNL2A' => 'Bunq',
        'FVLBNL22' => 'Van Lanschot Bankiers',
        'INGBNL2A' => 'ING',
        'KNABNL2H' => 'Knab',
        'RABONL2U' => 'Rabobank',
        'RBRBNL21' => 'Regiobank',
        'SNSBNL2A' => 'SNS Bank',
        'TRIONL2U' => 'Triodos Bank'
    );

    public function __construct()
    {
        $this->supports = array(
            'products',
            'refunds'
        );

        $this->id = self::METHOD_ID;
        $this->has_fields = true;

        parent::__construct();
    }

    public function payment_fields()
    {
        parent::payment_fields();

        $html = '<select name="spryng_payments_wc_ideal_gateway-issuer" >';
        foreach(self::$ISSUERS as $key => $issuer)
        {
            $html .= "<option value='".$key."' >". esc_html($issuer) ."</option>";
        }
        $html .= "</select>";

        echo wpautop(wptexturize($html));
    }

    protected function getReturnUrl(WC_Order $order)
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
            'payment_product' => 'ideal',
            'details' => array(
                'issuer' => $_POST['spryng_payments_wc_ideal_gateway-issuer'],
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
