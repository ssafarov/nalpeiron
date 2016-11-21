<?php
namespace Nalpeiron\Orders;

use Nalpeiron;
use Nalpeiron\Singleton;
use Nalpeiron\Products;
use Nalpeiron\Services\GetNextLicenseCode;
use WC_Order;
use Nalpeiron\Exception;
use MVC\Models\Licenses;
use Nalpeiron\Services\GetLicenseCodeActivity;

class StudioVersion
{
    use Singleton;

    const PAGE_SLUG = 'studio-versions';
    const STORE_SESSION_USA = 'versionsUsa';
    const STORE_SESSION = 'versionsRaw';

    public $dataStudioVersions;
    public $dataStudioVersionsRaw;
    public $dataStudioVersionsUsa;

    protected $parent_slug = 'users.php';
    protected $capability = 'manage_options';

    public static  $mappingStoreSession = [
        Nalpeiron::STORE_USA => 'versionsUsa',
        Nalpeiron::STORE => 'versionsRaw',
    ];

    public static  $mappingStore = [
       Nalpeiron::STORE_USA => 'dataStudioVersionsUsa',
       Nalpeiron::STORE => 'dataStudioVersionsRaw',
    ];

    public function init()
    {
        add_action('admin_menu', array($this, 'menu'));
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }


    public function page_init()
    {
        if (isset($_POST['csv']) && isset($_POST['shopName'])) {
             $filename = 'versions_stat.' . date('Y-m-d-H-i-s') . '.csv';
             header('Content-Description: File Transfer');
             header('Content-Disposition: attachment; filename=' . $filename);
             header('Content-Type: text/csv; charset=' . get_option('blog_charset'), true);
             if (isset($_SESSION[self::$mappingStoreSession[$_POST['shopName']]])) {
                 $this->dataStudioVersions =  $_SESSION[self::$mappingStoreSession[$_POST['shopName']]];
             }

             require __DIR__ . '/../views/versions_csv.php';


        }
    }


    public function menu()
    {
        /* $hook = */
        add_submenu_page(
            $this->parent_slug,
            'Versions',
            'Studio Versions',
            $this->capability,
            self::PAGE_SLUG,
            [$this, 'view_licenses']
        );
    }

    public function view_licenses()
    {
        if (!empty($_SESSION[self::STORE_SESSION_USA])) {
            $this->dataStudioVersionsUsa = $_SESSION[self::STORE_SESSION_USA];
        } else {
            $this->dataStudioVersionsUsa =  Nalpeiron::instance()->getDataStudioVersions(Nalpeiron::STORE_USA);
            $_SESSION[self::STORE_SESSION_USA] =  $this->dataStudioVersionsUsa;

        }
        if (!empty($_SESSION[self::STORE_SESSION])) {
            $this->dataStudioVersionsRaw = $_SESSION[self::STORE_SESSION];
        } else {
            $this->dataStudioVersionsRaw =  Nalpeiron::instance()->getDataStudioVersions(Nalpeiron::STORE);
            $_SESSION[self::STORE_SESSION] =  $this->dataStudioVersionsRaw;

        }
        require __DIR__ . '/../views/versions.php';
    }

}