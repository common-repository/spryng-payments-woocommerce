<?php

/**
 * Configuration Utilities
 */
class ConfigUtil
{

    /**
     * Setting key for the sandbox API key
     *
     * @var string
     */
    const API_KEY_SANDBOX = 'api_key_sandbox';

    /**
     * Setting key for the production API key
     *
     * @var string
     */
    const API_KEY_PRODUCTION = 'api_key_live';

    /**
     * Max size of setting keys for the database
     *
     * @var int
     */
    const SETTING_KEY_MAX_SIZE = 64;

    /**
     * Get the active API key.
     *
     * @return string
     */
    public static function get_api_key()
    {
        return self::sandbox_enabled() ? self::get_global_setting_value(static::API_KEY_SANDBOX) :
            self::get_global_setting_value(static::API_KEY_PRODUCTION);
    }

    /**
     * Check if the sandbox is active.
     *
     * @return bool
     */
    public static function sandbox_enabled()
    {
        return (self::get_global_setting_value('sandbox_enabled') === 'yes');
    }

    /**
     * Get the value of a global (plugin-wide) configuration parameter.
     *
     * @param $key
     * @return string
     */
    public static function get_global_setting_value($key)
    {
        return trim(get_option(self::get_setting_id($key)));
    }

    /**
     * Get a global (plugin-wide) setting ID.
     *
     * @param $key
     * @return string
     */
    public static function get_setting_id($key)
    {
        $settingId = Spryng_Payments_WC_Plugin::PLUGIN_ID . '_' . trim($key);

        if (strlen($settingId) > static::SETTING_KEY_MAX_SIZE)
            trigger_error(sprintf('Setting ID %s (%d) is too long to be stored in the database.',
                $settingId,
                strlen($settingId)));

        return $settingId;
    }
}