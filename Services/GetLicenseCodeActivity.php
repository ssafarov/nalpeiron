<?php
namespace Nalpeiron\Services;

use Nalpeiron\Helpers\Helper;
use Nalpeiron\Exception;

class GetLicenseCodeActivity extends Service
{
    protected $uri = 'GetLicenseCodeActivity';
    protected $required = ['productid', 'licensecode'];
    protected $optional = [];

    public function run($productid, $licensecode)
    {
        $response = $this
            ->doSync([
                'productid' => $productid,
                'licensecode' => $licensecode,
            ])
            ->response;

        $result = Helper::xmlToArray($response->body);

        if (isset($result['activity']) && is_array($result['activity'])) {
            if (isset($result['activity']['licensecode'])) {
                return [$result['activity']];
            }

            return $result['activity'];
        }

        if ($result && is_string($result)) {
            if ($result != '<licensecode />') {
                throw new Exception($result);
            }
        }

        return false;
    }

}