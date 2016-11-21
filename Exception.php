<?php
namespace Nalpeiron;

class Exception extends \Exception
{

    public function setCode($code)
    {
        $this->code = $code;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @param string $class
     * @return \Exception
     */
    public function getSpanException($class = 'nalpeiron-error')
    {
        return new \Exception("<span class='{$class}'>{$this->message}</span>", $this->getCode());
    }

}