<?php
namespace Nalpeiron\Services;

use Nalpeiron\Helpers\Helper;
use Nalpeiron\Exception;

class GetSystemDetails extends Service
{
    protected $uri = 'GetSystemDetails';
    protected $required = ['productid', 'computerid'];
    protected $optional = [];

    /**
     * @param number $productid
     * @param string $computerid
     *
     * @return string
     * @throws Exception
     */
    public function run($productid, $computerid)
    {
        $response = $this
            ->doSync([
                'productid' => $productid,
                'computerid' => $computerid,
            ])
            ->response;

        $result = Helper::xmlToArray($response->body);

        if (is_string($result)) {
            if ($result == 'Error: No system info data.') {
                return 'No system info data.';
            }
            throw new Exception($result);
        }

        return $result;
    }

}