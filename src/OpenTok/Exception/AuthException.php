<?php

namespace OpenTok\Exception;

class AuthException extends \RuntimeException implements Exception
{
    protected $credentials;

    public function __construct($message = '', $code = 400, Exception $previous, $credentials)
    {
        parent::__construct($message, $code, $previous);

        if (isset($credentials)) $this->credentials = $credentials;
    }

    public function __toString(){
        return parent::__toString() . ' Response: '.(string)$this->credentials;
    }
}
