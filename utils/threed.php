<?php

class ThreeDUtil
{
    public static function get_card($cardId)
    {
        try {
            $card = SpryngUtil::get_instance()->card->get($cardId);
        }
        catch (\SpryngPaymentsApiPhp\Exception\RequestException $exception)
        {
            SpryngUtil::log(sprintf('Could not fetch card \'%s\' for 3D Secure check. Aborting.'), $cardId);
            return null;
        }

        return $card;
    }

    public static function enroll($account, $amount, $card, $description)
    {
        try {
            $enrollment = SpryngUtil::get_instance()->threeD->enroll(array(
                'account'       => $account,
                'amount'        => $amount,
                'card'          => $card,
                'description'   => $description
            ));
        } catch (\SpryngPaymentsApiPhp\Exception\RequestException $exception)
        {
            SpryngUtil::log(sprintf('Could not enroll card \'%s\' for 3D secure. HTTP Code: %i.
                Message: \'%s\'.', $card, $exception->getCode(), $exception->getMessage()));
            return null;
        }

        return $enrollment;
    }

    public static function authorize($account, $pares)
    {
        try {
            $authorization = SpryngUtil::get_instance()->threeD->authorize(array(
                'account'   => $account,
                'pares'     => $pares
            ));
        } catch(\SpryngPaymentsApiPhp\Exception\RequestException $exception)
        {
            SpryngUtil::log('3D Secure authorization failed. Pares: \'%s\' account: \'%s\'
                Response code: %d Response message: \'%s\'', $pares, $account, $exception->getResponseCode(), $exception->getResponse());
            return null;
        }

        return $authorization;
    }

    /**
     * Boolean that indicates whether 3D Secure is enabled
     *
     * @return bool
     */
    public static function enabled()
    {
        return (ConfigUtil::get_global_setting_value('threed_enabled') === 'yes');
    }
}