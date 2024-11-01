<?php

if (! defined('ABSPATH'))
{
    die;
}

$pclasses = SpryngUtil::get_instance()->Klarna->getPClasses($this->get_option('spryng_payments_klarna_account'));
?>

<fieldset>
    <p class='form-row form-row-wide woocommerce-validated'>
        <label for="<?php echo esc_attr(Spryng_Payments_WC_Plugin::PLUGIN_ID . '-pclass') ?>"> Payment Plan</label>
        <select id="<?php echo esc_attr(Spryng_Payments_WC_Plugin::PLUGIN_ID . '-pclass') ?>"
                name="<?php echo esc_attr(Spryng_Payments_WC_Plugin::PLUGIN_ID . '-pclass') ?>"
                class='woocommerce-select'
                style='max-width:100%;width:100% !important;'
        >
            <?php
            foreach($pclasses as $pclass)
            {
                echo "<option value='". esc_attr($pclass->_id) . "'>" . esc_attr($pclass->description . ' - ('  . (
                    $pclass->interest_rate / 100) . '% interest)') . "</option>";
            }
            ?>
        </select>
        <label for="<?php echo esc_attr(Spryng_Payments_WC_Plugin::PLUGIN_ID . '-date-of-birth') ?>">Date of Birth</label>
        <input type="date" id="<?php echo esc_attr(Spryng_Payments_WC_Plugin::PLUGIN_ID . '-date-of-birth') ?>"
               name="<?php echo esc_attr(Spryng_Payments_WC_Plugin::PLUGIN_ID . '-date-of-birth') ?>"
               style='max-width:100%;width:100% !important;'
        >
    </p>
</fieldset>