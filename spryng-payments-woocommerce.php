<?php

/*
 * Plugin Name: Spryng Payments WooCommerce
 * Plugin URI: https://bitbucket.com/roemer/spryng-payments-woocommerce
 * Description: Extends WooCommerce by adding the Spryng Payments payment gateway.
 * Version: 1.6.7
 * Author: Roemer Bakker, Complexity
 * Author URI: https://roemerbakker.com
 */

add_action('plugins_loaded', 'spryng_payments_init',0);

function spryng_payments_init()
{
    // Verify if WooCommerce is installed
    if (!class_exists('WooCommerce') || !class_exists('WC_Payment_Gateway'))
        return;

    define('SPRYNG_DIR', dirname(__FILE__));
    define('SPRYNG_ASSETS_DIR', plugin_dir_url(__FILE__));

    // Load all necessary files
    require_once('vendor/autoload.php');
    require_once('gateways/abstract.php');
    require_once('gateways/creditcard.php');
    require_once('gateways/ideal.php');
    require_once('gateways/paypal.php');
    require_once('gateways/sepa.php');
    require_once('gateways/klarna.php');
    require_once('gateways/sofort.php');
    require_once('gateways/bancontact.php');
    require_once('gateways/subscription.php');
    require_once('gateways/sepa_recurring.php');
    require_once('utils/config.php');
    require_once('utils/customer.php');
    require_once('utils/order.php');
    require_once('utils/spryng.php');
    require_once('utils/klarna.php');
    require_once('utils/threed.php');

    // Initialize plugin
    Spryng_Payments_WC_Plugin::init();
}

/**
 * The main plugin class
 *
 * Class Spryng_Payments_WC_Plugin
 */
class Spryng_Payments_WC_Plugin
{
    /**
     * The ID of the plugin used throughout the system
     *
     * @var string
     */
    const PLUGIN_ID = 'spryng-payments-woocommerce';

    /**
     * Global textual plugin title
     *
     * @var string
     */
    const PLUGIN_TITLE = 'Spryng Payments WooCommerce Plugin';

    /**
     * Author of the plugin
     *
     * @var string
     */
    const AUTHOR = 'Spryng Payments';

    /**
     * Global version specification
     *
     * @var string
     */
    const PLUGIN_VERSION = '1.6.6';

    /**
     * Max length of setting keys.
     *
     * @var int
     */
    const SETTING_KEY_MAX_SIZE = 64;

    /**
     * Has the plugin been initialized?
     *
     * @var bool
     */
    private static $initiated = false;

    /**
     * Array for gateway identifiers (class names)
     *
     * @var array
     */
    public static $GATEWAYS = array(
        'Spryng_Payments_WC_Creditcard_Gateway',
        'Spryng_Payments_WC_iDeal_Gateway',
        'Spryng_Payments_WC_Paypal_Gateway',
        'Spryng_Payments_WC_SEPA_Gateway',
        'Spryng_Payments_WC_Klarna_Gateway',
        'Spryng_Payments_WC_SOFORT_Gateway',
        'Spryng_Payments_WC_SEPA_Recurring_Gateway',
        'Spryng_Payments_WC_Bancontact_Gateway'
    );

    /**
     * Spryng_Payments_WC_Plugin constructor.
     */
    public function __construct()
    {
        // Checks if the WooCommerce gateway class is available
        if (!class_exists('WC_Payment_Gateway')) return;

        // Include composer autoloader
        require_once('vendor/autoload.php');
    }

    /**
     * Initializes plugin using WooCommerce hooks
     */
    public static function init()
    {
        // Return if plugin is already initialized
        if (self::$initiated)
            return;

        // Add global settings
        add_filter('woocommerce_payment_gateways_settings', array(__CLASS__, 'add_global_settings'));
        // Add payment gateways
        add_filter('woocommerce_payment_gateways', array(__CLASS__, 'add_gateways'));
        // Listen for return action from redirect
        add_action('woocommerce_api_spryng_payments_return', array(__CLASS__, 'on_spryng_payments_return'));
        // Add action links
        add_filter('plugin_action_links_' . self::PLUGIN_ID, array(__CLASS__, 'add_action_links'));
        // Filter for adding title on checkout page
        add_filter('woocommerce_checkout_fields', array(__CLASS__, 'add_title_to_checkout'));
        // Validate this new field
        add_action('woocommerce_checkout_process', array(__CLASS__, 'validate_title_and_postal_code'));
        // Register a WooCommerce API method for the webhook
        add_action('woocommerce_api_spryng_payments_wc_webhook', array(__CLASS__, 'webhook'));
        // Register a WooCommerce API method to refresh account/organisation data
        add_action('woocommerce_api_spryng_payments_refresh_org_acc_transient', array(__CLASS__, 'refresh_org_acc_transients'));
        // Register a WooCommerce API method to redirect users to the 3D Secure authentication page
        add_action('woocommerce_api_spryng_payments_threed_authenticate', array(__CLASS__, 'threed_authenticate'));
        // Register a WooCommerce API method to catch redirects from the 3D Secure authentication
        add_action('woocommerce_api_spryng_payments_threed_authenticate_return', array(__CLASS__, 'threed_authenticate_return'));

        // Plugin Initialized
        self::$initiated = true;
    }

    public static function webhook()
    {
        $payload = file_get_contents('php://input');
        $json = json_decode($payload, true);

        if (!is_array($json))
        {
            SpryngUtil::log('Webhook action failed. JSON payload could not be deserialized.');
            die;
        }

        if ($json['type'] !== 'transaction')
        {
            SpryngUtil::log('Webhook action failed. Type of webhook payload is not \'transaction\'.');
            die;
        }

        $order = OrderUtil::find_order_by_transaction_id($json['_id']);
        if (!$order)
        {
            SpryngUtil::log(sprintf('Webhook action failed. Could not find a valid order for transaction ID \'%s\'.',
                $json['_id']));
            die;
        }

        if (isset($_GET['key']))
        {
            if ($_GET['key'] !== OrderUtil::get_webhook_key($order->get_id()))
            {
                SpryngUtil::log(sprintf('Provided key "%s" did not match key for order %d.',
                    $_GET['key'], $order->get_id()));
                die;
            }
        }
        else
        {
            SpryngUtil::log('Webhook call did not include key.');
            die;
        }

        try
        {
            $transaction = SpryngUtil::get_instance()->transaction->getTransactionById($json['_id']);
        }
        catch (\SpryngPaymentsApiPhp\Exception\RequestException $ex)
        {
            SpryngUtil::log(sprintf('Error occurred while handling webhook, a RequestException was thrown. Webhook
            payload: %s Exception message: %s', $json['_id'], $ex->getMessage()));
            die;
        }
        catch(\SpryngPaymentsApiPhp\Exception\TransactionException $ex)
        {
            SpryngUtil::log(sprintf('Error occurred while handling webhook, a TransactionException was thrown. Webhook
            payload: %s Exception message: %s', $json['_id'], $ex->getMessage()));
            die;
        }

        OrderUtil::handle_formal_status_change($order, $transaction);
    }

    /**
     * Organisation and account data from the API is stored in WP transient cache to reduce API calls. This method
     * flushes the cache and updates it with data from the API
     */
    public static function refresh_org_acc_transients()
    {
        // First, clear the cache
        delete_transient(self::PLUGIN_ID . '_accounts');
        delete_transient(self::PLUGIN_ID . '_organisations');

        // Check if API requests are possible at all by evaluating the stored API key
        if (is_null(ConfigUtil::get_api_key()) || ConfigUtil::get_api_key() === '')
        {
            // No API key was set, respond with error.
            SpryngUtil::log('Could not refresh transient cache. API key was not set.');
        }

        $accounts = array();
        try
        {
            $accounts = SpryngUtil::get_instance()->account->getAll();
            // Save accounts to transient cache
            set_transient(self::PLUGIN_ID . '_accounts', $accounts);
        }
        catch (\SpryngPaymentsApiPhp\Exception\RequestException $ex)
        {
            // Fetching accounts failed. Respond with error.
            SpryngUtil::log(sprintf('Could not refresh transient cache. Could not fetch accounts. Response code:
             %d. Response: \'%s\'.', $ex->getCode(), $ex->getMessage()));
        }

        $organisations = array();
        try
        {
            $organisations = SpryngUtil::get_instance()->organisation->getAll();
            // Save organisations to transient cache
            set_transient(self::PLUGIN_ID . '_organisations', $organisations);
        }
        catch (\SpryngPaymentsApiPhp\Exception\RequestException $ex)
        {
            // Fetching accounts failed. Respond with error.
            SpryngUtil::log(sprintf('Could not refresh transient cache. Could not fetch organisations. Response code:
             %d. Response: \'%s\'.', $ex->getCode(), $ex->getMessage()));
        }

        // All went well, redirect back to settings screen
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        return null;
    }

    /**
     * Render the auto submit form for 3D Secure authentication
     */
    public static function threed_authenticate()
    {
        require(SPRYNG_DIR . '/views/public/threed_authenticate.php');
        die;
    }

    public static function threed_authenticate_return()
    {
        // If the PaRes and MD are not set, the request is invalid.
        if (!isset($_POST['PaRes']) || !isset($_POST['MD']))
        {
            SpryngUtil::log(sprintf('Invalid 3D Secure Authentication return. POST: \'%s\' GET: \'%s\'.',
                json_encode($_POST), json_encode($_GET)));
            die('Something went wrong. Your card has not been charged.');
        }
        $pares = $_POST['PaRes'];
        $MD = $_POST['MD'];

        // Find the order based on the MD that has been saved as meta
        $orderId = OrderUtil::get_order_id_for_order_key($MD);
        if (is_null($orderId))
        {
            SpryngUtil::log(sprintf('No order ID could be found for key \'%s\'', $MD));
            die('We could not find your order. Please try again. You have not been charged.');
        }

        // Get the order object
        $order = OrderUtil::get_order_by_id($orderId);
        if (is_null($order))
        {
            SpryngUtil::log('Could not find order from 3D Secure Auth return: %s', $orderId);
            die('Could not find your order. Your card has not been charged.');
        }

        // Initiate the gateway so we can process the payment
        $gateway = OrderUtil::get_wc_payment_gateway_for_order($order);
        // Authorize the 3D secure transaction
        $authorization = ThreeDUtil::authorize($gateway->get_option('account'), $pares);
        if (is_null($authorization) || $authorization->status == 'N')
        {
            SpryngUtil::log(sprintf('Could not authorize payment with pares \'%s\'. Response: \'%s\''),
                $pares, json_encode($authorization));
            die('3D Secure Authorization failed. Please try again. You have not been charged.');
        }

        // Remove illegal characters from the Base64 encoded pares and cavv2 before submitting to the API
        $pares = str_replace('=','',$pares);
        $authorization->cavv2 = str_replace('=','',$authorization->cavv2);

        // Process the payment like normal
        return $gateway->process_payment($orderId, null, null, $authorization->eci, $authorization->cavv2, $pares, true);
    }

    /**
     * Global handler for return actions
     */
    public static function on_spryng_payments_return()
    {
        // Get request information
        $orderId        = isset($_GET['order_id']) ? $_GET['order_id'] : null;
        $key            = isset($_GET['key']) ? $_GET['key'] : null;

        // Fetch order
        $order = OrderUtil::get_order_by_id($orderId);

        // Check if order exists
        if (!$order)
        {
            self::set_http_response_code(404);
            return;
        }

        // Check if provided key is valid for this order
        if (!$order->key_is_valid($key))
        {
            self::set_http_response_code(401);
            return;
        }

        // Get the transaction ID for the order
        $transactionId = OrderUtil::get_transaction_id_from_order($orderId);
        // Get the gateway that processed this order
        $gateway = OrderUtil::get_wc_payment_gateway_for_order($order);

        // Check if gateway is valid
        if (!$gateway)
        {
            self::set_http_response_code(404);
            return;
        }

        // Fetch transaction
        $transaction = SpryngUtil::get_instance()->transaction->getTransactionById($transactionId);


        // Redirect customer to proper return page
        wp_safe_redirect($gateway->get_return_url($order));
    }

    public static function get_public_url($path = '')
    {
        return plugin_dir_url(__FILE__) . $path;
    }

    /**
     * Handles setting HTTP response codes
     *
     * @param $statusCode
     */
    public static function set_http_response_code($statusCode)
    {
        // Only set the response code if the environment is not a CLI and the headers have not been sent yet
        if (PHP_SAPI !== 'cli' && !headers_sent())
        {
            http_response_code($statusCode);
        }
        else
        {
            header(" ", true, $statusCode);
        }
    }

    /**
     * Ads settings tabs to WooCommerce settings
     *
     * @param $links
     * @return array
     */
    protected function add_action_links($links)
    {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', self::PLUGIN_ID ) . '</a>'
        );

        return array_merge( $plugin_links, $links );
    }

    /**
     * Merge the gateway array with the global gateways.
     *
     * @param array $gateways
     * @return array
     */
    public static function add_gateways(array $gateways)
    {
        return array_merge($gateways, self::$GATEWAYS);
    }

    /**
     * Ads a title field to the checkout page
     *
     * @param array $fields
     * @return array
     */
    public static function add_title_to_checkout(array $fields)
    {
        // Define the field
        $title =  array(
            'label' => __('Title', self::PLUGIN_ID),
            'type' => 'select',
            'required' => true,
            'class' => array('form-row-wide'),
            'clear' => true,
            'options' => array(
                'mr' => 'Mr.',
                'ms' => 'Ms.'
            )
        );

        // Put title first in the array so that it appears first on the checkout page.
        $fields['billing'] = array_merge(array('billing_title' => $title), $fields['billing']);

        return $fields;
    }

    /**
     * Validates the new title field and Dutch postcodes for SEPA and Klarna
     */
    public static function validate_title_and_postal_code()
    {
        // Options for title
        $options = array('mr', 'ms');

        // Title is not provided
        if (!isset($_POST['billing_title']) || $_POST['billing_title'] === '')
            wc_add_notice(__('Please enter your title.', self::PLUGIN_ID), 'error');

        // Validate if title is in the allowed options
        if (!in_array($_POST['billing_title'], $options))
            wc_add_notice(__('The title you provided is invalid.', self::PLUGIN_ID), 'error');

        // Validate Dutch postcodes for SEPA DirectDebit and Klarna
        if (
            isset($_POST['billing_country']) &&
            $_POST['billing_country'] == 'NL' &&
            isset($_POST['billing_postcode'])
        )
        {
            $postCode = $_POST['billing_postcode'];

            if (
                (strlen($postCode) !== 6 && strlen($postCode) !== 7) || // Postcode should have a length of 6 or 7
                preg_match_all("/[0-9]/", substr($postCode, 0, 4)) !== 4 || // First 4 characters should be numbers...
                preg_match_all("/[a-zA-Z]/", $postCode) !== 2 // and 2 characters
            )
                wc_add_notice(__('Postcode is invalid.', self::PLUGIN_ID), 'error');
        }
    }

    /**
     * Ads global settings to the WooCommerce checkout settings page.
     *
     * @param array $settings
     * @return array
     */
    public static function add_global_settings( array $settings )
    {
        $spryngSettings = array(
            array(
                'id' => ConfigUtil::get_setting_id('title'),
                'title' => __('Spryng Payments Settings', self::PLUGIN_ID),
                'type' => 'title',
                'desc_tip' => '<p>' . __('These are the global settings for the Spryng Payments gateways. These are necessary for all transactions', self::PLUGIN_ID) . '</p>',
            ),
            array(
                'id' => ConfigUtil::get_setting_id('api_key_live'),
                'title' => __('API Key for Live Environment', self::PLUGIN_ID),
                'default' => '',
                'type' => 'password',
                'desc_tip' => 'This key is used to identify you as a Spryng Payments user. You can find this key in the dashboard.',
                'css' => 'width: 350px'
            ),
            array(
                'id' => ConfigUtil::get_setting_id('api_key_sandbox'),
                'title' => __('API Key for Sandbox Environment', self::PLUGIN_ID),
                'default' => '',
                'type' => 'password',
                'desc_tip' => 'This key is used to identify you as a Spryng Payments user. You can find this key in the dashboard.',
                'css' => 'width: 350px'
            ),
            array(
                'id' => ConfigUtil::get_setting_id('sandbox_enabled'),
                'title' => 'Enable Sandbox Mode',
                'type' => 'checkbox',
                'desc_tip' => __('Turning on sandbox mode allows you to test your settings before handling real payments.', self::PLUGIN_ID),
                'default' => 'yes',
            ),
            array(
                'id' => ConfigUtil::get_setting_id('merchant_reference'),
                'title' => __('Merchant Reference', self::PLUGIN_ID),
                'type' => 'text',
                'desc_tip' => __('The merchant reference allows you to identify an order in the UI.', self::PLUGIN_ID),
                'default' => 'WCorder{id}',
                'css' => 'width: 350px'
            ),
            array(
                'id' => ConfigUtil::get_setting_id('pay_button_text'),
                'title' => __('Pay button text', self::PLUGIN_ID),
                'type' => 'text',
                'desc_tip' => __('This controls the text in the pay button on the checkout page. Default: <code>Pay with Spryng Payments</code>.', Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'default' => 'Pay with Spryng Payments',
                'css' => 'width: 350px'
            ),
            array(
                'id' => ConfigUtil::get_setting_id('capture'),
                'title' => __('Capture transactions right away', self::PLUGIN_ID),
                'type' => 'checkbox',
                'desc_tip' => __('Enabling this setting causes transactions to get captured right away, whenever possible', self::PLUGIN_ID)
            ),
            array(
                'id' => ConfigUtil::get_setting_id('customers_disabled'),
                'title' => __('Disable sending customer information with transactions', self::PLUGIN_ID),
                'type' => 'checkbox',
                'desc_tip' => 'By default, customer information is sent when making payments, so that they\'re available
                    in the Spryng Payments dashboard. Disabling this may slightly improve performance.'
            ),
            array(
                'id' => ConfigUtil::get_setting_id('threed_enabled'),
                'title' => __('Enable or disable 3D Secure for credit card transactions.'),
                'type' => 'checkbox',
                'desc_tip' => 'If your acquirer requires you to verify your customers with 3D Secure, enable this setting.',
                'default' => 'no'
            ),
            array(
                'id' => ConfigUtil::get_setting_id('enhanced_debugging'),
                'title' => __('Enable or disable enhanced debugging. This is an option for developers.'),
                'type' => 'checkbox',
                'desc_tip' => 'Enhanced debugging will log all webhook events.',
                'default' => 'no'
            ),
            array(
                'id' => ConfigUtil::get_setting_id('status_success'),
                'title' => __('Order status for successful payments'),
                'type' => 'select',
                'options' => OrderUtil::get_order_status_selector_options(),
                'default' => OrderUtil::STATUS_COMPLETED,
                'desc_tip' => __("The WC status an order gets when the transaction is successful.
                    These statuses include: Settlement Completed, Settlement Requested"
                    , Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'css' => 'width: 350px'
            ),
            array(
                'id' => ConfigUtil::get_setting_id('status_pending'),
                'title' => __('Order status for pending payments'),
                'type' => 'select',
                'options' => OrderUtil::get_order_status_selector_options(),
                'default' => OrderUtil::STATUS_WC_PENDING,
                'desc_tip' => __("The WC status an order gets when the transaction is pending.
                    These statuses include: Initiated",
                    Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'css' => 'width: 350px'
            ),
            array(
                'id' => ConfigUtil::get_setting_id('status_authorized'),
                'title' => __('Order status for authorized payments'),
                'type' => 'select',
                'options' => OrderUtil::get_order_status_selector_options(),
                'default' => OrderUtil::STATUS_ON_HOLD,
                'desc_tip' => __("The WC status an order gets when the transaction is authorized, but not yet captured.
                    These statuses include: Authorized, Settlement Processed",
                    Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'css' => 'width: 350px'
            ),
            array(
                'id' => ConfigUtil::get_setting_id('status_failed'),
                'title' => __('Order status for failed payments'),
                'type' => 'select',
                'options' => OrderUtil::get_order_status_selector_options(),
                'default' => OrderUtil::STATUS_FAILED,
                'desc_tip' => __("The WC status an order gets when the transaction has failed.
                    These statuses include: Declined, Settlement Declined, Authorization Voided, Settlement Canceled,
                     Settlement Failed, Voided, Unknown",
                    Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'css' => 'width: 350px'
            )
        );

        return self::merge_settings($spryngSettings, $settings);
    }


    /**
     * Merges Spryng settings with the global settings
     *
     * @param array $spryngSettings
     * @param array $settings
     * @return array
     */
    protected static function merge_settings($spryngSettings, $settings)
    {
        $newSettings           = array();
        $mergedSpryngSettings = false;

        // Find payment gateway options index
        foreach ($settings as $index => $setting) {
            if (isset($setting['id']) && $setting['id'] == 'payment_gateways_options'
                && (!isset($setting['type']) || $setting['type'] != 'sectionend')
            ) {
                $newSettings           = array_merge($newSettings, $spryngSettings);
                $mergedSpryngSettings = true;
            }

            $newSettings[] = $setting;
        }

        if (!$mergedSpryngSettings)
        {
            // Append Spryng settings
            $newSettings = array_merge($newSettings, $spryngSettings);
        }

        return $newSettings;
    }
}
