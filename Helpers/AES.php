<?php

namespace Nalpeiron\Helpers;

use \Exception;

// use \Nalpeiron\Helpers\Base32;

/**
 * Class AES
 * @package Nalpeiron\Helpers
 * @see http://aesencryption.net/
 */
class AES
{
    const M_CBC = 'cbc';
    const M_CFB = 'cfb';
    const M_ECB = 'ecb';
    const M_NOFB = 'nofb';
    const M_OFB = 'ofb';
    const M_STREAM = 'stream';

    //const KEY = 'gipSies96arrOyos63gilt55sheS94somerSet26adviseeS';
    const KEY = 'gipSies96arrOyos';

    protected $key;
    protected $cipher;
    protected $data;
    protected $mode;
    protected $IV;

    /**
     * @param string $data
     * @param string $key
     * @param int $blockSize
     * @param string $mode
     */
    function __construct($data = null, $key = self::KEY, $blockSize = 128, $mode = self::M_ECB)
    {
        $key = substr($key, 0, 32); // gipSies96arrOyos63gilt55sheS94so
        $this->setData($data);
        $this->setKey($key);
        $this->setBlockSize($blockSize);
        $this->setMode($mode);
        $this->setIV("");
    }

    /**
     *
     * @param type $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     *
     * @param type $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     *
     * @param type $blockSize
     */
    public function setBlockSize($blockSize)
    {
        switch ($blockSize) {
            case 128:
                $this->cipher = MCRYPT_RIJNDAEL_128;
                break;

            case 192:
                $this->cipher = MCRYPT_RIJNDAEL_192;
                break;

            case 256:
                $this->cipher = MCRYPT_RIJNDAEL_256;
                break;
        }
    }

    /**
     *
     * @param type $mode
     */
    public function setMode($mode)
    {
        switch ($mode) {
            case AES::M_CBC:
                $this->mode = MCRYPT_MODE_CBC;
                break;
            case AES::M_CFB:
                $this->mode = MCRYPT_MODE_CFB;
                break;
            case AES::M_ECB:
                $this->mode = MCRYPT_MODE_ECB;
                break;
            case AES::M_NOFB:
                $this->mode = MCRYPT_MODE_NOFB;
                break;
            case AES::M_OFB:
                $this->mode = MCRYPT_MODE_OFB;
                break;
            case AES::M_STREAM:
                $this->mode = MCRYPT_MODE_STREAM;
                break;
            default:
                $this->mode = MCRYPT_MODE_ECB;
                break;
        }
    }

    /**
     *
     * @return boolean
     */
    public function validateParams()
    {
        if ($this->data != null &&
            $this->key != null &&
            $this->cipher != null
        ) {
            return true;
        } else {
            return false;
        }
    }

    public function setIV($IV)
    {
        $this->IV = $IV;
    }

    protected function getIV()
    {
        if ($this->IV == "") {
            $this->IV = mcrypt_create_iv(mcrypt_get_iv_size($this->cipher, $this->mode), MCRYPT_RAND);
        }

        return $this->IV;
    }

    /**
     * @return type
     * @throws Exception
     */
    public function encrypt_original()
    {
        if ($this->validateParams()) {
            $en = mcrypt_encrypt($this->cipher, $this->key, $this->data, $this->mode, $this->getIV());

//            file_put_contents(__DIR__ . '/h.data', $en); // todo test
            return trim(base64_encode($en)); // original
//            return trim(base64_encode(mcrypt_encrypt($this->cipher, $this->key, $this->data, $this->mode, $this->getIV()))); // original
//            $base = new Base32('custom');
//            return trim($base->base32_encode($en));
        } else {
            throw new Exception('Invalid params!');
        }
    }

    public function encrypt()
    {
        if ($this->validateParams()) {
            $blockSize = mcrypt_get_block_size($this->cipher, $this->mode);
            $pad = $blockSize - (strlen($this->data) % $blockSize);
            $en = mcrypt_encrypt($this->cipher, $this->key, $this->data . str_repeat(chr($pad), $pad), $this->mode, $this->getIV());

            return trim(base64_encode($en)); // original
        } else {
            throw new Exception('Invalid params!');
        }
    }

    /**
     *
     * @return type
     * @throws Exception
     */
    public function decrypt()
    {
        if ($this->validateParams()) {
//            return trim(mcrypt_decrypt($this->cipher, $this->key, base64_decode($this->data), $this->mode, $this->getIV())); // original
            return trim(mcrypt_decrypt($this->cipher, $this->key, base64_decode($this->data), $this->mode, $this->getIV()), " \t\n\r\0\x0B" . chr(14) . chr(15));

//            $base = new Base32('custom');
//            return trim(mcrypt_decrypt($this->cipher, $this->key, $base->base32_decode($this->data), $this->mode, $this->getIV()));
        } else {
            throw new Exception('Invlid params!');
        }
    }

}