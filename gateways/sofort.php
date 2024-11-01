<?php

class Spryng_Payments_WC_SOFORT_Gateway extends Spryng_Payments_WC_Abstract_Gateway
{
    const METHOD_ID = 'spryng_payments_sofort';

    const DEFAULT_TITLE = 'SOFORT';

    const DEFAULT_DESCRIPTION = 'Pay using the SOFORT platform.';

    public $AVAILABLE_COUNTRIES = array('AT', 'BE', 'CZ', 'DE', 'HU', 'IT', 'NL', 'PL', 'SK', 'ES', 'CH', 'GB');

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

    public function init_form_fields()
    {
        return array_merge($this->form_fields, array(
            'project_id' => array(
                'title' => __('SOFORT Project ID', Spryng_Payments_WC_Plugin::PLUGIN_ID),
                'type'  => 'text',
                'label' => 'The ID of the project configured in the SOFORT dashboard.',
                'default' => '',
                'desc_tip' => true
            )
        ));
    }

    public function is_available()
    {
        $projectId = $this->get_option('project_id');

        if (is_null($projectId) || $projectId === '')
        {
            return false;
        }

        return parent::is_available();
    }

    public function get_transaction($orderId)
    {
        $returnUrl = OrderUtil::get_return_url_for_order(OrderUtil::get_order_by_id($orderId));
        $countryCode = $_POST['billing_country'];
        $projectId = $this->get_option('project_id');

        if (is_null($projectId) || $projectId === '')
        {
            return array(
                'result' => 'failure',
                'messages' => array(
                    'SOFORT is not properly configured. Please contact the store administrator.'
                )
            );
        }
        if (!in_array($countryCode, $this->AVAILABLE_COUNTRIES))
        {
            return array(
                'result' => 'failure',
                'messages' => array(
                    'SOFORT is not available for the selected country.'
                )
            );
        }

        $transaction = array(
            'payment_product' => 'sofort',
            'country_code' => $countryCode,
            'details' => array(
                'redirect_url' => $returnUrl,
                'project_id' => $this->get_option('project_id')
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