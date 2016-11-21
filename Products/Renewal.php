<?php
namespace Nalpeiron\Products;

use Nalpeiron\Services\UpdateLicenseCode;
use Nalpeiron\Services\GetLicenseCode;
use WC_Order;
use Nalpeiron\Exception;
use WC_Subscriptions_Manager;

class Renewal
{

    public static function getSubscriptionAndNextPaymentTimestamp($order_id)
    {
        $order_parent_id = wp_get_post_parent_id($order_id);
        if (!$order_parent_id) {
            $order_parent_id = $order_id;
        }
        $subscriptions = \WC_Subscriptions::get_subscriptions([
            'order_id' => $order_parent_id,
            'subscription_status' => 'active',
            'subscriptions_per_page' => 1000,
        ]);
        if (!count($subscriptions)) {
            throw new Exception('Subscriptions not found');
        }
        $subscription = reset($subscriptions);
        $next_payment_timestamp_gmt = \WC_Subscriptions_Manager::get_next_payment_date(
            $subscription['subscription_key'],
            $subscription['user_id'], 'timestamp'
        );
        $next_payment_timestamp = $next_payment_timestamp_gmt + get_option('gmt_offset') * 3600;

        return [$subscription, $next_payment_timestamp];
    }

    public static function run($user_id, $subscription_key, $order_id)
    {
        $order = new WC_Order($order_id);

        list($subscription, $next_payment_timestamp) = self::getSubscriptionAndNextPaymentTimestamp($order_id);

        if (!$order) {
            throw new Exception('Order not found');
        }

        // todo
        $stat = $order->status;
        if (!in_array($order->status, ['completed', 'processing'])) {
            throw new Exception('Order is not complete');
        }

        foreach ($order->get_items() as $order_item_id => $order_item) {
            /**
             * @var WC_Product $product
             */
            $product = wc_get_product($order_item['product_id']);

            $nalpeiron_productid = $product->get_attribute('nalpeiron_productid');
            if (!$nalpeiron_productid) {
                continue;
            }

            $st = 1;
            // todo upgrade product ?

            $nalpeiron_profilename = $product->get_attribute('nalpeiron_profilename');
            if (!$nalpeiron_profilename) {
                continue;
            }

            $count = $order_item['qty'];
            if ($count != count($count)) {
                throw new Exception('the number of licenses does not match');
            }
            $codes = wc_get_order_item_meta($order_item_id, NALPEIRON_HIDDEN_LICENSE_CODE, true);
            $codes = explode(',', $codes);

            foreach ($codes as $code) {
                try {
                    $data = GetLicenseCode::instance()->run($nalpeiron_productid, $code);
                } catch (Exception $e) {
                    wc_add_order_item_meta($order_item_id, '_errorGetLicenseCode', $e->getMessage(), true);
                    if ($e->getCode() == 0) {
                        WC_Subscriptions_Manager::put_subscription_on_hold(
                            $subscription['user_id'],
                            $subscription['subscription_key']
                        );
                    }
                    throw new Exception($e->getMessage(), 500);
                }

                if (!isset($data['subscriptionenddate'])) {
                    throw new Exception('subscriptionenddate is not set');
                }

                if (!isset($data['subscriptionperiod'])) {
                    throw new Exception('subscriptionperiod is not set');
                }

                $subscriptionEndDate = date('d M Y H:i A', $next_payment_timestamp);

                try {
                    UpdateLicenseCode::instance()->run($nalpeiron_productid, $code, [
                        'licensetype' => UpdateLicenseCode::LICENSE_TYPE_Expiration_Date,
                        'subscriptionenddate' => $subscriptionEndDate,
                    ]);
                } catch (Exception $e) {
                    wc_add_order_item_meta($order_item_id, '_errorUpdateLicenseCode', $e->getMessage(), true);
                    throw $e;
                }

                wc_add_order_item_meta($order_item_id, '_renewal', 'success', false);
                wc_add_order_item_meta($order_item_id, '_license_expires', $subscriptionEndDate, true);
            }

        }

        return;
    }
}