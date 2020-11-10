<?php

namespace OpenTok\Exception;

/**
* Defines an exception thrown when a call to a signal method results in an error due to an
* unexpected value.
*/
class SignalUnexpectedValueException extends UnexpectedValueException implements SignalException
{
    /** @ignore */
    public function __construct($message, $code)
    {
        parent::__construct($message, $code);
    }
}
