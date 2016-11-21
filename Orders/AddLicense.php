<?php
namespace Nalpeiron\Orders;

use Nalpeiron;
use Nalpeiron\Singleton;
use Nalpeiron\Services\GetNextLicenseCode;
use WC_Order;
use Nalpeiron\Exception;
use MVC\Models\Licenses;

class AddLicense
{
    use Singleton;

    const PAGE_SLUG = 'add-license-to-user';

    protected $parent_slug = 'users.php';
    protected $capability = 'create_users';

    public function init()
    {
        add_filter('user_row_actions', array($this, 'filter_user_row_actions'), 10, 2);
        add_action('admin_menu', array($this, 'menu'));
    }

    public function menu()
    {
        //todo add_users_page()
        /* $hook = */
        add_submenu_page(
            $this->parent_slug,
            'Add license code to user',
            'Add License Code',
            $this->capability,
            self::PAGE_SLUG,
            [$this, 'view_add_license_code']
        );
    }

    public function view_add_license_code()
    {
        $user_id = '';
        $email = '';
        if (isset($_REQUEST['user_id']) && (int)$_REQUEST['user_id']) {
            $user_id = (int)$_REQUEST['user_id'];
            $user = new \WP_User($user_id);
            $email = $user->user_email;
        }

        $profile = '';
        if (isset($_REQUEST['profile'])) {
            $profile = $_REQUEST['profile'];
        }

        $license = '';
        if (isset($_REQUEST['license'])) {
            $license = $_REQUEST['license'];
        }

        $amount = 1;
        if (isset($_REQUEST['amount'])) {
            $amount = $_REQUEST['amount'];
        }

        $modify_data = '';
        if (isset($_REQUEST['modify_data'])) {
            $modify_data = $_REQUEST['modify_data'];
        }

        if (isset($_POST['email'])) {
            $email = $_REQUEST['email'];
            try {
                $user = get_user_by('email', $email);
                if (!$user) {
                    throw new \Exception('User not found');
                }

                $order = $this->toUser($user->ID, $profile, $amount, $modify_data, $license);

                $link = get_site_url() . "/wp-admin/post.php?post={$order->id}&action=edit";

                echo "
                    <h3>Success</h3>
                    <a href='$link'>Created order #{$order->id}</a>
                ";

                return;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        require __DIR__ . '/../views/add_license_code.php';
    }

    public function filter_user_row_actions(array $actions, \WP_User $user)
    {
        $link = '?page=' . self::PAGE_SLUG . '&user_id=' . $user->ID;
        $actions['add_license'] = '<a href="' . $link . '">' . 'Add&nbsp;license' . '</a>';

        return $actions;
    }

    /**
     * @param $user_id
     * @param string $nalpeiron_profilename
     * @param int $amount
     * @param string $modify_expire_date
     * @param string $codes
     * @return WC_Order
     * @throws Exception
     */
    public function toUser($user_id, $nalpeiron_profilename = 'Plus', $amount = 1, $modify_expire_date = null, $codes = null)
    {
        $product_ids = Licenses::instance()->getVirtualProductIDs();
        foreach ($product_ids as $product_id) {
            $maybe_product = get_product($product_id);
            if ($maybe_product->get_attribute('nalpeiron_profilename') == $nalpeiron_profilename) {
                $product = $maybe_product;
                break;
            }
        }
        if (!isset($product)) {
            throw new Exception('Product not found (license_profile=' . $nalpeiron_profilename . ')');
        }

        $nalpeiron_productid = $product->get_attribute('nalpeiron_productid');
        if (!$nalpeiron_productid) {
            throw new Exception('Nalpeiron productid is not set');
        }

        $order = $this->wc_create_order([
            'customer_id' => $user_id,
        ]);

        $order_item = $this->add_order_item($order->id, $product->id);

        wc_update_order_item_meta($order_item['id'], '_qty', $amount);
        // todo total = 0
        wc_add_order_item_meta($order_item['id'], '_admin_create', 'yes', true);

        if (!$codes) {
            $codes = GetNextLicenseCode::instance()->run($nalpeiron_productid, $nalpeiron_profilename, $amount);
        }
        Nalpeiron::instance()->addOrderItemLicense($order_item['id'], $codes);
        
        if (isset($modify_expire_date)){
            $d = new \DateTime();
            $d->modify($modify_expire_date);
            $next_payment_timestamp = $d->getTimestamp();
        } else {
            $next_payment_timestamp = null;
        }

        Nalpeiron::instance()->setExpiresTime($order->id, $order_item['id'], $next_payment_timestamp);

        $order->update_status('Other');

        return $order;
    }

    /**
     * @param array $args
     * @return int|WC_Order|\WP_Error
     */
    protected function wc_create_order($args = array())
    {
        $default_args = array(
            'status' => '',
            'customer_id' => null,
            'customer_note' => null,
            'order_id' => 0,
            'created_via' => '',
            'parent' => 0
        );

        $args = wp_parse_args($args, $default_args);
        $order_data = array();

        if ($args['order_id'] > 0) {
            $updating = true;
            $order_data['ID'] = $args['order_id'];
        } else {
            $updating = false;
            $order_data['post_type'] = 'shop_order';
            $order_data['post_status'] = 'publish';
            $order_data['ping_status'] = 'closed';
            $order_data['post_author'] = 1;
            $order_data['post_password'] = uniqid('order_');
            $order_data['post_title'] = sprintf(__('Order – %s', 'woocommerce'), strftime(_x('%b %d, %Y @ %I:%M %p', 'Order date parsed by strftime', 'woocommerce')));
            $order_data['post_parent'] = absint($args['parent']);
        }

        if ($args['status']) {
            $order_data['post_status'] = $args['status'];
        }

        if (!is_null($args['customer_note'])) {
            $order_data['post_excerpt'] = $args['customer_note'];
        }

        if ($updating) {
            $order_id = wp_update_post($order_data);
        } else {
            $order_id = wp_insert_post(apply_filters('woocommerce_new_order_data', $order_data), true);
        }

        if (is_wp_error($order_id)) {
            return $order_id;
        }

        if (!$updating) {
            update_post_meta($order_id, '_order_key', 'wc_' . apply_filters('woocommerce_generate_order_key', uniqid('order_')));
            update_post_meta($order_id, '_order_currency', get_woocommerce_currency());
            update_post_meta($order_id, '_prices_include_tax', get_option('woocommerce_prices_include_tax'));
            update_post_meta($order_id, '_customer_ip_address', isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']);
            update_post_meta($order_id, '_customer_user_agent', isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
            update_post_meta($order_id, '_customer_user', 0);
            update_post_meta($order_id, '_created_via', sanitize_text_field($args['created_via']));
        }

        if (is_numeric($args['customer_id'])) {
            update_post_meta($order_id, '_customer_user', $args['customer_id']);
        }

        update_post_meta($order_id, '_order_version', WC_VERSION);

        return new WC_Order($order_id);
    }

    /**
     * @unused
     * @return mixed|void
     */
    private function wc_get_order_statuses()
    {
        $order_statuses = array(
            'wc-pending' => _x('Pending Payment', 'Order status', 'woocommerce'),
            'wc-processing' => _x('Processing', 'Order status', 'woocommerce'),
            'wc-on-hold' => _x('On Hold', 'Order status', 'woocommerce'),
            'wc-completed' => _x('Completed', 'Order status', 'woocommerce'),
            'wc-cancelled' => _x('Cancelled', 'Order status', 'woocommerce'),
            'wc-refunded' => _x('Refunded', 'Order status', 'woocommerce'),
            'wc-failed' => _x('Failed', 'Order status', 'woocommerce'),
        );

        return apply_filters('wc_order_statuses', $order_statuses);
    }

    /**
     * @param int $order_id
     * @param int $product_id
     * @return array
     * @throws Exception
     */
    protected function add_order_item($order_id, $product_id)
    {
        $order_id = absint($order_id);
        $item_to_add = sanitize_text_field($product_id);

        if (!is_numeric($item_to_add)) {
            throw new Exception('$item_to_add must be numeric');
        }

        $post = get_post($item_to_add);

        if (!$post || ($post->post_type !== 'product' && $post->post_type !== 'product_variation')) {
            throw new Exception('post_type must be product');
        }

        $_product = get_product($post->ID);

        $item = [];
        $item['product_id'] = $_product->id;
        $item['variation_id'] = isset($_product->variation_id) ? $_product->variation_id : '';
        $item['variation_data'] = isset($_product->variation_data) ? $_product->variation_data : '';
        $item['name'] = $_product->get_title();
        $item['tax_class'] = $_product->get_tax_class();
        $item['qty'] = 1;
        $item['line_subtotal'] = wc_format_decimal($_product->get_price_excluding_tax());
        $item['line_subtotal_tax'] = '';
        $item['line_total'] = wc_format_decimal($_product->get_price_excluding_tax());
        $item['line_tax'] = '';

        // Add line item
        $item_id = wc_add_order_item($order_id, [
            'order_item_name' => $item['name'],
            'order_item_type' => 'line_item'
        ]);

        // Add line item meta
        if ($item_id) {
            wc_add_order_item_meta($item_id, '_qty', $item['qty']);
            wc_add_order_item_meta($item_id, '_tax_class', $item['tax_class']);
            wc_add_order_item_meta($item_id, '_product_id', $item['product_id']);
            wc_add_order_item_meta($item_id, '_variation_id', $item['variation_id']);
            wc_add_order_item_meta($item_id, '_line_subtotal', $item['line_subtotal']);
            wc_add_order_item_meta($item_id, '_line_subtotal_tax', $item['line_subtotal_tax']);
            wc_add_order_item_meta($item_id, '_line_total', $item['line_total']);
            wc_add_order_item_meta($item_id, '_line_tax', $item['line_tax']);

            // Store variation data in meta
            if ($item['variation_data'] && is_array($item['variation_data'])) {
                foreach ($item['variation_data'] as $key => $value) {
                    wc_add_order_item_meta($item_id, str_replace('attribute_', '', $key), $value);
                }
            }

            do_action('woocommerce_ajax_add_order_item_meta', $item_id, $item);
        }
        $item['id'] = $item_id;

        return $item;
    }
}