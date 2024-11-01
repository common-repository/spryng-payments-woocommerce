<?php

/**
 * CreditCard Gateway
 *
 * Class Spryng_Payments_WC_Creditcard_Gateway
 */
class Spryng_Payments_WC_Creditcard_Gateway extends Spryng_Payments_WC_Abstract_Gateway
{
    const METHOD_ID = 'spryng_payments_creditcard';
    
    /**
     * The default title to initiate settings
     *
     * @var string
     */
    const DEFAULT_TITLE = 'CreditCard';

    const DEFAULT_DESCRIPTION = 'Pay safely with your credit card.';

    /**
     * Spryng_Payments_WC_Creditcard_Gateway constructor.
     */
    public function __construct()
    {
        // Supported gateway features
        $this->supports = array(
            'products',
            'refunds'
        );
        // Initiate title and icon
        $this->id = self::METHOD_ID;
        $this->has_fields = true; // Enable <code>payment_fields</code> method

        parent::__construct();
    }

    /**
     * This method echo's HTML for the CreditCard form.
     */
    public function payment_fields()
    {
        // Since 'default_credit_card_form' is deprecated, we have to use this class
        $cc = new WC_Payment_Gateway_CC();
        $cc->id = $this->id;
        $cc->form(); // Echo's the WC default credit card form

        require(SPRYNG_DIR . '/views/public/credit-card-payment-fields.php'); // Add tokenize JS
    }

    /**
     * @param int $order_id
     * @param null $amount
     * @param string $reason
     * @return bool
     * @throws \SpryngPaymentsApiPhp\Exception\RequestException
     * @throws \SpryngPaymentsApiPhp\Exception\TransactionException
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $data = parent::process_refund($order_id, $amount, $reason);

        $order = $data['order'];
        $transaction = $data['transaction'];

        SpryngUtil::get_instance()->transaction->refund($transaction->_id, $data['amount'], $data['reason']);

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

    public function get_transaction($orderId)
    {
        if (isset($_POST[static::CC_CARD_TOKEN_POST_KEY]))
        {
            $card = $_POST[static::CC_CARD_TOKEN_POST_KEY];
        }
        else
        {
            $card = OrderUtil::get_card_token($orderId);
            if (is_null($orderId))
            {
                SpryngUtil::log(sprintf('No card provided for order %d', $orderId));
                die('No card provided.');
            }
        }

        $transaction = array(
            'payment_product'   => 'card',
            'card'              => $card
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