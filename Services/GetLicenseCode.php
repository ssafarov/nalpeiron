<?php
namespace Nalpeiron\Services;

use Nalpeiron\Helpers\Helper;
use Nalpeiron\Exception;

/**
 * Class GetLicenseCode
 * @package Nalpeiron\Services
 */
class GetLicenseCode extends Service
{
    /**
     * Output:
     * licensecode                  string  licensecode in question
     * enabledforuse                bool    is the LC enabled for use (1 yes, 0 no)
     * licensetype                  int     the license type 0=perpetual,1=SubscriptionPeriod, 2=Expiration Date
     * clientsallowed               int     Number of clients allows
     * clientleaseperiod            int     client lease period in hours
     * webservices                  bool    is used by webservices (1 yes, 0 no)
     * profile                      string  Profile to which license code belongs
     * clientofflineleaseperiod     int     client offline lease period in hours
     * subscriptionperiod           int     subscription period in days
     * subscriptionenddate          Date    subscription end date
     * maintenanceenddate           Date    maintenance end date
     * concurrentprocesses          int     number of concurrent processes allowed (0= unlimited)
     * features                     string  XML snippet containing the tag for each feature and the value is ON or OFF
     * aaudf                        string  XML snippet containing the tag for each AA variable and the value is the set value for the AA field
     * NumActivations               string  XML snippet containing the number of current activations for this license code.
     */

    protected $uri = 'GetLicenseCode';
    protected $required = ['productid', 'licensecode'];
    protected $optional = ['shafercompanyid' => null];

    /**
     * @param number $productid
     * @param number $licensecode
     * @param int|null $shafercompanyid
     * @return array
     * @throws Exception
     */
    public function run($productid, $licensecode, $shafercompanyid = null)
    {
        $response = $this
            ->doSync([
                'productid' => $productid,
                'licensecode' => $licensecode,
                'shafercompanyid' => $shafercompanyid ? (int)$shafercompanyid : null,
            ])
            ->response;

        $result = Helper::xmlToArray($response->body);

        if (is_string($result)) {
            throw new Exception($result);
        }

        return $result;
    }
}