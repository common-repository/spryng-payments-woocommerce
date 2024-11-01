<?php

/**
 * Handles order interaction.
 *
 * Class OrderUtil
 */
class OrderUtil
{

    // Default WooCommerce order statuses
    const STATUS_WC_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_ON_HOLD    = 'on-hold';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_CANCELLED  = 'cancelled';
    const STATUS_REFUNDED   = 'refunded';
    const STATUS_WC_FAILED     = 'failed';

    // Spryng statuses
    const STATUS_SETTLEMENT_REQUESTED   = 'SETTLEMENT_REQUESTED';
    const STATUS_SETTLEMENT_COMPLETED   = 'SETTLEMENT_COMPLETED';
    const STATUS_PENDING                = 'PENDING';
    const STATUS_AUTHORIZED             = 'AUTHORIZED';
    const STATUS_SETTLEMENT_PROCESSED   = 'SETTLEMENT_PROCESSED';
    const STATUS_INITIATED              = 'INITIATED';
    const STATUS_DECLINED               = 'DECLINED';
    const STATUS_SETTLEMENT_DECLINED    = 'SETTLEMENT_DECLINED';
    const STATUS_AUTHORIZATION_VOIDED   = 'AUTHORIZATION_VOIDED';
    const STATUS_SETTLEMENT_CANCELED    = 'SETTLEMENT_CANCELED';
    const STATUS_SETTLEMENT_FAILED      = 'SETTLEMENT_FAILED';
    const STATUS_VOIDED                 = 'VOIDED';
    const STATUS_UNKNOWN                = 'UNKNOWN';
    const STATUS_FAILED                 = 'FAILED';

    public static $statuses = array(
        self::STATUS_WC_PENDING     => 'Pending Payment',
        self::STATUS_PROCESSING     => 'Processing',
        self::STATUS_ON_HOLD        => 'On Hold',
        self::STATUS_COMPLETED      => 'Completed',
        self::STATUS_CANCELLED      => 'Cancelled',
        self::STATUS_REFUNDED       => 'Refunded',
        self::STATUS_FAILED         => 'Failed'
    );

    /**
     * @var string The name of the WC API request provided to the api_request_url function.
     */
    const RETURN_URL_REQUEST = 'spryng_payments_return';

    /**
     * Find a WooCommerce order by it's ID.
     *
     * @param $orderId
     * @return null|WC_Order
     */
    public static function get_order_by_id($orderId)
    {
        if (function_exists('wc_get_order'))
            return wc_get_order($orderId);

        $order = new WC_Order();
        if ($order->get_order($orderId))
            return $order;

        return null;
    }

    /**
     * Generate a return URL for a WooCommerce order.
     *
     * @param WC_Order $order
     * @return mixed|void
     */
    public static function get_return_url_for_order(WC_Order $order)
    {
        $url = WC()->api_request_url(static::RETURN_URL_REQUEST);
        $url = add_query_arg(array(
            'order_id'  => $order->get_id(),
            'key'       => $order->get_order_key()
        ), $url);

        return strpos($url, 'https') !== false ? $url : str_replace('http', 'https', $url);
    }

    /**
     * Upon initiating a transaction, we save the ID as post meta of the order. We can find it again
     * with this function.
     *
     * @param $transactionId
     * @return bool|null|WC_Order
     */
    public static function find_order_by_transaction_id($transactionId)
    {
        global $wpdb;

        $transactionId = $wpdb->_real_escape($transactionId);

        $orderId = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT post_id FROM ' . $wpdb->prefix . 'postmeta WHERE meta_value = %s ORDER BY meta_id DESC LIMIT 1;',
                $transactionId
            ), ARRAY_N
        );

        if (count($orderId) < 1)
            return false;

        $order = self::get_order_by_id((int) $orderId[0]);

        if (!$order)
            return false;

        return $order;
    }

    /**
     * (Quietly) change the status of an order, possibly including an order note.
     *
     * @param WC_Order $order
     * @param $newStatus
     * @param string $note
     */
    public static function change_status(WC_Order $order, $newStatus, $note = '')
    {
        $order->update_status($newStatus, $note);
    }

    /**
     * Formally handle an order status change, including emptying the shopping card, reducing stock, etc.
     *
     * @param WC_Order $order
     * @param \SpryngPaymentsApiPhp\Object\Transaction $transaction
     */
    public static function handle_formal_status_change(WC_Order $order, \SpryngPaymentsApiPhp\Object\Transaction $transaction)
    {
        $successStatuses = array(
            self::STATUS_SETTLEMENT_REQUESTED,
            self::STATUS_SETTLEMENT_COMPLETED
        );

        $authorizedStatuses = array(
            self::STATUS_AUTHORIZED,
            self::STATUS_SETTLEMENT_PROCESSED
        );

        $pendingStatuses = array(
            self::STATUS_PENDING,
            self::STATUS_INITIATED
        );

        $failedStatuses = array(
            self::STATUS_DECLINED,
            self::STATUS_SETTLEMENT_DECLINED,
            self::STATUS_AUTHORIZATION_VOIDED,
            self::STATUS_SETTLEMENT_CANCELED,
            self::STATUS_SETTLEMENT_FAILED,
            self::STATUS_VOIDED,
            self::STATUS_UNKNOWN,
            self::STATUS_FAILED
        );

        $message = '';
        $status = '';
        $completed = false;
        $reduceStock = false;
        $emptyCart = false;
        switch($transaction->status)
        {
            case self::STATUS_SETTLEMENT_REQUESTED:
            case self::STATUS_SETTLEMENT_COMPLETED:
                $completed = true;
                if (!in_array(self::get_last_spryng_status_for_order($order->get_id($order->get_id())), $successStatuses)) {
                    $status = !is_null(ConfigUtil::get_global_setting_value('status_success')) ?
                        ConfigUtil::get_global_setting_value('status_success'): static::STATUS_COMPLETED;
                    $message = sprintf(
                        'Payment completed for order %s. Your transaction ID is %s.', $order->get_order_key(),
                        $transaction->_id);
                }
                $emptyCart = true;
                $reduceStock = true;
                break;
            case self::STATUS_AUTHORIZED:
            case self::STATUS_SETTLEMENT_PROCESSED:
                if (!in_array(self::get_last_spryng_status_for_order($order->get_id($order->get_id())), $authorizedStatuses)) {
                    $status = !is_null(ConfigUtil::get_global_setting_value('status_authorized')) ?
                        ConfigUtil::get_global_setting_value('status_authorized') : static::STATUS_ON_HOLD;
                    $message = sprintf('Transaction with ID %s is currently pending. Your order with ID %s should be 
                        updated automatically when the status on the payment is updated.', $transaction->_id,
                        $order->get_order_key());
                }
                $reduceStock = true;
                $emptyCart = true;
                break;
            case self::STATUS_INITIATED:
            case self::STATUS_PENDING:
                if (!in_array(self::get_last_spryng_status_for_order($order->get_id($order->get_id())), $pendingStatuses)) {
                    $status = !is_null(ConfigUtil::get_global_setting_value('status_pending')) ?
                        ConfigUtil::get_global_setting_value('status_pending'): static::STATUS_WC_PENDING;
                    if (!is_null($transaction->details->approval_url) || $transaction->details->approval_url != '')
                    {
                        $message = sprintf('Transaction with ID %s has been initiated. If you haven\'t already, you can
                    complete your payment at this URL: %s. Your order %s will be updated automatically when you\'ve
                    completed the payment.', $transaction->_id, $transaction->details->approval_url,
                            $order->get_order_key());
                    }
                    else
                    {
                        $message = sprintf('Transaction with ID %s has been initiated. Your order %s will be updated
                    automatically when it\'s status changes.', $transaction->_id, $order->get_order_key());
                    }
                }
                break;
            case self::STATUS_DECLINED:
            case self::STATUS_SETTLEMENT_DECLINED:
            case self::STATUS_AUTHORIZATION_VOIDED:
            case self::STATUS_SETTLEMENT_CANCELED:
            case self::STATUS_SETTLEMENT_FAILED:
            case self::STATUS_VOIDED:
            case self::STATUS_UNKNOWN:
            case self::STATUS_FAILED:
            default:
                if (!in_array(self::get_last_spryng_status_for_order($order->get_id($order->get_id())), $failedStatuses)) {
                    $status = !is_null(ConfigUtil::get_global_setting_value('status_failed')) ?
                        ConfigUtil::get_global_setting_value('status_failed'): static::STATUS_WC_FAILED;
                    $message = sprintf('There was a problem with your payment %s. We received the status: %s. If you think
                        this is an error, please contact us.', $transaction->_id, $transaction->status);
                }
                break;
        }

        if (ConfigUtil::get_global_setting_value('enhanced_debugging') == 'yes')
        {
            SpryngUtil::log(sprintf('Webhook event from %s: New status: \'%s\', new status for order %d: \'%s\', order message: 
            \'%s\', payment completed: %d, empty card: %d, reduce stock: %d',
                $_SERVER['REMOTE_ADDR'],
                $transaction->status,
                $order->get_id(),
                $status,
                $message,
                (string) $completed,
                (string) $emptyCart,
                (string) $reduceStock));
        }

        if ($status != '' && $order->get_status() !== $transaction->status)
        {
            self::change_status($order, $status);
        }

        if ($message !== '')
        {
            $order->add_order_note(__($message, Spryng_Payments_WC_Plugin::PLUGIN_ID));
        }

        if ($completed)
        {
            $order->payment_complete();
        }

        if ($reduceStock == true)
        {
            // Check if the stock has already been reduced
            if (!self::stock_has_been_reduced($order->get_id()))
            {
                // If not, reduce the stock and persist that this has been done
                wc_reduce_stock_levels($order->get_id());
                self::set_stock_status($order->get_id(), true);
            }
        }
    }

    /**
     * Find out what payment gateway was used for an order.
     *
     * @param WC_Order $order
     * @return bool|mixed|WC_Payment_Gateway
     */
    public static function get_wc_payment_gateway_for_order(WC_Order $order)
    {
        if (function_exists('wc_get_payment_gateway_by_order'))
            return wc_get_payment_gateway_by_order($order);

        if (WC()->payment_gateways())
        {
            $gateways = WC()->payment_gateways()->payment_gateways();
        }
        else
        {
            $gateways = array();
        }

        return isset($gateways[$order->get_payment_method()]) ? $gateways[$order->get_payment_method()] : false;
    }

    /**
     * Get the ID of a transaction associated with an order, which was saved as post meta.
     *
     * @param $orderId
     * @return mixed
     */
    public static function get_transaction_id_from_order($orderId)
    {
        return get_post_meta($orderId, '_spryng_payments_transaction_id', $single = true);
    }

    /**
     * Sets the webhook key for an order
     *i
     * @param $orderId
     */
    public static function set_webhook_key($orderId)
    {
        add_post_meta($orderId, '_spryng_payments_webhook_key', self::key_gen(100));
    }

    /**
     * Sets the card token for an order
     *i
     * @param $orderId
     */
    public static function set_card_token($orderId, $card)
    {
        add_post_meta($orderId, '_spryng_payments_card_token', $card);
    }

    /**
     * Sets the order key for an order
     *
     * @param $orderId
     */
    public static function set_order_key($orderId, $key)
    {
        add_post_meta($orderId, '_spryng_payments_order_key', $key);
    }

    /**
     * Gets an order ID by it's key
     *
     * @param $key
     * @return int
     */
    public static function get_order_id_for_order_key($key)
    {
        global $wpdb;
        $key = $wpdb->_real_escape($key);
        $res = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT post_id FROM wp_postmeta WHERE meta_key = "_spryng_payments_order_key" AND meta_value = "%s";',
                $key
            ), ARRAY_N
        );

        if (count($res) > 0)
        {
            $postID = $res[0];
            return intval($postID);
        }

        return null;
    }

    /**
     * Get the card token for an order (ID)
     *
     * @param $orderId
     * @return mixed
     */
    public static function get_card_token($orderId)
    {
        return get_post_meta($orderId, '_spryng_payments_card_token', $single = true);
    }

    /**
     * Get the webhook key for an order (ID)
     *
     * @param $orderId
     * @return mixed
     */
    public static function get_webhook_key($orderId)
    {
        return get_post_meta($orderId, '_spryng_payments_webhook_key', $single = true);
    }


    /**
     * Adds postmeta to an order to indicate if the stock has been reduced for this order yet.
     *
     * @param int $orderId
     * @param bool $status
     */
    public static function set_stock_status($orderId, $status)
    {
        add_post_meta($orderId, '_spryng_reduced_stock', $status);
    }

    /**
     * Fetches the post meta indicating if the stock has been reduced for an order.
     *
     * @param $orderId
     * @return bool
     */
    public static function stock_has_been_reduced($orderId)
    {
        $status = get_post_meta($orderId, '_spryng_reduced_stock', true);
        if (is_null($status))
            return false;

        return (bool) $status;
    }

    /**
     * Add metadata to an order, like transaction ID, gateway and last known status.
     *
     * @param $orderId
     * @param $gatewayName
     * @param \SpryngPaymentsApiPhp\Object\Transaction $transaction
     */
    public static function set_metadata($orderId, $gatewayName, \SpryngPaymentsApiPhp\Object\Transaction $transaction)
    {
        if (get_post_meta($orderId, '_spryng_payments_transaction_id', true) == '') {
            add_post_meta($orderId, '_spryng_payments_transaction_id', $transaction->_id, true);
        } else {
            update_post_meta($orderId, '_spryng_payments_transaction_id', $transaction->_id);
        }
        if (get_post_meta($orderId, '_spryng_payments_gateway', true) == '') {
            add_post_meta($orderId, '_spryng_payments_gateway', $gatewayName, true);
        } else {
            update_post_meta($orderId, '_spryng_payments_gateway', $gatewayName, true);
        }
        if (get_post_meta('_spryng_payments_latest_status', true) == '') {
            add_post_meta($orderId, '_spryng_payments_latest_status', $transaction->status);
        } else {
            update_post_meta($orderId, '_spryng_payments_latest_status', $transaction->status);
        }

        if (!is_null($transaction->customer->_id) && $transaction->customer->_id != '')
        {
            if (get_post_meta('_spryng_payments_customer_id', true) == '') {
                add_post_meta($orderId, '_spryng_payments_customer_id', $transaction->customer->_id);
            } else {
                update_post_meta($orderId, '_spryng_payments_customer_id', $transaction->customer->_id);
            }
        }
    }

    /**
     * Get the status that the webhook reported last for an order with $orderId
     *
     * @param $orderId
     * @return mixed
     */
    public static function get_last_spryng_status_for_order($orderId)
    {
        return get_post_meta($orderId, '_spryng_payments_latest_status', true);
    }

    /**
     * Delete the customer ID on an order.
     *
     * @param $orderId
     */
    public static function delete_metadata($orderId)
    {
        delete_post_meta($orderId, '_spryng_payments_customer_id');
    }

    /**
     * Generates an array that can be used as options for the status selector in the general settings.
     *
     * @return array
     */
    public static function get_order_status_selector_options()
    {
        $statuses = array();

        foreach (self::$statuses as $key => $status)
        {
            $statuses[$key] = __($status, Spryng_Payments_WC_Plugin::PLUGIN_ID);
        }

        return $statuses;
    }

    /**
     * Format a monetary amount that was provided by WC to a format that the platform supports.
     *
     * @param $amount
     * @return float|int|mixed
     */
    public static function format_wc_amount($amount)
    {
        // Price can be i.e. €40 without decimals
        if (strpos($amount, wc_get_price_decimal_separator()) !== false)
        {
            // Check if price is less than 1
            if (substr($amount, 0, 2) === '0' . wc_get_price_decimal_separator())
            {
                // 0.20 becomes 20.0
                $amount = (float) $amount * 100;
                // 20.0 becomes 20, which is the format we want
                $amount = (int) $amount;
            }
            else
            {
                // Make sure the seperator is '.' so we can cast to float
                $amount = str_replace(wc_get_price_decimal_separator(), '.', $amount);
                // Round to 2 digits
                $amount = round((float) $amount, 2);
                // Get int value of the amount
                $amount = (int) ($amount * 100);
            }
        }
        else
        {
            // Price is i.e. exactly €40, becomes 4000 as int
            $amount = (int) (floatval($amount) * 100);
        }

        return $amount;
    }

    /**
     * Generates a random string which can be used as a key to validate the webhook origin
     *
     * @param $length
     * @return bool
     */
    private static function key_gen($length)
    {
        global $wpdb;
        $unique = false;
        $rand   = self::get_random_str($length);

        while (!$unique)
        {
            $rand = $wpdb->_real_escape($rand);
            $check = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT COUNT(meta_value) FROM wp_postmeta WHERE meta_value = %s;',
                    $rand
                ), ARRAY_N
            );

            if ((int) $check[0] === 0)
                $unique = true;
        }

        return $rand;
    }

    private static function get_random_str($length)
    {
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890";
        $str = '';
        for ($i = 0; $i < $length; $i++)
        {
            $str .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $str;
    }

    /**
     * Check if an order is a subscription renewal
     *
     * @param $order
     * @return bool
     */
    public static function is_subscription_payment($order)
    {
        if (!$order instanceof WC_Abstract_Order && is_int($order))
        {
            $order = self::get_order_by_id($order);
        }

        $meta = $order->get_meta('_subscription_renewal');
        if ($meta === '' || is_null($meta))
            return false;

        return true;
    }
}