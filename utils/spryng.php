<?php

/**
 * Utility class handling comminication with the payments platform.
 */
class SpryngUtil
{
    /**
     * Debug header name.
     *
     * @var string
     */
    const SPRYNG_DEBUG_HEADER_KEY = 'X-Spryng-Debug';

    /**
     * Get an instance of the PHP SDK
     *
     * @return \SpryngPaymentsApiPhp\Client
     */
    public static function get_instance()
    {
        return new \SpryngPaymentsApiPhp\Client(ConfigUtil::get_api_key(), ConfigUtil::sandbox_enabled());
    }

    /**
     * Create a new log message.
     *
     * @param $message
     * @param bool $setHeader
     */
    public static function log($message, $setHeader = false)
    {
        if (!is_string($message))
            $message = print_r($message, true);

        if ($setHeader && PHP_SAPI !== 'cli' && !headers_sent())
        {
            header(static::SPRYNG_DEBUG_HEADER_KEY . ': '. $message);
        }

        static $logger;
        if (!$logger || is_null($logger))
        {
            $logger = new WC_Logger();
        }

        $logger->add(Spryng_Payments_WC_Plugin::PLUGIN_ID . '-' . date('Y-m-d H:i:s'), $message);
    }
}