<?php

namespace Nalpeiron\Services;

use Nalpeiron\Helpers\Helper;
use Nalpeiron\Exception;

class SetLicenseStatusReturned extends Service
{
    protected $uri = 'SetLicenseStatusReturned';
    protected $required = ['productid', 'licensecode'];
    protected $optional = [];

    /**
     * @param number $productid
     * @param number|string|array $codes
     * @return bool
     * @throws Exception
     */
    public function run($productid, $codes)
    {
        if (!is_array($codes)) {
            $codes = explode(",", $codes);
        }
        $errors = '';
        foreach ($codes as $code) {
            $response = $this
                ->doSync([
                    'productid' => $productid,
                    'licensecode' => $code,
                ])
                ->response;

            $responseResult = Helper::xmlToArray($response->body);
            if ($responseResult != 'OK') {
                $errors .= ' Unable to return the licence code. Error message from nalpeiron.com: ' . $responseResult;
            }
        }

        if ($errors) {
            throw new Exception($errors);
        }

        return true;
    }

}