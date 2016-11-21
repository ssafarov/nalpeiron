<?php
namespace Nalpeiron\Orders;

use Nalpeiron;
use Nalpeiron\Singleton;
use Nalpeiron\Services\GetNextLicenseCode;
use WC_Order;
use Nalpeiron\Exception;
use MVC\Models\Licenses;

class AllLicenses
{
    use Singleton;

    const PAGE_SLUG = 'all-license';

    protected $parent_slug = 'users.php';
    protected $capability = 'create_users';

    public function init()
    {
        add_action('admin_menu', array($this, 'menu'));
    }

    public function menu()
    {
        /* $hook = */
        add_submenu_page(
            $this->parent_slug,
            'Licenses',
            'Licenses Codes',
            $this->capability,
            self::PAGE_SLUG,
            [$this, 'view_licenses']
        );
    }

    public function view_licenses()
    {
        require __DIR__ . '/../views/licenses.php';
    }

}