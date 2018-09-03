<?php

namespace OpenTok\Exception;

/**
* Defines an exception thrown when a call to a signal method results in an error due to an
* unexpected value.
*/
class SignalUnexpectedValueException extends \OpenTok\Exception\UnexpectedValueException implements \OpenTok\Exception\SignalException
{
  public function __construct($message, $code)
  {
      parent::__construct($message, $code);
  }
}
