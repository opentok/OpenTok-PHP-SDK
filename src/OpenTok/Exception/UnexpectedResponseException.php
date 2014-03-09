<?php

namespace OpenTok\Exception;

class UnexpectedResponseException extends \UnexpectedValueException implements Exception
{
    protected $response;

    public function __construct($message = '', $code = 500, Exception $previous, $response)
    {
        parent::__construct($message, $code, $previous);

        if (isset($response)) $this->response = $response;
    }

    public function __toString(){
        return parent::__toString() . ' Response: '.$this->response;
    }
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
