<?php

/**
 * Abstract class containing global logic for payment gateways
 *
 * Class Spryng_Payments_WC_Abstract_Gateway
 */
abstract class Spryng_Payments_WC_Abstract_Gateway extends WC_Payment_Gateway
{
    /**
     * Name of the plugin folder
     *
     * @var string
     */
    const PLUGIN_FOLDER_NAME = "spryng-payments-woocommerce";

    const CC_CARD_TOKEN_POST_KEY = 'spryng_payments_wc_creditcard_gateway-card-token';

    const WEBHOOK_BASE_URI = '/?wc-api=spryng_payments_wc_webhook';

    const THREED_AUTHENTICATE_URI = '/?wc-api=spryng_payments_threed_authenticate';

    const THREED_RETURN_URL = '/?wc-api=spryng_payments_threed_authenticate_return';

    const REFRESH_BASE_URI = '/?wc-api=spryng_payments_refresh_org_acc_transient';

    /**
     * @var bool
     */
    protected $showIcon;

    /**
     * Spryng_Payments_WC_Abstract_Gateway constructor.
     */
    public function __construct()
    {
        // Set by gateways individually
        $this->plugin_id = '';

        $this->id = strtolower(get_class($this));

        // Set the title of the gateway in the settings pane
        $this->method_title = 'Spryng Payments - ' .$this->get_default_gateway_title();
        // Set default description for the settings pane
        $this->method_description = $this->get_method_description();

        // Load configuration
        $this->init_form_fields();
        $this->init_settings();

        if (!has_action('woocommerce_thanksyou_'.$this->id))
        {
            add_action('woocommerce_thankyou_'.$this->id, array($this,'thankyou_page'));
        }

        // Load the title of the gateway for the checkout page
        $this->title = $this->get_option('title', $this->get_default_gateway_title());
        // Load the description of the gateway for the checkout page
        $this->description = $this->get_option('description', $this->get_default_gateway_description());
        // Check if icon is enabled
        $this->showIcon = $this->get_option('show_icon') == 'yes';
        $this->init_icon();

        // Hook into the webhook action
        add_action('woocommerce_api_' . $this->id, array($this, 'webhookAction'));

        add_filter('woocommerce_thankyou_order_received_text', array($this, 'order_received_text'), 10, 2);

        // If the user is an administrator, add the settings tab
        if (is_admin())
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Define the setting fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'type' => 'checkbox',
                'label' => sprintf(__('Enable %s', Spryng_Payments_WC_Plugin::PLUGIN_ID), $this->title),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'type' => 'text',
                'description' => sprintf(__('The title of the payment gateway which the customer sees at checkout. 
                                            Default: <code>%s</code>.', Spryng_Payments_WC_Plugin::PLUGIN_ID),
                    $this->title),
                'default' => $this->get_default_gateway_title(),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'type' => 'textarea',
                'description' => sprintf(__('This controls the description of the payment method which the customer 
                                            sees at checkout. Default: <code>%s</code>',
                    Spryng_Payments_WC_Plugin::PLUGIN_ID), $this->description),
                'default' => $this->get_default_gateway_description(),
                'desc_tip' => true
            ),
            'show_icon' => array(
                'title' => __('Display icon on payment page', Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'type' => 'checkbox',
                'label' => __('Enable icon', Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'default' => 'yes'
            ),
            'organisation' => array(
                'title' => __('Organisation', Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'type' => 'select',
                'description' => __('Select the organisation for which you\'d like to process payments',
                    Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'desc_tip' => true,
                'default' => '',
                'options' => $this->get_organisation_options()
            ),
            'account' => array(
                'title' => __('Account', Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'type' => 'select',
                'description' => __('With this setting, you can select the account with the proper configurations for this gateway.'
                    , Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'desc_tip' => true,
                'default' => '',
                'options' => $this->get_account_options()
            ),
            'refresh_data' => array(
                'title' => __('Refresh accounts and organisations', Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'type' => 'button',
                'description' => __('This button will use the API to refresh the available accounts and organisations.',
                    Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'desc_tip' => true,
                'custom_attributes' => array(
                    'onclick' => 'location.href="' . self::REFRESH_BASE_URI .'"'
                )
            )
        );
    }

    protected function init_icon()
    {
        if ($this->showIcon)
        {
            $this->icon = apply_filters($this->id . '_icon_url',
                Spryng_Payments_WC_Plugin::get_public_url('assets/images/' . $this->id . '.png'));
        }
    }

    /**
     * Check if the gateway is available
     *
     * @return bool
     */
    public function is_available()
    {
        // Return false if the parent is not available
        if (!parent::is_available())
            return false;

        /*
         * Checking conditions for availability separately instead of in 1 if statement to improve performance on the
         * checkout page.
         */
        if (is_null(ConfigUtil::get_api_key()) || ConfigUtil::get_api_key() === '')
            return false;


        if (is_null($this->get_option('account')) || $this->get_option('account') === '')
            return false;


        if (is_null($this->get_option('organisation')) || $this->get_option('organisation') === '')
            return false;

        return true;
    }

    /**
     * Initialize the organisation selector in the plugin configuration.
     */
    protected function get_organisation_options()
    {
        $orgs = get_transient(Spryng_Payments_WC_Plugin::PLUGIN_ID . '_organisations');
        if (!$orgs)
            return array();

        $orgOptions = array();
        foreach ($orgs as $org) {
            $orgOptions[$org->_id] = __($org->name, Spryng_Payments_WC_Plugin::PLUGIN_ID);
        }

        return $orgOptions;
    }

    /**
     * Initialize account selection fields with API data
     */
    protected function get_account_options()
    {
        $accs = get_transient(Spryng_Payments_WC_Plugin::PLUGIN_ID . '_accounts');
        if (!$accs)
            return array();

        $accOptions = array();
        foreach ($accs as $acc) {
            $accOptions[$acc->_id] = __($acc->name, Spryng_Payments_WC_Plugin::PLUGIN_ID);
        }

        return $accOptions;
    }

    /**
     * Process a refund request
     *
     * @param int $order_id
     * @param null $amount
     * @param string $reason
     * @return bool|WP_Error
     * @throws \SpryngPaymentsApiPhp\Exception\TransactionException
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        // Parse amount
        if (strpos($amount,'.') !== false)
        {
            $amount = (int) str_replace('.','',$amount);
        }
        else
        {
            $amount = (int) $amount * 100;
        }

        // Fetch the order
        $order = wc_get_order($order_id);
        // Fetch the transaction ID for the order
        $transactionId = OrderUtil::get_transaction_id_from_order($order->get_id());

        // Fetch the transaction from the API
        $transaction = SpryngUtil::get_instance()->transaction->getTransactionById($transactionId);

        // Can't refund a settled transaction
        if ($transaction->status != 'SETTLEMENT_COMPLETED')
        {
            return new WP_Error(1,"This transaction does not have the status 'SETTLEMENT_COMPLETED'.");
        }

        return array(
            'order' => $order,
            'transaction' => $transaction,
            'amount' => $amount,
            'reason' => $reason
        );
    }

    /**
     * @param  int $orderId
     * @return array
     * @throws \libphonenumber\NumberParseException
     */
    public function get_default_transaction_parameters($orderId)
    {
        $order = OrderUtil::get_order_by_id($orderId); // Find Order instance by ID
        $amount = OrderUtil::format_wc_amount($order->get_total());
        $account = $this->get_option('account');
        $organisation = $this->get_option('organisation');
        OrderUtil::set_webhook_key($order->get_id());
        $webhookUrl = site_url() . self::WEBHOOK_BASE_URI . '&key=' . OrderUtil::get_webhook_key($order->get_id());
        $webhookUrl = strpos($webhookUrl, 'https') !== false ? $webhookUrl : str_replace('http', 'https', $webhookUrl);

        $transaction = array(
            'account'               => $this->get_option('account'),
            'amount'                => $amount,
            'dynamic_descriptor'    => str_replace('_','',$order->get_order_key()),
            'user_agent'            => $_SERVER['HTTP_USER_AGENT'],
            'customer_ip'           => $_SERVER['REMOTE_ADDR'],
            'capture_now'           => (ConfigUtil::get_global_setting_value('capture') === 'yes' ) ? true : false,
            'merchant_reference'    => str_replace('{id}', $order->get_id(), ConfigUtil::get_global_setting_value('merchant_reference')),
            'webhook_transaction_update' => $webhookUrl
        );

        // Check if customers are enabled and if so, fetch a customer and add it to the transaction
        if (ConfigUtil::get_global_setting_value('customers_disabled') !== 'yes')
        {
            // If 3D Secure is enabled, save the POST'ed customer data with the order
            if (ThreeDUtil::enabled())
            {
                $customer = CustomerUtil::get_spryng_customer_id($account, $organisation, $order, $_POST, true);
            }
            else
            {
                $customer = CustomerUtil::get_spryng_customer_id($account, $organisation, $order, $_POST, false);
            }
            if (is_null($customer))
            {
                SpryngUtil::log(sprintf('Could not create customer. User data: %s', json_encode($_POST)));
                return array(
                    'result' => 'failure',
                    'messages' => array(
                        'Could not create customer. Did you set your shipping details?'
                    )
                );
            }
            else
            {
                $transaction = array_merge($transaction, array('customer' => $customer->_id));
            }
        }

        return $transaction;
    }


    /**
     * Processes a payment via the Spryng Payments gateway.
     *
     * The function will process the order with ID $orderId. The gateway that'll be chosen
     * is saved as order meta, or is provided through a POST from the checkout page. Using
     * the arguments to this function, the process can be altered. The amount to be charged
     * and the account used can be overwritten by providing them in $chargeAmount and $account.
     *
     * For card transactions, the credit card is fetched using the provided token. If the CVV
     * of the card is not verified, the user will go through the 3D Secure authentication
     * process. To indicate that the user has completed this process, you can provide
     * $eci, $cavv and $pares, which will have been provided from the 3D Secure endpoints. Also
     * set $directRedirect to true to redirect the user directly, instead of replying to
     * the default WooCommerce checkout page.
     *
     * @param int $orderId
     * @param null $chargeAmount
     * @param null $account
     * @param null $eci
     * @param null $cavv
     * @param null $pares
     * @param bool $directRedirect
     * @return array|bool|\SpryngPaymentsApiPhp\Object\Transaction
     */
    public function process_payment($orderId, $chargeAmount = null, $account = null, $eci = null, $cavv = null,
                                    $pares = null, $directRedirect = false)
    {
        $transaction = $this->get_transaction($orderId);

        if (is_wp_error($transaction))
        {
            SpryngUtil::log($transaction->get_error_message($transaction->get_error_code()));
            OrderUtil::change_status(OrderUtil::get_order_by_id($orderId), OrderUtil::STATUS_FAILED, 'The payment for your
             order could not be processed. Please contact us for assistance.');
            return false;
        }

        $order = OrderUtil::get_order_by_id($orderId);

        // Check if the transaction could not be initialised. I.e. the user provided an invalid
        // country for sofort.
        if (isset($transaction['result']) && $transaction['result'] === 'failure')
        {
            // In this case the get_transaction function returns an array in the correct format for WC
            // so we can return it as-is.
            return $transaction;
        }

        if (!is_null($chargeAmount))
        {
            $transaction['amount'] = OrderUtil::format_wc_amount($chargeAmount);
        }

        // If an account ID is given, overwrite it. This might be a subcription payment.
        if (!is_null($account))
        {
            $transaction['account'] = $account;
        }

        if (!is_null($eci) && !is_null($cavv) && !is_null($pares))
        {
            $transaction['eci']= $eci;
            $transaction['cavv2'] = $cavv;
            $transaction['pares'] = $pares;
        }

        // Check if the card needs to go through the 3D Secure process. If $pares is set, the 3D process
        // has already been completed.
        if ($transaction['payment_product'] == 'card' &&
            isset($transaction['card']) &&
            !isset($pares) &&
            !isset($eci) &&
            !isset($cavv)
        )
        {
            // Fetch the card from the Spryng API using the ThreedUtil
            $card = ThreeDUtil::get_card($transaction['card']);
            if (is_null($card))
            {
                // If the card can not be fetched, checkout fails
                return array(
                    'result' => 'failure',
                    'messages' => array(
                        'Could not validate your card for 3D secure. Please try again.'
                    )
                );
            }

            // If 3D Secure is enabled, go through the 3D process.
            if (ThreeDUtil::enabled())
            {
                return $this->initiate_threed_secure($transaction, $orderId);
            }
        }

        // If the customer already started a transaction for this order, we should be able to find it in the database
        // This way we can make sure that no two payments exist for one order.
        $transactionId = OrderUtil::get_transaction_id_from_order($orderId);
        if (is_null($transactionId) || $transactionId === '')
        {
            $transaction = $this->initiate_payment_for_payment_product($transaction);
            if (is_null($transaction))
            {
                return array(
                    'result' => 'failure',
                    'messages' => array(
                        'Transaction was declined. Please contact the administrator of this website.'
                    )
                );
            }
        }
        else
        {
            // The transaction ID is not null so we'll attempt to fetch the transaction information from the API
            try
            {
                $apiTransaction = SpryngUtil::get_instance()->transaction->getTransactionById($transactionId);
                if ($this->should_resume_existing_transaction($apiTransaction, $transaction))
                {
                    $transaction = $apiTransaction;
                }
                else
                {
                    $transaction = $this->initiate_payment_for_payment_product($transaction);
                }
            }
            catch (\SpryngPaymentsApiPhp\Exception\TransactionException $ex)
            {
                SpryngUtil::log(sprintf('Transaction \'%s\' does not seem to exist.', $transactionId));
                $transaction = $this->initiate_payment_for_payment_product($transaction);
                if (is_null($transaction))
                {
                    return array(
                        'result' => 'failure',
                        'messages' => array(
                            'Transaction was declined. Please contact the administrator of this website.'
                        )
                    );
                }
            }
        }

        OrderUtil::handle_formal_status_change($order, $transaction);
        OrderUtil::set_metadata($orderId, __CLASS__, $transaction);
        $redirect = (isset($transaction->details->approval_url)) ? $transaction->details->approval_url :
            OrderUtil::get_return_url_for_order($order);
        if ($directRedirect)
        {
            wp_safe_redirect($redirect);
            die;
        }
        else
        {
            return array(
                'result' => 'success',
                'redirect' => $redirect
            );
        }
    }

    public function should_resume_existing_transaction($existingTransaction, $currentTransaction)
    {
        // Start a new transaction if the user wants to use a different payment product
        if ($existingTransaction->payment_product != $currentTransaction['payment_product'])
        {
            return false;
        }

        $createdAtDateTime = DateTime::createFromFormat('Y-m-d\TH:i:s.???\Z', $existingTransaction->created_at);
        $now = new DateTime(date('Y-m-d H:i:s'));
        // If the previous transaction was on a different day, create a new one
        if ($createdAtDateTime->format('Ymd') !== $now->format('Ymd'))
        {
            return false;
        }
        // If the previous transaction was less than 10 minutes ago and the status is 'INITIATED', use it
        $diff = $createdAtDateTime->diff($now);

        if (intval($diff->format('%i')) >= 7)
        {
            return false;
        }
        // If we're going to pick up a iDEAL transaction but the issuer is different, start a new transaction.
        if ($existingTransaction->payment_product == 'ideal' && $existingTransaction->details->issuer !== $currentTransaction['details']['issuer'])
        {
            return false;
        }
        if ($existingTransaction->status != 'INITIATED')
        {
            return false;
        }

        return true;
    }

    public function initiate_payment_for_payment_product($transaction)
    {
        try
        {
            if ($transaction instanceof \SpryngPaymentsApiPhp\Object\Transaction)
            {
                $transaction = json_decode(json_encode($transaction), true);
            }
            // Call correct initiate function for the payment product
            switch($transaction['payment_product'])
            {
                case 'card':
                default:
                    $transaction = SpryngUtil::get_instance()->transaction->create($transaction);
                    break;
                case 'ideal':
                    $transaction = SpryngUtil::get_instance()->iDeal->initiate($transaction);
                    break;
                case 'paypal':
                    $transaction = SpryngUtil::get_instance()->Paypal->initiate($transaction);
                    break;
                case 'klarna':
                    $transaction = SpryngUtil::get_instance()->Klarna->initiate($transaction);
                    break;
                case 'sofort':
                    $transaction = SpryngUtil::get_instance()->SOFORT->initiate($transaction);
                    break;
                case 'sepa':
                    $transaction = SpryngUtil::get_instance()->Sepa->initiate($transaction);
                    break;
                case 'bancontact':
                    $transaction = SpryngUtil::get_instance()->Bancontact->initiate($transaction);
                    break;
            }
        }
        catch (\SpryngPaymentsApiPhp\Exception\RequestException $exception)
        {
            SpryngUtil::log(sprintf('RequestException was thrown while trying to initiate transaction. Transaction JSON:
            %s Exception message: %s', json_encode($transaction), $exception->getMessage()));
            return null;
        }

        return $transaction;
    }

    public function get_method_title()
    {
        $title = $this->get_option('title');

        if (is_null($title) || $title === '')
        {
            return __($this->get_default_gateway_title(), Spryng_Payments_WC_Plugin::PLUGIN_ID);
        }

        return $title;
    }

    public function get_method_description()
    {
        $description = $this->get_option('description');

        if (is_null($description) || $description === '')
        {
            return __($this->get_default_gateway_description(), Spryng_Payments_WC_Plugin::PLUGIN_ID);
        }

        return $description;
    }

    /**
     * Returns the gateway's default title
     *
     * @return string
     */
    abstract public function get_default_gateway_title();

    /**
     * Returns the gateways default description
     *
     * @return string
     */
    abstract public function get_default_gateway_description();

    /**
     * Get a formatted transaction specific to the payment gateway.
     *
     * @return array
     */
    abstract public function get_transaction($orderId);

    /**
     * Generate Button HTML.
     *
     * @access public
     * @param mixed $key
     * @param mixed $data
     * @since 1.0.0
     * @return string
     */
    public function generate_button_html( $key, $data ) {
        $field    = $this->plugin_id . $this->id . '_' . $key;
        $defaults = array(
            'class'             => 'button-secondary',
            'css'               => '',
            'custom_attributes' => array(),
            'desc_tip'          => false,
            'description'       => '',
            'title'             => '',
        );

        $data = wp_parse_args( $data, $defaults );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
                <?php echo $this->get_tooltip_html( $data ); ?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                    <button class="<?php echo esc_attr( $data['class'] ); ?>" type="button" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>><?php echo wp_kses_post( $data['title'] ); ?></button>
                    <?php echo $this->get_description_html( $data ); ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Will launch the 3D Secure process
     *
     * @param $transaction
     * @param $orderId
     * @return array
     */
    private function initiate_threed_secure($transaction, $orderId)
    {
        // Start the enrollment
        $enrollment = ThreeDUtil::enroll(
            $this->get_option('account'),
            $transaction['amount'],
            $transaction['card'],
            $transaction['dynamic_descriptor']
        );
        // Cancel checkout if the enrollment fails
        if (is_null($enrollment))
        {
            return array(
                'result' => 'failure',
                'messages' => array(
                    'Could not enroll your card for 3D secure. Please try again.'
                )
            );

        }

        // Store card and key with order
        OrderUtil::set_card_token($orderId, $transaction['card']);
        OrderUtil::set_order_key($orderId, $transaction['dynamic_descriptor']);

        // Redirect the customer to the authenticate page. See spryng-payments-woocommerce.php for return hook
        $query = array(
            'url'       => $enrollment->url,
            'pareq'     => $enrollment->pareq,
            'termURL'   => site_url() . self::THREED_RETURN_URL,
            'md'        => $transaction['dynamic_descriptor']
        );

        $url = site_url() . self::THREED_AUTHENTICATE_URI . '&' . http_build_query($query);
        return array(
            'result' => 'success',
            'redirect' => $url
        );
    }

    /**
     * @param string $text
     * @param WC_Order $order
     * @return string
     */
    public function order_received_text($text, $order)
    {
        if (!$text)
        {
            return $text;
        }

        return $this->thankyou_page($order->get_id());
    }

    /**
     * @param int $orderId
     * @return string
     */
    public function thankyou_page($orderId)
    {
        $order = OrderUtil::get_order_by_id($orderId);

        if (!$order)
        {
            return '';
        }

        if ($order->has_status(ConfigUtil::get_global_setting_value('status_success')))
        {
            return __('Thank you, we have received your order and your payment was successful.');
        }

        if (
                $order->has_status(ConfigUtil::get_global_setting_value('status_pending')) ||
                $order->has_status(ConfigUtil::get_global_setting_value('status_authorized'))
        )
        {
            return __('We received your order and your payment is currently being processed. Once payment processing is 
            completed, your order will be updated automatically.');
        }

        if ($order->has_status(ConfigUtil::get_global_setting_value('status_failed')))
        {
            return __('We could not process your payment. Please try again from the \'My Account\' page.');
        }

        return __('We received your order and your payment is currently being processed. Once payment processing is 
            completed, your order will be updated automatically.');
    }
}