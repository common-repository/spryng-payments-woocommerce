<?php

/**
 * Klarna Utilities
 */
class KlarnaUtil
{
    /**
     * Flag ID for shipment fees.
     *
     * @var int
     */
    const FLAG_SHIPMENT_FEE = 8;

    /**
     * Flag ID for handlig fees.
     *
     * @var int
     */
    const FLAG_HANDLING_FEE = 16;

    /**
     * Flag ID indicating that the order includes VAT
     *
     * @var int
     */
    const FLAG_INCL_VAT = 32;

    /**
     * Generate a formatted GoodsList instance based on a WooCommerce Order instance.
     *
     * @param WC_Order $order
     * @return \SpryngPaymentsApiPhp\Object\GoodsList
     */
    public static function generate_goods_list(WC_Order $order)
    {
        $goods = new \SpryngPaymentsApiPhp\Object\GoodsList();

        foreach($order->get_items() as $orderItem)
        {
            $product = wc_get_product($orderItem['product_id']);
            $price = OrderUtil::format_wc_amount($orderItem['total']) / (int) $orderItem['qty'];
            $vat = OrderUtil::format_wc_amount($orderItem['subtotal_tax']);
            $vatRate = (int) ((($vat / $price) * 100) / (int) $orderItem['qty']);
            $discountRate = 0;
            if ($product->is_on_sale())
                $discountRate = self::get_discount_rate($product->get_regular_price(), $product->get_sale_price());

            if (!wc_prices_include_tax())
                $price = (int) ($price + (($vatRate / 100) * $price));

            $good               = new \SpryngPaymentsApiPhp\Object\Good();
            $good->title        = $orderItem['name'];
            $good->reference    = 'WC_Product_'.$product->get_id();
            $good->quantity     = (int) $orderItem['qty'];
            $good->discount     = $discountRate;
            $good->flags        = [self::FLAG_INCL_VAT];
            $good->vat          = $vatRate;
            $good->price        = $price;

            $goods->add($good);
        }

        return $goods;
    }

    /**
     * Calculate discount rate based on the regular and sale price.
     *
     * @param $regularPrice
     * @param $salePrice
     * @return int
     */
    public static function get_discount_rate($regularPrice, $salePrice)
    {
        $regularPrice = OrderUtil::format_wc_amount($regularPrice);
        $salePrice = OrderUtil::format_wc_amount($salePrice);

        return (int) round(100 - (($salePrice / $regularPrice) * 100));
    }
}