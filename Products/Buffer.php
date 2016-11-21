<?php
namespace Nalpeiron\Products;

use Nalpeiron\Helpers\Logs;
use Nalpeiron\Services\GetNextLicenseCode;
use Nalpeiron\Singleton;
use Nalpeiron\Helpers\Arr;
use MVC\Models\Licenses;
use Nalpeiron\Exception;

class Buffer
{
    use Singleton;

    const UPDATE_ACTION = 'schedule_update_license_buffer_event';


    public function init()
    {
        /** @see CustomOptionsEditor::getOptions() */
        add_filter('add_custom_options', [$this, 'addOptions']);

        /** @see NalpeironInit::orderStatusProcessing() */
        add_action(self::UPDATE_ACTION, [$this, 'update']);

        /** @see CustomOptionsEditor::options_editor() */
        add_filter('custom_save_option_nalpeiron_buffer_count', [$this, 'addUpdateEvent']);
    }

    public function addUpdateEvent($option_value = null)
    {
        if (!wp_next_scheduled(self::UPDATE_ACTION)) {
            wp_schedule_single_event(time(), self::UPDATE_ACTION);
        }

        if ($option_value) {
            $option_value = (int)$option_value;
            if ($option_value < 0) {
                $option_value = 0;
            }
            if ($option_value > 10000) {
                $option_value = 10000;
            }
        }

        return $option_value;
    }

    public function addOptions($options)
    {
        $options['nalpeiron_buffer_count'] = [
            'title' => 'Nalpeiron buffer size',
            'default' => '0',
            'autoload' => 'no',
            'type' => 'number',
            'blog_id' => 1,
        ];

//        if (!is_admin() && !(defined('DOING_CRON') && DOING_CRON)) {
//            return $options;
//        }

        foreach ($this->getProductsBlog1() as $product) {
            $options['nalpeiron_buffer_codes_' . strtolower($product['nalpeiron_profile']) . '_' . $product['nalpeiron_productid']] = [
                'title' => 'Nalpeiron buffer codes<br/>(' . $product['nalpeiron_profile'] . ', ' . $product['nalpeiron_productid'] . ')',
                'default' => '',
                'autoload' => 'no',
                'type' => 'textarea',
                'readonly' => 'true',
                'blog_id' => 1,
            ];
        }

        return $options;
    }

    public function update()
    {
        try {
            $count = $this->getCount();
            foreach ($this->getProductsBlog1() as $product) {
                $keys = $this->getBufferKeys($product['nalpeiron_productid'], $product['nalpeiron_profile']);
                $add_count = $count - count($keys);
                if ($add_count > 0) {
                    $codes = GetNextLicenseCode::instance()->run(
                        $product['nalpeiron_productid'],
                        $product['nalpeiron_profile'], $add_count
                    );
                    $codes = explode(',', $codes);
                    $codes = Arr::merge($keys, $codes);
                    $this->updateBufferKeys($product['nalpeiron_productid'], $product['nalpeiron_profile'], $codes);
                }
            }
        } catch (Exception $e) {
            Logs::error('update_buffer_error', $e->getMessage());
            $this->addUpdateEvent();
        }
    }

    /**
     * @param $nalpeiron_productid
     * @param $nalpeiron_profilename
     * @param $count
     * @param null|Exception $e
     * @return string
     * @throws Exception
     */
    public function getNextLicenseCode($nalpeiron_productid, $nalpeiron_profilename, $count, $e = null)
    {
        $keys = $this->getBufferKeys($nalpeiron_productid, $nalpeiron_profilename);
        $max_count = $this->getCount();
        if ($count > $max_count) {
            $code = 0;
            if (!$max_count) {
                $message = 'The buffer of license is empty.';
            } else {
                $message = 'The lack of codes in the buffer of license.';
            }
            if ($e && $e instanceof Exception) {
                $code = $e->getCode();
                $message .= ' ' . $e->getMessage();
            }

            throw new Exception($message, $code);
        }
        $result = array_splice($keys, 0, $count);
        $this->updateBufferKeys($nalpeiron_productid, $nalpeiron_profilename, $keys);
        $this->addUpdateEvent();

        return implode(',', $result);
    }

    protected function getCount()
    {
        return (int)apply_filters('get_custom_option', '0', 'nalpeiron_buffer_count');
    }

    public function getBufferKeys($productid, $profile)
    {
        $keys = apply_filters(
            'get_custom_option',
            '',
            'nalpeiron_buffer_codes_' . strtolower($profile) . '_' . $productid
        );
        if (!$keys) {
            return [];
        }

        return explode(', ', $keys);
    }

    public function updateBufferKeys($productid, $profile, $keys)
    {
        if (is_array($keys)) {
            $keys = implode(', ', $keys);
        }

        update_blog_option(1, 'nalpeiron_buffer_codes_' . strtolower($profile) . '_' . $productid, $keys);
    }

    /**
     * @return array
     */
    protected function getProductsBlog1()
    {
        $result = [];
        $product_ids = Licenses::instance()->getVirtualProductIDs(1);
        switch_to_blog(1);
        foreach ($product_ids as $product_id) {
            $product = get_product($product_id);
            $nalpeiron_productid = $product->get_attribute('nalpeiron_productid');
            $nalpeiron_profile = $product->get_attribute('nalpeiron_profilename');
            if ($nalpeiron_profile && $nalpeiron_productid) {
                $result[] = [
                    'nalpeiron_productid' => $nalpeiron_productid,
                    'nalpeiron_profile' => $nalpeiron_profile,
                ];
            }
        }
        restore_current_blog();

        return $result;
    }
}