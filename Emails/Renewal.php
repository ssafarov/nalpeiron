<?php
namespace Nalpeiron\Emails;

use WC_Subscriptions;
use WC_Subscriptions_Manager;
use WC_Order;
use WC_Subscriptions_Order;
use WC_Subscriptions_Admin;

class Renewal
{

    public static function run()
    {
        $subscriptions = WC_Subscriptions::get_subscriptions([
            'subscription_status' => 'active',
            'subscriptions_per_page' => -1,
        ]);

        $data = [];
        foreach ($subscriptions as $subscription) {
            $order = new WC_Order($subscription['order_id']);
            if (!WC_Subscriptions_Order::requires_manual_renewal($order)) {

                $amount_to_charge = WC_Subscriptions_Order::get_recurring_total($order);
                $outstanding_payments = WC_Subscriptions_Order::get_outstanding_balance(
                    $order,
                    $subscription['product_id']
                );

                if (get_option(WC_Subscriptions_Admin::$option_prefix . '_add_outstanding_balance', 'no') == 'yes'
                    && $outstanding_payments > 0
                ) {
                    $amount_to_charge += $outstanding_payments;
                }

                if ($amount_to_charge > 0) {

                    $next_payment_timestamp_gmt = \WC_Subscriptions_Manager::get_next_payment_date(
                        $subscription['subscription_key'],
                        $subscription['user_id'],
                        'timestamp'
                    );
                    $next_payment_timestamp = $next_payment_timestamp_gmt + get_option('gmt_offset') * 3600;

                    $prev_date = get_post_meta($order->id, '_sending_next_renewal', true);

                    if (($next_payment_timestamp < time() + (7 * 24 * 60 * 60))
                        && $next_payment_timestamp > time()
                        && $next_payment_timestamp != $prev_date
                    ) {
                        $data[$subscription['subscription_key']] = [
                            'subscription' => $subscription,
                            'order' => $order,
                            'amount_to_charge' => $amount_to_charge,
                            'outstanding_payments' => $outstanding_payments,
                            'next_payment' => date('d M Y', $next_payment_timestamp),
                            'next_payment_timestamp' => $next_payment_timestamp,
                        ];
                    }

                    /*
                    $_deb_data[$subscription['subscription_key']] = [
                        'subscription' => $subscription,
                        'order' => $order,
                        'amount_to_charge' => $amount_to_charge,
                        'outstanding_payments' => $outstanding_payments,
                        'next_payment' => date('d M Y', $next_payment_timestamp),
                        'next_payment_timestamp' => $next_payment_timestamp,
                    ];

                    $_deb = date('Y-m-d H:i:s', $next_payment_timestamp);
                    */

                    update_post_meta($order->id, '_next_renewal', date('d M Y', $next_payment_timestamp));
                }
            }
        }

        \WC_Emails::instance();
        


        foreach ($data as $datum) {
            update_post_meta($datum['order']->id, '_next_renewal', date('d M Y', $datum['next_payment_timestamp']));
            update_post_meta($datum['order']->id, '_sending_next_renewal', $datum['next_payment_timestamp']);

            do_action('woocommerce_before_renewal', $datum);
        }


        return;
    }


}
