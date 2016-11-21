<?php
namespace Nalpeiron\Services;

use Nalpeiron\Singleton; // trait
use Nalpeiron\Helpers\Helper;
use Nalpeiron\Helpers\Arr;
use Nalpeiron\Exception;

/**
 * Class Service
 * @package Blackhawk
 */
abstract class Service
{
    use Singleton;

    const SERVICE_REQUEST_METHOD_GET = 'GET';
    const SERVICE_REQUEST_METHOD_POST = 'POST';
    const SERVICE_REQUEST_METHOD_PUT = 'PUT';
    const SERVICE_REQUEST_METHOD_DELETE = 'DELETE';

    const SERVICE_BASE_URL = 'https://my.nalpeiron.com/shaferws.asmx';
    const LICENSE_PROFILE_NAME_PLUS = 'Plus';
    const LICENSE_PROFILE_NAME_ADVANCED = 'Advanced';

    const MAX_PING_NUMBER = 100;

    // const SERVICE_REQUEST_SSL_CERT = '/';
    // const SERVICE_REQUEST_SSL_CERTPASSWD = '';

    public static $__request_debug = null; // for unit test

    /**
     * @var array
     */
    public $response = null;

    /**
     * @var string
     */
    protected $uri = '';

    /**
     * @var array
     */
    protected $required = [];

    /**
     * @var array
     */
    protected $optional = [];

    /**
     * @var resource
     * @type curl
     */
    private static $ch = null;

    private static $countPing = 0;

    public function __construct()
    {
        if (is_null(self::$ch)) {
            self::$ch = curl_init();
        }
    }

    public function __destruct()
    {
        if (!is_null(self::$ch)) {
            curl_close(self::$ch);
            self::$ch = null;
        }
    }

    /**
     * @param array $data
     * @return $this
     * @throws Exception
     */
    protected function doSync(array $data)
    {
        $data = Arr::merge($this->optional, $data);
        $this->isValidData($data);

        $body = [
            'auth' => Helper::arrayToXml($this->getAuth(), 'auth'),
            'data' => Helper::arrayToXml($data, 'data'),
        ];

        $b = '';
        foreach ($body as $name => $value) {
            if ($b) {
                $b .= '&';
            }
            $b .= "$name=$value";
        }
        $body = $b;

        $this->response = $this->doRequest($this->uri, self::SERVICE_REQUEST_METHOD_POST, $body);

        if (!isset($this->response->code) || $this->response->code != 200) {
            throw new Exception('Response code must be 200', $this->response->code ?: 520);
        }

        if (!isset($this->response->headers['content_type']) || $this->response->headers['content_type'] != 'text/xml; charset=utf-8') {
            throw new Exception('Content type must be xml');
        }

        if (!isset($this->response->body) || !$this->response->body) {
            throw new Exception('Body is empty');
        }

        return $this;
    }


    /**
     * @param string $uri
     * @param string $method
     * @param array|string|null $body
     * @param array $headers
     * @return object
     * @throws Exception
     */
    protected function doRequest(
        $uri,
        $method = self::SERVICE_REQUEST_METHOD_POST,
        $body = null,
        array $headers = []
    ) {
        if (is_null(self::$ch)) {
            self::$ch = curl_init();
            // throw new Exception('CUrl-resource is null');
        }

        // restart ch
        self::$countPing++;
        if (self::$countPing > self::MAX_PING_NUMBER) {
            self::$countPing = 0;
            curl_close(self::$ch);
            self::$ch = curl_init();
        }


        if (is_array($body)) {
            $body = http_build_query($body);
        }

        if (is_array($body)) {
            throw new Exception('body must be string');
        }

        if (Helper::is_protocol($uri)) {
            $url = $uri;
        } else {
            $url = self::SERVICE_BASE_URL . '/' . $uri;
        }

        if ($method == self::SERVICE_REQUEST_METHOD_GET) {
            $url .= '?' . $body;
        }

        $headers = Arr::merge($this->getDefaultHeaders(), $headers);

        curl_setopt_array(
            self::$ch,
            [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_SSL_VERIFYPEER => 0,
                // CURLOPT_SSLCERT => ABSPATH . WPINC . self::SERVICE_REQUEST_SSL_CERT,
                // CURLOPT_SSLCERTPASSWD => self::SERVICE_REQUEST_SSL_CERTPASSWD,
                // CURLOPT_SSLCERTTYPE => 'PEM',
                CURLOPT_HTTPHEADER => $headers,
            ]
        );

        switch ($method) {
            case self::SERVICE_REQUEST_METHOD_POST:
                curl_setopt(self::$ch, CURLOPT_CUSTOMREQUEST, self::SERVICE_REQUEST_METHOD_POST);
                curl_setopt(self::$ch, CURLOPT_POST, true);
                curl_setopt(self::$ch, CURLOPT_POSTFIELDS, $body);
                break;
            case self::SERVICE_REQUEST_METHOD_PUT:
                curl_setopt(self::$ch, CURLOPT_CUSTOMREQUEST, self::SERVICE_REQUEST_METHOD_PUT);
                curl_setopt(self::$ch, CURLOPT_POSTFIELDS, $body);
                break;
            case self::SERVICE_REQUEST_METHOD_DELETE:
                curl_setopt(self::$ch, CURLOPT_CUSTOMREQUEST, self::SERVICE_REQUEST_METHOD_DELETE);
                break;
            default: // case self::SERVICE_REQUEST_METHOD_GET:
                curl_setopt(self::$ch, CURLOPT_POST, false);
                curl_setopt(self::$ch, CURLOPT_CUSTOMREQUEST, self::SERVICE_REQUEST_METHOD_GET);
        }

        $result = curl_exec(self::$ch);
        $httpStatus = curl_getinfo(self::$ch, CURLINFO_HTTP_CODE);
        $httpHeaders = curl_getinfo(self::$ch);

        $err = curl_error(self::$ch);
        if ($err) {
            throw new Exception($err, $httpStatus ?: 520);
        }

        if (PHP_SAPI == 'cli') {
            self::$__request_debug = [func_get_args(), $httpStatus, $result];
        }

        return (object)[
            'code' => $httpStatus,
            'headers' => $httpHeaders,
            'body' => $result,
        ];
    }

    /**
     * @return array
     */
    protected function getDefaultHeaders()
    {
        return [
            'Content-Type: application/x-www-form-urlencoded',
        ];
    }

    /**
     * @return array
     */
    protected function getAuth()
    {
        return [
            'username' => apply_filters('get_custom_option', 'f3dweb', NALPEIRON_AUTH_USERNAME),
            'password' => apply_filters('get_custom_option', '', NALPEIRON_AUTH_PASSWORD),
            'customerid' => apply_filters('get_custom_option', '3601', NALPEIRON_AUTH_CUSTOMERID),
        ];
    }

    /**
     * @param array $data
     * @return $this
     * @throws Exception
     */
    protected function isValidData(array $data)
    {
        foreach ($this->required as $key) {
            if (!isset($data[$key])) {
                throw new Exception($key . ' is empty');
            }
        }

        return $this;
    }

    /**
     * @unused
     *
     * @param $uri
     * @param array $args
     * @param array $headers
     * @return object
     */
    protected function getJsonRequest($uri, array $args = [], array $headers = [])
    {
        $httpQuery = http_build_query($args);
        $response = $this->doRequest(
            $uri,
            self::SERVICE_REQUEST_METHOD_GET,
            $httpQuery,
            $headers
        );
        if (!empty($response->body)) {
            $response->body = json_decode($response->body);
        }

        return $response;
    }


}