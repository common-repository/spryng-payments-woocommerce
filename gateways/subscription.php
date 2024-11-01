<?php

abstract class Spryng_Payments_WC_Abstract_Subscription_Gateway extends Spryng_Payments_WC_Abstract_Gateway
{

    const MANDATE_STATUS_ACTIVE = "ACTIVE";

    public function __construct()
    {
        parent::__construct();

        $this->supports = array_merge($this->supports, array(
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes'
        ));

        if (class_exists('WC_Subscriptions_Order'))
        {
            add_action('woocommerce_scheduled_subscription_payment_' . $this->id,
                array($this, 'scheduled_subscription_payment'), 10, 2);
            add_action('wcs_resubscribe_order_created', array($this, 'delete_order_meta'), 10);
            add_action('wcs_renewal_order_created', array($this, 'delete_customer_id'), 10);
            add_action('woocommerce_subscription_failing_payment_method_updated_' . $this->id, array($this,
                'update_failing_payment_method'), 10, 2);

            add_filter('woocommerce_subscription_payment_meta', array($this, 'add_subscription_payment_data'), 10, 2);
        }
    }

    /**
     * Process a scheduled subscription payment.
     *
     * @param integer $chargeAmount
     * @param WC_Order|WC_Subscription $order
     * @return array|bool
     */
    public function scheduled_subscription_payment($chargeAmount, $order)
    {
        SpryngUtil::log(sprintf("The hook was called for order %d", $order->get_id()));
        if (!$order)
        {
            SpryngUtil::log(__('Could not process scheduled subscription payment. Order invalid.'),
                Spryng_Payments_WC_Plugin::PLUGIN_ID);

            return array('result' => 'failure');
        }

        SpryngUtil::log(sprintf('Starting scheduled subscription payment for order %d.', $order->get_id()));

        if (!$this->validate_subscription_payment($order->get_id()))
        {
            SpryngUtil::log('Could not process payment for order %d.', $order->get_id());
            OrderUtil::change_status($order, OrderUtil::STATUS_FAILED, sprintf('Unfortunately, we could not automatically
                process the payment for your subscription. Please contact our support team. (Order ID: %d)', $order->get_id()));
            return false;
        }

        $paymentResult = $this->process_payment($order->get_id(), $chargeAmount, $this->get_account_id(true));

        if (isset($paymentResult['result']) && $paymentResult['result'] === 'success')
        {
            return array('result' => 'success');
        }

        return array('result' => 'failure');
    }

    /**
     * Hook function to delete customer data.
     *
     * @param WC_Order|WC_Subscription $resubOrder
     * @return WC_Order|WC_Subscription
     */
    public function delete_customer_id($resubOrder)
    {
        delete_post_meta($resubOrder->get_id(), '_spryng_payments_customer_id');
        return $resubOrder;
    }

    /**
     * Deletes the customer ID off a subscription that is to be deleted.
     *
     * @param WC_Order|WC_Subscription $renewalOrder
     * @return WC_Order|WC_Subscription
     */
    public function delete_order_meta($renewalOrder)
    {
        delete_post_meta($renewalOrder->get_id(), '_spryng_payments_customer_id');
        delete_post_meta($renewalOrder->get_id(), '_spryng_payments_gateway');
        delete_post_meta($renewalOrder->get_id(), '_spryng_payments_transaction_id');
        delete_post_meta($renewalOrder->get_id(), '_spryng_payments_latest_status');

        return $renewalOrder;
    }

    /**
     * Add a customer ID to payment data when the payment method is selected manually in the admin area.
     *
     * @param array $meta
     * @param WC_Subscription $sub
     * @return array
     */
    public function add_subscription_payment_data($meta, $sub)
    {
        $customerId = get_post_meta($sub->get_id(), '_spryng_payments_customer_id', true);
        if (!is_null($customerId) && $customerId != '')
        {
            try
            {
                $customer = SpryngUtil::get_instance()->customer->getCustomerById($customerId);
            }
            catch (\SpryngPaymentsApiPhp\Exception\RequestException $ex)
            {
                return $meta;
            }
            catch (\SpryngPaymentsApiPhp\Exception\CustomerException $ex)
            {
                return $meta;
            }

            $meta[$this->id] = array(
                'post_meta' => array(
                    '_spryng_payments_customer_id' => array(
                        'value' => $customer->_id,
                        'label' => 'Spryng Customer ID'
                    ),
                    '_spryng_payments_gateway' => array(
                        'value' => $this->id,
                        'label' => 'Spryng Gateway'
                    )
                )
            );
        }

        return $meta;
    }

    /**
     * @param WC_Subscription $subscription
     * @param WC_Order $renewalOrder
     */
    public function update_failing_payment_method($subscription, $renewalOrder)
    {
        update_post_meta($subscription->get_id(), '_spryng_payments_customer_id', get_post_meta($renewalOrder->get_id(),
            '_spryng_payments_customer_id', true));
    }

    abstract public function get_mandate_type();

    abstract public function get_mandate_processor();

    abstract public function validate_subscription_payment($orderId);

    /**
     * In some cases, different account ID's might be necessary for different kinds of payments.
     *
     * @return mixed
     */
    abstract public function get_account_id($isSubscriptionPayment);
}