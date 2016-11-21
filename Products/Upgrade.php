<?php
namespace Nalpeiron\Products;

use WC_Order;
use MVC\Singleton;
use Nalpeiron\Services\GetLicenseCode;
use Nalpeiron\Services\UpdateLicenseCode;
use Nalpeiron\Exception;

class Upgrade
{
    use Singleton;

    public function init()
    {
        // Nalpeiron\Products\Upgrade::woocommerce_subscriptions_renewal_order_meta();
        add_filter('subscription_get_recurring_total_gateway_scheduled', [$this, 'subscription_get_recurring_total'], 10, 3);
        add_action('woocommerce_subscriptions_renewal_order_meta', [$this, 'woocommerce_subscriptions_renewal_order_meta'], 10, 4);

        //todo
        add_filter('woocommerce_subscriptions_product_price_string', [$this, 'woocommerce_subscriptions_product_price_string'], 10, 3);

        // echo apply_filters( 'woocommerce_cart_totals_order_total_html', $value );
        add_filter('woocommerce_cart_totals_order_total_html', [$this, 'woocommerce_cart_totals_order_total_html'], 100);

        // return apply_filters( 'woocommerce_cart_subtotal', $cart_subtotal, $compound, $this );
        add_filter('woocommerce_cart_subtotal', [$this, 'woocommerce_cart_subtotal'], 100, 3);

        // return apply_filters( 'woocommerce_cart_contents_total', $cart_contents_total );
        add_filter('woocommerce_cart_contents_total', [$this, 'woocommerce_cart_contents_total'], 100);
    }

    function woocommerce_cart_contents_total($cart_contents_total)
    {
        //$cart_contents_total = str_replace(' / day', '', $cart_contents_total);
        //$cart_contents_total = str_replace(' / year', '', $cart_contents_total);

        return $cart_contents_total;
    }

    protected $cart;

    function woocommerce_cart_subtotal($cart_subtotal, $compound, $cart)
    {
        //$cart_subtotal = str_replace(' / day', '', $cart_subtotal);
        //$cart_subtotal = str_replace(' / year', '', $cart_subtotal);

        return $cart_subtotal;
    }

    function woocommerce_cart_totals_order_total_html($value)
    {
        //$value = str_replace(' / day', '', $value);
        //$value = str_replace(' / year', '', $value);

        return $value;
    }

    function woocommerce_subscriptions_product_price_string($subscription_string, \WC_Product $product, $include)
    {
//        global $selected_currency;
        global $selected_currency_sign;
//        global $selected_currency_sign_icon;

        if ($product->id == self::getUpgradeProduct()->id) {
            $productAdvanced = self::getAdvancedProduct();
            // <span class="amount">£35.00</span> now then <span class="amount">£30.00</span> / year
            $s = '/ year';
            if (strpos($subscription_string, '/ day')) {
                $s = '/ day';
            }

            $subscription_string =
                '<span class="amount">' . $selected_currency_sign . $product->get_price() . '</span>'
                . __(' now then ', 'storefront')
                . '<span class="amount recurrent">' . $selected_currency_sign . $productAdvanced->get_price() . '</span> ' . $s;
        }

        return $subscription_string;
    }

    /**
     * @param $license
     * @return WC_Order
     * @throws \Exception
     */
    protected function getProductAndOrderByLicense($license)
    {
        global $wpdb;
        $license = filter_var($license, FILTER_SANITIZE_STRING);

        $prefix = 'fu_';
        if (get_current_blog_id() == 4) {
            $prefix = 'fu_4_';
        }

        $sql = "SELECT * FROM {$prefix}license WHERE codes = '{$license}'";

        $result = $wpdb->get_results($sql);

        foreach ($result as $item) {
            $product = wc_get_product($item->product_id);
            if ($product->get_attribute('nalpeiron_profilename')) {
                $order_id = $item->order_id;

                return [$product, new WC_Order($order_id)];
            }
        }
        throw new \Exception('Order not found', 404);
    }

    /**
     * @param string $productid
     * @param string $license
     * @param string $profile
     * @param WC_Order $upgradeOrder
     * @param array $order_item
     * @return bool
     * @throws Exception
     */
    public function run($productid, $license, $profile, $upgradeOrder, $order_item, $order_item_id)
    {
        $data = GetLicenseCode::instance()->run($productid, $license);
        if (isset($data['profile']) && $data['profile'] == $profile) {
            throw new Exception('License profile cannot be changed');
        }

        $upgradeProduct = wc_get_product($order_item['product_id']);
        if ($upgradeProduct->product_type == 'subscription') {
            $subscriptionKey = $upgradeOrder->id . '_' . $upgradeProduct->id;
            $subscription = \WC_Subscriptions_Manager::get_subscription($subscriptionKey);
        }

        list($oldProduct, $oldOrder) = $this->getProductAndOrderByLicense($license);
        $oldSubscriptionKey = $oldOrder->id . '_' . $oldProduct->id;
        $oldSubscription = \WC_Subscriptions_Manager::get_subscription($oldSubscriptionKey);


        // SS we will not cancel and Old Subscription. Just try to change it's type  
        // cancel old subscription
        //\WC_Subscriptions_Manager::cancel_subscription($upgradeOrder->get_user_id(), $oldSubscriptionKey);

        $advancedProduct = self::getAdvancedProduct();

        $order_currency = $upgradeOrder->get_order_currency();
        if (!$order_currency) {
            $order_currency = Aelia_SessionManager::get_value(AELIA_CS_USER_CURRENCY, false);
        }

        $k_tax = $upgradeOrder->order_total / $upgradeProduct->get_price();
        // $upgradeOrder->order_total / $upgradeProduct->get_price()
        $amount_to_charge = $advancedProduct->get_price() * $k_tax;
        $amount_to_charge = round($amount_to_charge, 2);
        $tax = $amount_to_charge - $advancedProduct->get_price();

        $amount_to_charge_base = $advancedProduct->subscription_price * $k_tax;
        $amount_to_charge_base = round($amount_to_charge_base, 2);
        $tax_base = $amount_to_charge_base - $advancedProduct->subscription_price;

        // product_custom_fields
        $__price = get_post_meta($advancedProduct->id, '_price');
        $__regular_currency_prices = json_decode(get_post_meta($advancedProduct->id, '_regular_currency_prices')[0], true);

        $data = [
            'amount_to_charge' => $amount_to_charge,
            '_deposit_paid' => $amount_to_charge,
            '_order_currency' => $order_currency,
            '_order_total' => $amount_to_charge,
            '_order_total_base_currency' => $amount_to_charge_base,
            '_order_tax' => $tax,
            '_order_tax_base_currency' => $tax_base,
            // Net Revenue From Stripe // todo
            // Stripe Fee
            'advanced_product_price' => $advancedProduct->get_price(),
            'advanced_product_base_price' => $advancedProduct->subscription_price,
            '_subscription_recurring_amount' => $amount_to_charge,
            '_recurring_line_total' => $advancedProduct->get_price(),
            '_recurring_line_tax' => $tax,
            '_recurring_line_subtotal' => $advancedProduct->get_price(),
            '_recurring_line_subtotal_tax' => $tax,
        ];
        wc_update_order_item_meta($order_item_id, '_next_renewal_data', $data);

        update_post_meta($upgradeOrder->id, '_order_recurring_total', $amount_to_charge);
        update_post_meta($upgradeOrder->id, '_order_recurring_tax_total', $tax);


        $recurring_taxes = $upgradeOrder->get_items('recurring_tax');
        foreach ($recurring_taxes as $key => $recurring_tax) {
            wc_update_order_item_meta($key, 'tax_amount', $tax);
            wc_update_order_item_meta($key, 'tax_amount_base_currency', $tax_base);
        }

        $next_renewal_data = wc_get_order_item_meta($order_item_id, '_next_renewal_data');

        $success = UpdateLicenseCode::instance()->run($productid, $license, [
            'profile' => $profile,
            'inheritprofile' => 1,
            // 'webservices' => 1, // nalpeiron bug
        ]);
        UpdateLicenseCode::instance()->run($productid, $license, [
            'webservices' => 1,
        ]);

        return $success;
    }

    protected $original_order_id;

    /**
     * @see WC_Subscriptions_Payment_Gateways::gateway_scheduled_subscription_payment()
     * @filter subscription_get_recurring_total_gateway_scheduled
     *
     * @param $amount_to_charge
     * @param WC_Order $order
     * @param $subscription
     * @return mixed
     * @throws Exception
     */
    public function subscription_get_recurring_total($amount_to_charge, WC_Order $order, $subscription)
    {
        $this->original_order_id = null;

        $order_items = $order->get_items();
        foreach ($order_items as $order_items_id => $order_item) {
            break;
        }

        if (!$order_items_id) {
            return $amount_to_charge;
        }

        $next_renewal_data = wc_get_order_item_meta($order_items_id, '_next_renewal_data');
        if (!$next_renewal_data) {
            return $amount_to_charge;
        }

        if ($order->get_order_currency() != $next_renewal_data['_order_currency']) {
            throw new Exception('currency is invalid');
        }

        if (isset($order_item['product_id'])) {
            $product = wc_get_product((int)$order_item['product_id']);
            if (isset($next_renewal_data['amount_to_charge']) && $next_renewal_data['amount_to_charge']
                && $product->get_attribute('nalpeiron_profilename_new') == 'Advanced'
            ) {
                $this->original_order_id = $order->id;
                $amount_to_charge = $next_renewal_data['amount_to_charge'];
                $order->order_recurring_total = $amount_to_charge;
            }
        }

        return $amount_to_charge;
    }

    /**
     * @see WC_Subscriptions_Renewal_Order::generate_renewal_order()
     *
     * @param $order_meta
     * @param $original_order_id
     * @param $renewal_order_id
     * @param $new_order_role
     * @return mixed
     */
    public function woocommerce_subscriptions_renewal_order_meta($order_meta, $original_order_id, $renewal_order_id, $new_order_role)
    {
        if ($this->original_order_id == $original_order_id) {
            $order = new WC_Order($original_order_id);
            $order_items = $order->get_items();
            $_advanced_product_next_renewal = null;
            foreach ($order_items as $order_items_id => $order_item) {
                break;
            }
            if (!$order_items_id) {
                return $order_meta;
            }

            $next_renewal_data = wc_get_order_item_meta($order_items_id, '_next_renewal_data');
            if (!$next_renewal_data) {
                return $order_meta;
            }

            foreach ($order_meta as $key => &$item) {
                if (isset($next_renewal_data[$item['meta_key']]) && $next_renewal_data[$item['meta_key']]) {
                    $item['meta_value'] = $next_renewal_data[$item['meta_key']];
                }
            }
        }

        return $order_meta;
    }

    /**
     * @hack
     *
     * @return \WC_Product
     */
    public static function getAdvancedProduct()
    {
        static $product;
        if ($product) {
            return $product;
        }

        if (defined('PRODUCT_ADVANCED_ID')) {
            $product = wc_get_product(PRODUCT_ADVANCED_ID);
        } else {
            $product = wc_get_product(161954);
        }

        return $product;
        // todo us
    }

    /**
     * @hack
     *
     * @return \WC_Product
     */
    public static function getUpgradeProduct()
    {
        static $product;
        if ($product) {
            return $product;
        }

        if (defined('PRODUCT_UPGRADE_ID')) {
            $product = wc_get_product(PRODUCT_UPGRADE_ID);
        } else {
            $product = wc_get_product(166394);
        }

        return $product;
        // todo us
    }
    /**
     * @hack #2
     *
     * @return int
     */
    public static function getUpgradeProductId()
    {
        return defined('PRODUCT_ADVANCED_ID') ? PRODUCT_ADVANCED_ID : 161954;
    }
    /**
     * @hack #3
     *
     * @return int
     */
    public static function getAdvancedProductId()
    {
        return defined('PRODUCT_UPGRADE_ID') ? PRODUCT_UPGRADE_ID : 166394;
    }
}