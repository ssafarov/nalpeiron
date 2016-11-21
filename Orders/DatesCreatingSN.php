<?php
namespace Nalpeiron\Orders;

use Nalpeiron;
use Nalpeiron\Singleton;


class DatesCreatingSN
{
    use Singleton;

    const PAGE_SLUG = 'dates-creating-serial-numbers';

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
            'Dates Creating S/N',
            'Dates Creating S/N',
            $this->capability,
            self::PAGE_SLUG,
            [$this, 'view_dates_creating_SN']
        );
    }

    public function view_dates_creating_SN()
    {
        $this->usersHasCreatingDates  = get_users(array(
            'meta_key'     => 'dates_create_serial_numbers',
        ));

        require __DIR__ . '/../views/dates_creating_SN.php';
    }

}