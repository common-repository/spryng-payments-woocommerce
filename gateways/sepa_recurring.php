<?php

/**
 * Class Spryng_Payments_WC_SEPA_Recurring_Gateway
 */
class Spryng_Payments_WC_SEPA_Recurring_Gateway extends Spryng_Payments_WC_Abstract_Subscription_Gateway
{
    const METHOD_ID = 'spryng_payments_sepa_recurring';

    const DEFAULT_TITLE = "SEPA Recurring";

    const DEFAULT_DESCRIPTION = 'Let us automatically charge your bank account using SEPA Direct Debit.';

    const MANDATE_PROCESSOR = "slimpay";

    const MANDATE_TYPE = "sepa";

    const MANDATE_STATUS_ACTIVE = "ACTIVE";

    public function __construct()
    {
        $this->id = self::METHOD_ID;
        $this->has_fields = true;
        $this->icon = false;

        parent::__construct();
        $this->supports = array_merge($this->supports, array('refunds'));
        $this->add_additional_settings();
    }

    public function is_available()
    {
        if (is_null($this->get_option('subscription_account')) || $this->get_option('subscription_account') === '')
            return false;

        if (is_null($this->get_option('payment_product')) || $this->get_option('payment_product') === '')
            return false;

        return true;
    }

    public function get_transaction($orderId)
    {
        $returnUrl = OrderUtil::get_return_url_for_order(OrderUtil::get_order_by_id($orderId));
        $order = OrderUtil::get_order_by_id($orderId);

        if (OrderUtil::is_subscription_payment($order))
        {
            $paymentProduct = 'sepa';
        }
        else
        {
            $paymentProduct = $this->get_option('payment_product');
        }

        $transaction = array(
            'payment_product' => $paymentProduct,
            'details' => array(
                'redirect_url' => $returnUrl,
                'issuer' => 'INGBNL2A'
            )
        );

        return array_merge($this->get_default_transaction_parameters($orderId), $transaction);
    }

    public function get_mandate_type()
    {
        return static::MANDATE_TYPE;
    }

    public function get_mandate_processor()
    {
        return static::MANDATE_PROCESSOR;
    }

    public function validate_subscription_payment($orderId)
    {
        $transaction = $this->get_default_transaction_parameters($orderId);
        if (is_null($transaction['customer']))
        {
            SpryngUtil::log(__(sprintf('Subscription payment validation failed. No customer ID was saved. Order
                ID: %d.', $orderId), Spryng_Payments_WC_Plugin::PLUGIN_ID));
            return false;
        }

        $customer = SpryngUtil::get_instance()->customer->getCustomerById($transaction['customer']);
        if (!($customer instanceof \SpryngPaymentsApiPhp\Object\Customer))
        {
            SpryngUtil::log(__(sprintf('Customer %s is not valid. Could not fetch.', $transaction['customer']),
                Spryng_Payments_WC_Plugin::PLUGIN_ID));
            return false;
        }

        $hasMandate = false;
        if (count($customer->mandates) > 0)
        {
            foreach($customer->mandates as $key => $mandate)
            {
                if ($mandate->_type != static::MANDATE_TYPE || $mandate->processor != static::MANDATE_PROCESSOR)
                {
                    continue;
                }

                if ($mandate->status === static::MANDATE_STATUS_ACTIVE)
                {
                    $hasMandate = true;
                    break;
                }
            }
        }

        if (!$hasMandate)
        {
            SpryngUtil::log(__('Subscription payment validation failed. Customer %s does not have a valid mandate. Order
                ID: %d.', $customer->_id, $orderId), Spryng_Payments_WC_Plugin::PLUGIN_ID);
            return false;
        }

        return true;
    }

    public function init_form_fields()
    {
        $settings = parent::init_form_fields();

        // Change title and description of the accounts setting.
        $settings['account']['title'] = 'Checkout/mandate signature account';
        $settings['account']['description'] = __(
            'This account will be used during the checkout process, to handle the mandate signature by the customer.',
            Spryng_Payments_WC_Plugin::PLUGIN_ID
        );

        // Add setting for the payment product for the checkout account
        $settings['payment_product'] = array(
            'title' => 'Checkout account payment product',
            'type' => 'select',
            'options' => array(
                'ideal' => 'iDEAL',
                'sepa' => 'SEPA',
                'creditcard' => 'CreditCard'
            )
        );

        // Add setting for the recurring account. This is initially a copy of the regular account
        $settings['subscription_account'] = $settings['account'];
        // Modify the title, description and value
        $settings['subscription_account']['title'] = 'Account for processing subscription payments.';
        $settings['subscription_account']['description'] = __(
            'This account will be used when processing subscriptions.', Spryng_Payments_WC_Plugin::PLUGIN_ID
        );

        return $settings;
    }

    /*
     * In the current setup, different accounts may need to be used in order to get SlimPay to work properly
     * with iDEAL mandate signatures. Therefore, this function will overwrite the regular configuration fields for
     * the gateway, with an additional account setting that is used for subscription payments.
     */
    public function add_additional_settings()
    {
        // Overwrite the title and description of the regular account field for clarity
        $this->form_fields['account']['title'] = 'Checkout/mandate signature account';
        $this->form_fields['account']['description'] = __(
            'This account will be used during the checkout process, to handle the mandate signature by the customer.',
            Spryng_Payments_WC_Plugin::PLUGIN_ID
        );
        $this->form_fields['payment_product'] = array(
            'title' => 'Checkout account payment product',
            'type' => 'select',
            'options' => array(
                'ideal' => 'iDEAL',
                'sepa' => 'SEPA',
                'creditcard' => 'CreditCard'
            )
        );

        // Copy the regular account field so that accounts don't need to be fetched from the API twice
        $this->form_fields['subscription_account'] = $this->form_fields['account'];
        // Modify the title, description and value
        $this->form_fields['subscription_account']['title'] = 'Account for processing subscription payments.';
        $this->form_fields['subscription_account']['description'] = __(
            'This account will be used when processing subscriptions.', Spryng_Payments_WC_Plugin::PLUGIN_ID
        );
    }

    /**
     * In some cases, different account ID's might be necessary for different kinds of payments.
     *
     * @return mixed
     */
    public function get_account_id($isSubscriptionPayment)
    {
        return ($isSubscriptionPayment) ? $this->get_option('subscription_account') :
            $this->get_option('account');
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