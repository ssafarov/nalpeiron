<?php
namespace Nalpeiron\Services;

use Nalpeiron\Helpers\Helper;
use Nalpeiron\Exception;

/**
 * Class DeleteLicenseCodeActivity
 * @package Nalpeiron\Services
 * @test DeleteLicenseCodeActivityTest
 */
class DeleteLicenseCodeActivity extends Service
{
    protected $uri = 'DeleteLicenseCodeActivity';
    protected $required = ['productid', 'licensecode'];
    protected $optional = ['computerid' => null];

    /**
     * @param number $productid
     * @param number|string|array $codes
     * @param string $computerid
     * @return array|string
     * @throws Exception
     */
    public function run($productid, $codes, $computerid = null)
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
                    'computerid' => $computerid,
                ])
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