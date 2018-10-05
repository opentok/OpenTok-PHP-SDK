<?php

namespace OpenTok\Exception;

/**
* Defines an exception thrown when a call to the force disconnect method results in an error due to an
* unexpected value.
*/
class ForceDisconnectUnexpectedValueException extends \OpenTok\Exception\UnexpectedValueException implements \OpenTok\Exception\ForceDisconnectException
{
  /** @ignore */

  public function __construct($message, $code)
  {
      parent::__construct($message, $code);
  }
}
