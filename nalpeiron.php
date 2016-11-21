<?php
/*
Plugin Name:  Nalpeiron
Plugin URI:   http://itransition.com
Description:  Nalpeiron custom plugin
Version:      1.0
Author:       Sergey Safarov
*/

require_once "autoloader.php";

define('NALPEIRON_AUTH_USERNAME', 'nalpeiron_auth_username');
define('NALPEIRON_AUTH_PASSWORD', 'nalpeiron_auth_password');
define('NALPEIRON_AUTH_CUSTOMERID', 'nalpeiron_auth_customerid');

define('NALPEIRON_HIDDEN_LICENSE_CODE', '_license_code');
define('NALPEIRON_DISPLAY_LICENSE_CODE', 'License code');
define('NALPEIRON_DISPLAY_LICENSE_CODES', 'License codes');

use Nalpeiron\Services\GetLicenseCode;
use \Nalpeiron\Singleton;
use \Nalpeiron\Services\GetNextLicenseCode;
use \Nalpeiron\Services\UpdateLicenseCode;
use \Nalpeiron\Helpers\Arr;
use \Nalpeiron\Helpers\Logs;
use \Nalpeiron\Products\Buffer;
use \Nalpeiron\Products\Upgrade;
use \Nalpeiron\Products\Renewal as Products_Renewal;
use \Nalpeiron\Emails\Renewal as Emails_Renewal;
use \Nalpeiron\Exception;
use \Nalpeiron\Orders\AddLicense;
use \Nalpeiron\Services\GetLicenseCodeActivity;
use \Nalpeiron\Services\GetSystemDetails;

class Nalpeiron
{
    use Singleton;

    /**
     * @see ActionScheduler_QueueRunner::WP_CRON_HOOK
     */
    const WP_CRON_HOOK = 'action_scheduler_run_queue';
    const STORE = 'ROW';
    const STORE_USA = 'Usa';
    const NALPEIRON_PRODUCT_ID = '5902400102';
    const LICENSE_ACTIVATED = 'ACTIVATED';
    const OTHERS_VERSION = 'Others';
    const PROFILE_STATUS = 'None';

    public static $mappingStore = [
        self::STORE => 'fu_',
        self::STORE_USA  => 'fu_4_',
    ];

    public function init()
    {
        /** @see CustomOptionsEditor::getOptions() */
        add_filter('add_custom_options', [$this, 'addOptions']);
        /** @see WC_Subscriptions_Renewal_Order::trigger_renewal_payment_complete() */
        add_action('woocommerce_renewal_order_payment_complete', [$this, 'renewalLicense']);
        add_action('schedule_renewal_order_payment_complete', [$this, 'renewalLicense2'], 10, 4);
        add_action('processed_subscription_renewal_payment', [$this, 'renewalLicense2'], 10, 2);
        /** @see WC_Order::update_status() */
        add_action('woocommerce_order_status_changed', [$this, 'tryOrderStatusProcessing'], 10, 3);
        add_action('woocommerce_order_status_changed', [$this, 'orderStatusRefunded'], 10, 3);
        add_action('schedule_disable_license_code', [$this, '_disableLicenseCode'], 10, 3);
        /** @see ActionScheduler_QueueRunner::process_action() */
        add_action('action_scheduler_before_execute', [$this, 'test_scheduler_before_execute']);
        /** @see ActionScheduler_QueueRunner::run() */
        add_action(self::WP_CRON_HOOK, [$this, 'renewalSendEmails']);

        Buffer::instance()->init();

        add_action('schedule_set_expires_time', [$this, 'setExpiresTime'], 10, 4);

        if (is_admin()) {
            AddLicense::instance()->init();
            Nalpeiron\Orders\AllLicenses::instance()->init();
            Nalpeiron\Orders\StudioVersion::instance()->init();
            Nalpeiron\Orders\DatesCreatingSN::instance()->init();
        }

        Upgrade::instance()->init();
    }


    function addOptions($options)
    {
        $nalpeiron_options = [
            NALPEIRON_AUTH_USERNAME => [
                'title' => 'Nalpeiron auth username',
                'default' => 'f3dweb',
                'autoload' => 'no',
                'type' => 'text',
                'blog_id' => 1,
            ],
            NALPEIRON_AUTH_PASSWORD => [
                'title' => 'Nalpeiron auth password',
                'default' => '',
                'autoload' => 'no',
                'type' => 'password',
                'blog_id' => 1,
            ],
            NALPEIRON_AUTH_CUSTOMERID => [
                'title' => 'Nalpeiron auth customerid',
                'default' => '3601',
                'autoload' => 'no',
                'type' => 'text',
                'blog_id' => 1,
            ],
        ];

        return Arr::merge($options, $nalpeiron_options);
    }

    protected $renewal_order_id;

    function renewalLicense($order_id)
    {
        $this->renewal_order_id = $order_id;
    }

    // do_action( 'processed_subscription_renewal_payment', $user_id, $subscription_key ); // todo
    function renewalLicense2($user_id, $subscription_key, $order_id = null, $count = 0)
    {
        if (!$order_id) {
            $order_id = $this->renewal_order_id;
        }
        try {
            Products_Renewal::run($user_id, $subscription_key, $order_id);
        } catch (Exception $e) {
            if ($e->getCode() == 0) {
                return;
            }
            if ($count > 20) {
                Logs::error('renewalLicense', [
                    'user_id' => $user_id,
                    'subscription_key' => $subscription_key,
                    'order_id' => $order_id,
                    'error' => $e->getMessage(),
                ]);

                return;
            }
            wp_schedule_single_event(time(), 'schedule_renewal_order_payment_complete', [
                'user_id' => $user_id,
                'subscription_key' => $subscription_key,
                'order_id' => $order_id,
                'count' => $count + 1,
            ]);
        }
    }


    public function tryOrderStatusProcessing($order_id, $old_status, $new_status)
    {
        try {
            $this->orderStatusProcessing($order_id, $old_status, $new_status);
        } catch (Exception $e) {
            throw $e->getSpanException();
        }
    }

    protected function orderStatusProcessing($order_id, $old_status, $new_status)
    {
        if ($new_status == 'processing' && $old_status != 'processing') {
            $order = new WC_Order($order_id);
            $isAutoComplete = true;
            $is_renewal_order = (bool)wp_get_post_parent_id($order_id);
            foreach ($order->get_items() as $order_item_id => $order_item) {
                /**
                 * @var WC_Product $product
                 */
                $product = wc_get_product($order_item['product_id']);
                if (!$product->is_virtual() || $product->is_downloadable()) {
                    $isAutoComplete = false;
                    continue;
                }

                $nalpeiron_productid = $product->get_attribute('nalpeiron_productid');
                if (!$nalpeiron_productid) {
                    continue;
                }

                //$is_renewal_order = (bool)wc_get_order_item_meta($order_item_id, NALPEIRON_HIDDEN_LICENSE_CODE, true);
                if ($is_renewal_order) {
                    continue;
                }

                /**
                 * Upgrade
                 */
                $nalpeiron_profilename_old = $product->get_attribute('nalpeiron_profilename_old');
                $nalpeiron_profilename_new = $product->get_attribute('nalpeiron_profilename_new');
                if ($nalpeiron_profilename_new) {
                    $upgrad_license = wc_get_order_item_meta($order_item_id, NALPEIRON_HIDDEN_LICENSE_CODE, true);
                    Upgrade::instance()->run($nalpeiron_productid, $upgrad_license, $nalpeiron_profilename_new, $order, $order_item, $order_item_id);
                    continue;
                }

                /**
                 * Generate
                 */
                $nalpeiron_profilename = $product->get_attribute('nalpeiron_profilename');
                if (!$nalpeiron_profilename) {
                    continue;
                }

                $count = $order_item['qty'];

                try {
                    $codes = GetNextLicenseCode::instance()->run($nalpeiron_productid, $nalpeiron_profilename, $count);
                } catch (Exception $e) {
                    Logs::error('GetNextLicenseCode', [
                        'error' => $e->getMessage(),
                    ]);

                    try {
                        $codes = Buffer::instance()->getNextLicenseCode(
                            $nalpeiron_productid,
                            $nalpeiron_profilename,
                            $count,
                            $e
                        );
                    } catch (Exception $e) {
                        Logs::error('Buffer::GetNextLicenseCode', [
                            'error' => $e->getMessage(),
                        ]);
                        // todo fix stripe refund
                        // todo set status failed or refund
                        // todo deactivate subscription
                        if ($e->getCode() == 0) {
                            throw new Exception("Due to a technical error we could not generate your license code,
                            please contact support@fuel-3d.com for help. (" . $e->getMessage() . ")");
                        } else {
                            throw new Exception(\MVC\Models\Licenses::NOT_AVAILABLE, $e->getCode());
                        }
                    }
                }

                $this->addOrderItemLicense($order_item_id, $codes);
                $this->setExpiresTime($order_id, $order_item_id);
            }

            if ($isAutoComplete) {
                // $order->update_status('completed');
            }
        }
    }

    /**
     * @param $code
     * @return \Nalpeiron\Helpers\type
     * @throws Exception
     *
     * @see http://www.cryptopp.com/wiki/Base32Decoder
     * @see http://tools.ietf.org/html/draft-ietf-idn-dude-02
     */
    public static function licenseEncode($code)
    {
        if (!$code) {
            return '';
        }
        $aes = new \Nalpeiron\Helpers\AES($code);
        $enc = $aes->encrypt();
//        $aes->setData($enc);
//        $dec = $aes->decrypt();

        return $enc;
    }

    public function addOrderItemLicense($order_item_id, $codes)
    {
        wc_add_order_item_meta($order_item_id, NALPEIRON_HIDDEN_LICENSE_CODE, $codes, true);
        if (substr_count($codes, ',')) {
            $codes = explode(',', $codes);
            $arr = [];
            foreach ($codes as $code) {
                $arr[] = self::licenseEncode($code);
            }
            $codes = implode(', ', $arr);
        } else {
            $codes = self::licenseEncode($codes);
        }
        wc_add_order_item_meta($order_item_id, NALPEIRON_DISPLAY_LICENSE_CODES, $codes, true); // for user
    }

    public function setExpiresTime($order_id, $order_item_id, $next_payment_timestamp = null, $count = 0)
    {
        $codes = wc_get_order_item_meta($order_item_id, NALPEIRON_HIDDEN_LICENSE_CODE, true);

        $order_item_product_id = wc_get_order_item_meta($order_item_id, '_product_id', true);
        $product = wc_get_product($order_item_product_id);

        $nalpeiron_productid = $product->get_attribute('nalpeiron_productid');
        if (!$nalpeiron_productid) {
            return;
        }

        $nalpeiron_perpetual = ($product->get_attribute('nalpeiron_perpetual') == 'perpetual')?true:false;

        if (!$nalpeiron_perpetual) {
            if ( isset( $next_payment_timestamp ) ) {
                $next_payment_time = $next_payment_timestamp;
            } elseif ( ! $nalpeiron_perpetual ) {
                try {
                    list( $subscription, $next_payment_time ) = Products_Renewal::getSubscriptionAndNextPaymentTimestamp( $order_id );
                } catch ( Exception $e ) {
                    return; // not subscription product at all;
                }
            }
            $subscriptionEndDate = date('d M Y H:i A', $next_payment_time);
        } else {
            $subscriptionEndDate = null;
        }

        $licenseAttributes = [];
        $licenseAttributes['licensetype'] = $nalpeiron_perpetual?UpdateLicenseCode::LICENSE_TYPE_Perpetual : UpdateLicenseCode::LICENSE_TYPE_Expiration_Date;
        if (isset($subscriptionEndDate)) {
            $licenseAttributes['subscriptionenddate'] = $subscriptionEndDate;
        }

        try {
            UpdateLicenseCode::instance()->run($nalpeiron_productid, $codes, $licenseAttributes);
            wc_add_order_item_meta($order_item_id, '_license_expires', $subscriptionEndDate, true);

            wc_add_order_item_meta($order_item_id, '_license_perpetual', $nalpeiron_perpetual, true);

	        // Hack to set lifetime product as a subscription, which was set as a simple product
	        if ($nalpeiron_perpetual){
		        wc_add_order_item_meta($order_item_id, '_subscription', $product->get_attribute('nalpeiron_perpetual'), true);
	        }
        } catch (Exception $e) {
            wc_add_order_item_meta($order_item_id, '_errorUpdateLicenseCode', $e->getMessage(), true);
            if ($count > 50) {
                Logs::error('setExpiresTime', [
                    'order_id' => $order_id,
                    'error' => $e->getMessage(),
                ]);
                return;
            }
            if (!$nalpeiron_perpetual) {
                if (isset($next_payment_timestamp)) {
                    wp_schedule_single_event( time(), 'schedule_set_expires_time', [
                        'order_id'               => $order_id,
                        'order_item_id'          => $order_item_id,
                        'next_payment_timestamp' => $next_payment_timestamp,
                        'count'                  => $count + 1,
                    ] );
                }
            }
        }
    }

    // todo order failed : if renewal then re-upgrade

    function orderStatusRefunded($order_id, $old_status, $new_status)
    {
        // refunded
        if ($new_status == 'refunded' && in_array($old_status, ['processing', 'completed'])) {
            $order = new WC_Order($order_id);
            // todo fix stripe refund
            foreach ($order->get_items() as $order_item_id => $order_item) {
                /**
                 * @var WC_Product $product
                 */
                $product = get_product($order_item['product_id']);

                $nalpeiron_productid = $product->get_attribute('nalpeiron_productid'); // 4565300100
                if (!$nalpeiron_productid) {
                    continue;
                }

                wc_delete_order_item_meta($order_item_id, NALPEIRON_DISPLAY_LICENSE_CODE); // for user
                wc_delete_order_item_meta($order_item_id, NALPEIRON_DISPLAY_LICENSE_CODES); // for user

                $codes = wc_get_order_item_meta($order_item_id, NALPEIRON_HIDDEN_LICENSE_CODE);
                if (!$codes) {
                    continue;
                }

                $this->_disableLicenseCode($nalpeiron_productid, $codes);

                wc_add_order_item_meta($order_item_id, NALPEIRON_HIDDEN_LICENSE_CODE . '-refunded', $codes, true);
                wc_delete_order_item_meta($order_item_id, NALPEIRON_HIDDEN_LICENSE_CODE);
            }
        }
    }

    /**
     * Disabled License Code
     */
    function _disableLicenseCode($nalpeiron_productid, $codes, $count = 0)
    {
        try {
            UpdateLicenseCode::instance()->run($nalpeiron_productid, $codes, ['enabledforuse' => 0]);
        } catch (Exception $e) {
            if ($count > 50) {
                Logs::error('_disableLicenseCode', [
                    'codes' => $codes,
                    'error' => $e->getMessage(),
                ]);

                return;
            }
            wp_schedule_single_event(time(), 'schedule_disable_license_code', [
                'nalpeiron_productid' => $nalpeiron_productid,
                'codes' => $codes,
                'count' => $count + 1,
            ]);
        }
    }

    function test_scheduler_before_execute($action_id)
    {
        # $order = new WC_Order($action_id);
        return;
    }

    function renewalSendEmails()
    {
        Emails_Renewal::run();
    }

    //TODO
    /**
     * @param $value_first
     * @param $value_second
     * @return float
     */
    public function orderByVersionHighest($value_first, $value_second)
    {
        $v1 = floatval($value_first->version) * 10000;
        $v2 = floatval($value_second->version) * 10000;
        return $v2 - $v1;
    }

    /**
     * @param $store
     * @return array|object
     * @throws Exception
     */
    public function getDataStudioVersions($store)
    {
        global $wpdb;
        $result = $wpdb->get_results("
        SELECT
          codes.meta_value        as codes,
          products.meta_value     as product_id,
          i.order_item_name       as title,
          product_posts.post_name as slug,
          i.order_id              as orderId,
          i.order_item_id         as order_item_id,
          customer_user.meta_value as order_user_id,
          order_posts.post_date   as purchaseDate,
          t_user.user_email       as email,
          user_meta_first_name.meta_value      as firstName,
          user_meta_last_name.meta_value      as lastName
        FROM `" . self::$mappingStore[$store] . "woocommerce_order_items`     as i
        JOIN `" . self::$mappingStore[$store] . "woocommerce_order_itemmeta`  as codes         ON codes.order_item_id = i.order_item_id     AND codes.meta_key   = '_license_code'
        JOIN `" . self::$mappingStore[$store] . "woocommerce_order_itemmeta`       as products       ON products.order_item_id = i.order_item_id   AND products.meta_key = '_product_id'
        LEFT JOIN `" . self::$mappingStore[$store] . "posts`                       as product_posts  ON product_posts.ID = products.meta_value
        LEFT JOIN `" . self::$mappingStore[$store] . "posts`                       as order_posts    ON order_posts.ID = i.order_id
        LEFT JOIN `" . self::$mappingStore[$store] . "postmeta`                    as customer_user  ON customer_user.post_id = order_posts.ID     AND customer_user.meta_key = '_customer_user'
        LEFT JOIN `" . self::$mappingStore[self::STORE] . "users`                       as t_user    ON t_user.ID = customer_user.meta_value
        LEFT JOIN `" . self::$mappingStore[self::STORE] . "usermeta`                    as user_meta_first_name    ON user_meta_first_name.user_id = customer_user.meta_value  AND user_meta_first_name.meta_key = 'first_name'
        LEFT JOIN `" . self::$mappingStore[self::STORE] . "usermeta`                    as user_meta_last_name    ON user_meta_last_name.user_id = customer_user.meta_value  AND user_meta_last_name.meta_key = 'last_name'
        ORDER BY purchaseDate, order_item_id;
        ");
        
        if (!empty($result)) {
            foreach ($result as $key => $item) {
                if (isset($item->email)) {
                    try {
                        if (strpos($item->codes, ',') !== false) {
                            unset($result[$key]);
                            $codes = explode(',', $item->codes);

                            foreach ($codes as $codeId) {
                                $newItem = clone $item;
                                $newItem->codes = $codeId;
                                $dataActivity = GetLicenseCodeActivity::instance()->run(self::NALPEIRON_PRODUCT_ID, $codeId);
                                $dataLicenseCode = GetLicenseCode::instance()->run(self::NALPEIRON_PRODUCT_ID, $codeId);
                                $newItem->version = self::OTHERS_VERSION;
                                $newItem->profile = self::PROFILE_STATUS;
                                if (isset($dataLicenseCode['profile'])) {
                                    $newItem->profile = $dataLicenseCode['profile'];
                                }
                                if (!empty($dataActivity)) {
                                    foreach ($dataActivity as $itemActivity) {
                                        if (isset($itemActivity['computerid']) && !empty($itemActivity['computerid']) && isset($itemActivity['status']) && $itemActivity['status'] == self::LICENSE_ACTIVATED) {
                                            $computerId = $itemActivity['computerid'];
                                            $systemDetails = GetSystemDetails::instance()->run(self::NALPEIRON_PRODUCT_ID, $computerId);
                                            if (!empty($systemDetails['systemdetails']['version'])) {
                                                $newItem->version = $systemDetails['systemdetails']['version'];
                                            }
                                        }
                                    }
                                } else {
                                    continue;
                                }
                                $result[] = $newItem;
                            }
                        } else {
                            $dataActivity = GetLicenseCodeActivity::instance()->run(self::NALPEIRON_PRODUCT_ID, $item->codes);
                            $dataLicenseCode = GetLicenseCode::instance()->run(self::NALPEIRON_PRODUCT_ID, $item->codes);
                            $item->version = self::OTHERS_VERSION;
                            $item->profile = self::PROFILE_STATUS;
                            if (isset($dataLicenseCode['profile'])) {
                                $item->profile = $dataLicenseCode['profile'];
                            }
                            if (!empty($dataActivity)) {
                                foreach ($dataActivity as $itemActivity) {
                                    if (isset($itemActivity['computerid']) && !empty($itemActivity['computerid']) && isset($itemActivity['status']) && $itemActivity['status'] == self::LICENSE_ACTIVATED) {
                                        $computerId = $itemActivity['computerid'];
                                        $systemDetails = GetSystemDetails::instance()->run(self::NALPEIRON_PRODUCT_ID, $computerId);
                                        if (!empty($systemDetails['systemdetails']['version'])) {
                                            $item->version = $systemDetails['systemdetails']['version'];
                                        }
                                    }
                                }
                            } else {
                                unset($result[$key]);
                            }
                        }
                    } catch (Exception $e) {
                        $item->version = $e->getMessage();
                    }
                } else {
                    unset($result[$key]);
                }
            }
            usort($result, array($this, "orderByVersionHighest"));
            return $result;
        }

        return array();
    }

}

Nalpeiron::instance()->init();

//test
add_action('wp_footer', '__test');
function __test()
{
//    Emails_Renewal::run(); // todo remove
    //Upgrade::run('5902400102', '378400000034031082', 'Plus');

    return;
}
