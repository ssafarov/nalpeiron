<?php
namespace Nalpeiron\Services;

use Nalpeiron\Helpers\Helper;
use Nalpeiron\Helpers\Arr;
use Nalpeiron\Exception;
use Nalpeiron\Helpers\Logs;

/**
 * Class UpdateLicenseCode
 * @package Nalpeiron\Services
 */
class UpdateLicenseCode extends Service
{
    /**
     * Data:
     * shafercompanyid          int     id of the company in question (optional)
     * productid*               string  product in question
     * licensecode*             string  licensecode in question
     * clientleaseperiod        int     Client Lease period in hrs (max 8760)
     * clientofflineleaseperiod int     Client Offline lease period in days (max 500)
     * enabledforuse            bool    is the LC enabled for use (1 yes, 0 no)
     * licensetype              int     the license type 0=perpetual,1=SubscriptionPeriod, 2=Expiration Date
     * clientsallowed           int     Number of clients allows
     * clientleaseperiod        int     client lease period in hours
     * webservices              bool    is used by webservices (1 yes, 0 no)
     * profile                  string  sets the code profile ('default'=none/default)
     * inheritprofile           bool    inherits profile properties (1 yes, 0 no)
     * clientofflineleaseperiod int     client offline lease period in hours
     * subscriptionperiod       int     subscription period in days
     * subscriptionenddate      Date    subscription end date
     * maintenanceenddate       Date    maintenance end date
     * concurrentprocesses      int     number of concurrent processes allowed (0= unlimited)
     * features                 string  XML snippet containing the tag for each feature and the value is ON or OFF
     * aaudf                    string  XML snippet containing the tag for each AA variable and the value is the set value for the AA field
     */

    const LICENSE_TYPE_Perpetual = 0;
    const LICENSE_TYPE_Subscription_Period = 1;
    const LICENSE_TYPE_Expiration_Date = 2;

    protected $uri = 'UpdateLicenseCode';
    protected $required = ['productid', 'licensecode'];
    protected $optional = [
        'shafercompanyid' => null,
    ];

    /**
     * @param number $productid
     * @param number|string|array $codes
     * @param array $optional
     * @return bool
     * @throws Exception
     */
    public function run($productid, $codes, $optional = [])
    {
        if (!is_array($codes)) {
            $codes = explode(',', $codes);
        }
        $errors = '';
        foreach ($codes as $code) {
            $response = $this
                ->doSync(Arr::merge($optional, [
                    'productid' => $productid,
                    'licensecode' => $code
                ]))
                ->response;
            $result = Helper::xmlToArray($response->body);
            if ($result != 'OK') {
                $errors .= ' ' . $result;
            }

        }

        if ($errors) {
            throw new Exception($errors);
        }

        return true;
    }
}