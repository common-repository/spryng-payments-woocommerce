<?php

class Spryng_Payments_WC_Klarna_Gateway extends Spryng_Payments_WC_Abstract_Gateway
{
    const METHOD_ID = 'spryng_payments_klarna';

    const DEFAULT_TITLE = 'Klarna';

    const DEFAULT_DESCRIPTION = 'Pay using Klarna afterpay';

    const PCLASS_POST_KEY = 'spryng-payments-woocommerce-pclass';

    public function __construct()
    {
        $this->supports = array(
            'products',
            'refunds'
        );

        $this->id = self::METHOD_ID;
        $this->has_fields = true;
        $this->icon = false;

        parent::__construct();

        add_action('woocommerce_checkout_update_order_review', array(__CLASS__, 'addSocialSecurityNumberForSweden'));
    }

    public function payment_fields()
    {
        if (!$this->is_available())
        {
            return;
        }

        parent::payment_fields();

        require_once(SPRYNG_DIR . '/views/public/klarna-payment-fields.php');
    }

    public function get_transaction($orderId)
    {
        $order = OrderUtil::get_order_by_id($orderId);
        $returnUrl = OrderUtil::get_return_url_for_order($order);
        $goodsList = KlarnaUtil::generate_goods_list(OrderUtil::get_order_by_id($orderId));
        $transaction = array(
            'payment_product' => 'klarna',
            'details' => array(
                'pclass' => (int) $_POST[self::PCLASS_POST_KEY],
                'goods_list' => $goodsList->toArray(),
                'redirect_url' => $returnUrl
            )
        );

        return array_merge($this->get_default_transaction_parameters($orderId), $transaction);
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $data = parent::process_refund($order_id, $amount, $reason);

        $order = $data['order'];
        $transaction = $data['transaction'];

        SpryngUtil::get_instance()->Klarna->refund($transaction->_id, $data['amount'], $data['reason']);

        // Add a note to the order
        $order->add_order_note(
            sprintf(__('Refunded %s%s (reason: %s) - Payment ID: %s.',
                get_woocommerce_currency_symbol(),
                $transaction->amount,
                $reason,
                $transaction->_id))
        );

        return true;
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