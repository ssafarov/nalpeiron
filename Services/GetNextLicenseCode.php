<?php
namespace Nalpeiron\Services;

use Nalpeiron\Helpers\Helper;
use Nalpeiron\Exception;

class GetNextLicenseCode extends Service
{
    protected $uri = 'GetNextLicenseCode';
    protected $required = ['productid', 'profilename'];
    protected $optional = ['amount' => 1];

    private function str_repeat_extended($input, $multiplier, $separator = '')
    {
        return $multiplier == 0 ? '' : str_repeat($input . $separator, $multiplier - 1) . $input;
    }

    /**
     * @param number $productid
     * @param string $profilename
     * @param int $amount
     * @param string $debug_code
     *
     * @return string
     * @throws Exception
     */
    public function run($productid, $profilename, $amount = 1, $debug_code = null)
    {
        if ($debug_code) {
            return $this->str_repeat_extended($debug_code, $amount, ','); // todo
        }

        $response = $this
            ->doSync([
                'productid' => $productid,
                'profilename' => $profilename,
                'amount' => (int)$amount,
                'licensetype' => UpdateLicenseCode::LICENSE_TYPE_Perpetual,
            ])
            ->response;

        $codes = Helper::xmlToArray($response->body);
        if (!$codes) {
            throw new Exception('License Code is empty');
        }

        if (preg_match('/[^0-9,]/', $codes)) {
            throw new Exception($codes);
        }

        $commaCount = substr_count($codes, ",");
        $codesAmount = $commaCount + 1;
        if ($codesAmount != $amount) {
            /**
             * Used by Webservices
             */
            UpdateLicenseCode::instance()->run($productid, $codes, ['webservices' => 0]);

            throw new Exception('The resulting number of licences codes differ from the requested: ' . $codesAmount . ' received instead of ' . $amount . ' requested. All received licenses codes was returned.');
        }

        return $codes;
    }

}