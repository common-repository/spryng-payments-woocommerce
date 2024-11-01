<?php

/**
 * Class CustomerUtil
 *
 * Handles all interaction with customer data
 */
class CustomerUtil
{

    /**
     * @var string The key of the date-of-birth POST parameter for Klarna payments.
     */
    const SPRYNG_PAYMENTS_DATE_OF_BIRTH_KEY = 'spryng-payments-woocommerce-date-of-birth';

    /**
     * @param $account
     * @param $organisation
     * @param WC_Order $order
     * @param $userData
     * @param bool $storeUserData
     * @return array|mixed|null|\SpryngPaymentsApiPhp\Object\Customer|string
     * @throws \libphonenumber\NumberParseException
     */
    public static function get_spryng_customer_id($account, $organisation, $order, $userData, $storeUserData = false)
    {
        // Store user data if asked to do so
        if ($storeUserData)
            self::store_customer_data_for_order($order->get_id(), $userData);

        $userId = $order->get_user_id();
        if (!self::validate_user_id($userId)) // Check if the user is registered and if we can find it by it's meta
        {
            // There is no record of a customer ID associated with the user.
            // Perhaps we can find it as order meta.
            $customerId = self::find_spryng_customer_by_order_id($order->get_id());

            // Found a customer ID for the order
            if (!is_null($customerId) && $customerId != '')
            {
                $customer = self::verify_customer_id($customerId);

                // It's valid, so return it.
                if ($customer instanceof \SpryngPaymentsApiPhp\Object\Customer)
                {
                    return $customer;
                }
            }

            // If these are not available, the user is probably coming from the 3D Secure flow,
            // get the customer data from order meta. Should be saved.
            if (!isset($userData['billing_email']) || !isset($userData['billing_phone']))
            {
                $orderMeta = self::get_customer_data_for_order($order->get_id());
                if (!is_null($orderMeta))
                    $userData = $orderMeta;
            }

            $customer = self::create_new_customer($account, $organisation, $userData);

            if ($customer instanceof \SpryngPaymentsApiPhp\Object\Customer)
            {
                return $customer;
            }

            SpryngUtil::log(sprintf('Error occurred while trying to create new customer. Message: %s',
                $customer));
            return null;
        }
        else
        {
            $customer = self::find_spryng_customer_by_user_id($userId);

            if ($customer instanceof \SpryngPaymentsApiPhp\Object\Customer)
            {
                return $customer;
            }
            else
            {
                if (is_null($customer))
                {
                    $customer = self::create_new_customer($account, $organisation, $userData, false, '', $userId);
                    self::store_spryng_customer_id($userId, $customer->_id);

                    if ($customer instanceof \SpryngPaymentsApiPhp\Object\Customer)
                    {
                        return $customer;
                    }
                    else
                    {
                        return null;
                    }
                }
                else
                {
                    SpryngUtil::log(sprintf('Error occurred while trying to create new customer. Message: %s',
                        $customer));
                    return null;
                }
            }
        }
    }

    /**
     * Checks if a user ID indicates that user has an account or not.
     *
     * @param   int     $userId
     * @return  bool
     */
    public static function validate_user_id($userId)
    {
        return ($userId !== 0 && !is_null($userId) && $userId != '' && $userId !== false);
    }

    /**
     * Creates a new customer on the platform.
     *
     * @param string $accountId
     * @param array $userData
     * @param bool $update
     * @param string $customerId
     * @param null $userId
     * @return array|\SpryngPaymentsApiPhp\Object\Customer|string
     * @throws \libphonenumber\NumberParseException
     */
    private static function create_new_customer($accountId, $organisationId, $userData, $update = false, $customerId = '', $userId = null)
    {
        $origPhoneNumber = ($userData['billing_phone'] == null) ? get_user_meta($userId, 'billing_phone', true) : $userData['billing_phone'];
        $origCountry = ($userData['billing_country'] == null) ? get_user_meta($userId, 'billing_country', true) : $userData['billing_country'];
        $origPostal = ($userData['billing_postcode'] == null) ? get_user_meta($userId, 'billing_postcode', true) : $userData['billing_postcode'];
        $phoneNumber = self::get_formatted_phone_number($origPhoneNumber, $origCountry);
        $postalCode = self::get_formatted_postal_code($origPostal, $origCountry);
        $title = ($userData['billing_title'] == null) ? get_user_meta($userId, 'billing_title', true) : $userData['billing_title'];
        if (is_null($title) || $title == '')
            $title = 'mr';

        $customer = array(
            'account'       => $accountId,
            'title'         => $title,
            'first_name'    => ($userData['billing_first_name'] == null) ? get_user_meta($userId, 'billing_first_name', true) : $userData['billing_first_name'],
            'last_name'     => ($userData['billing_last_name'] == null) ? get_user_meta($userId, 'billing_last_name', true) : $userData['billing_last_name'],
            'email_address' => ($userData['billing_email'] == null) ? get_user_meta($userId, 'billing_email', true) : $userData['billing_email'],
            'country_code'  => ($userData['billing_country'] == null) ? get_user_meta($userId, 'billing_country', true) : $userData['billing_country'],
            'city'          => ($userData['billing_city'] == null) ? get_user_meta($userId, 'billing_city', true) : $userData['billing_city'],
            'street_address'=> ($userData['billing_address_1'] == null) ? get_user_meta($userId, 'billing_address_1', true) : $userData['billing_address_1'],
            'postal_code'   => $postalCode,
            'phone_number'  => $phoneNumber,
            'organisation'  => $organisationId
        );

        if ($customer['title'] == 'mr')
        {
            $customer['gender'] = 'male';
        }
        else if ($customer['title'] == 'ms')
        {
            $customer['gender'] = 'female';
        }
        if (isset($userData[static::SPRYNG_PAYMENTS_DATE_OF_BIRTH_KEY]) && $userData[static::SPRYNG_PAYMENTS_DATE_OF_BIRTH_KEY] != '')
            $customer['date_of_birth'] = $userData[static::SPRYNG_PAYMENTS_DATE_OF_BIRTH_KEY];

        try
        {
            if ($update)
            {
                $customer = SpryngUtil::get_instance()->customer->update($customerId, $customer);
            }
            else
            {
                $customer = SpryngUtil::get_instance()->customer->create($customer);
            }
        }
        catch (\SpryngPaymentsApiPhp\Exception\CustomerException $exception)
        {
            return $exception->getMessage();
        }
        catch (\SpryngPaymentsApiPhp\Exception\RequestException $exception)
        {
            return $exception->getMessage();
        }

        return $customer;
    }

    /**
     * Searched user_meta table for Spryng Customer ID for given user ID
     *
     * @param int $userId
     * @return mixed|null|\SpryngPaymentsApiPhp\Object\Customer|string
     */
    public static function find_spryng_customer_by_user_id($userId)
    {
        $customer = get_user_meta($userId, Spryng_Payments_WC_Plugin::PLUGIN_ID . '_customer_id', true);

        if (!is_null($customer) && $customer != '')
        {
            $customer = self::verify_customer_id($customer);
            if ($customer instanceof \SpryngPaymentsApiPhp\Object\Customer)
            {
                return $customer;
            }

            return null;
        }

        return null;
    }

    /**
     * Verifies if a customer is (still) valid by fetching it.
     *
     * @param $customerId
     * @return \SpryngPaymentsApiPhp\Object\Customer|string
     */
    public static function verify_customer_id($customerId)
    {
        try
        {
            $customer = SpryngUtil::get_instance()->customer->getCustomerById($customerId);
        }
        catch (\SpryngPaymentsApiPhp\Exception\CustomerException $exception)
        {
            return $exception->getMessage();
        }
        catch (\SpryngPaymentsApiPhp\Exception\RequestException $exception)
        {
            return $exception->getMessage();
        }

        return $customer;
    }

    /**
     * @param int $orderId
     * @return string
     * @internal param WC_Order|WC_Subscription $order
     */
    public static function find_spryng_customer_by_order_id($orderId)
    {
        return get_post_meta($orderId, '_spryng_payments_customer_id', true);
    }

    /**
     * Saves a Spryng Customer ID for recurring payments.
     *
     * @param $userId
     * @param $customerId
     * @return bool
     */
    public static function store_spryng_customer_id($userId, $customerId)
    {
        if (get_user_meta($userId, Spryng_Payments_WC_Plugin::PLUGIN_ID . '_customer_id'))
        {
            delete_user_meta($userId, Spryng_Payments_WC_Plugin::PLUGIN_ID . '_customer_id');
        }

        $saveResult = add_user_meta($userId, Spryng_Payments_WC_Plugin::PLUGIN_ID . '_customer_id', $customerId);

        if (!$saveResult)
            return false;

        return true;
    }

    /**
     * Deletes the Spryng customer ID associated with a customer.
     *
     * @param $userId
     * @return bool
     */
    public static function delete_spryng_customer_id($userId)
    {
        if (get_user_meta($userId, Spryng_Payments_WC_Plugin::PLUGIN_ID . '_customer_id'))
        {
            return delete_user_meta($userId, Spryng_Payments_WC_Plugin::PLUGIN_ID . '_customer_id');
        }

        return true;
    }

    /**
     * Store customer data for customers that do not have an account, but need to use 3D Secure.
     *
     * When a customer returns from the 3D secure flow and does not have an account on this
     * site, the checkout information is no longer available. It can be stored in JSON form
     * to be retrieved when the customer returns from the checkout page, so that a customer
     * object can still be created and added to the transaction.
     *
     * @param $orderId
     * @param $customerData
     * @return false|int
     */
    public static function store_customer_data_for_order($orderId, $customerData)
    {
        // If these are not set, the customer data has already been saved.
        if (!isset($customerData['billing_phone']) || isset($customerData['billing_email']))
            return

        add_post_meta($orderId, '_spryng_customer_data', json_encode($customerData));
    }

    /**
     * Get stored customer data from Order if it's no longer available as POST data
     *
     * @param $orderId
     * @return array|mixed|object
     */
    public static function get_customer_data_for_order($orderId)
    {
        $data = get_post_meta($orderId, '_spryng_customer_data', true);

        return json_decode($data, true);
    }

    /**
     * Users libphonenumber to parse an international phone number to E164 format.
     *
     * @param   string $phoneNumber
     * @param   string $countryCode
     * @return  string
     * @throws \libphonenumber\NumberParseException
     */
    private static function get_formatted_phone_number($phoneNumber, $countryCode)
    {
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $phone = $phoneNumberUtil->parse($phoneNumber, $countryCode);

        return $phoneNumberUtil->format($phone, \libphonenumber\PhoneNumberFormat::E164);
    }

    /**
     * Formats a postal code to the format required by the platform.
     *
     * @param   string $postalCode
     * @param   string $countryCode
     * @return  string
     */
    private static function get_formatted_postal_code($postalCode, $countryCode)
    {
        switch ($countryCode)
        {
            case 'NL':
            default:
                if (strlen($postalCode) == 6)
                {
                    $postalCode = wordwrap($postalCode, 4, ' ', true);
                }
                break;
        }

        return $postalCode;
    }
}