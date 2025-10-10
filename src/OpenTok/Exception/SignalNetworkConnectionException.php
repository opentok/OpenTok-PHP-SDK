<?php

namespace OpenTok\Exception;

use RuntimeException;

/**
* Defines an exception thrown when a call to a signal method results in no
* response from the server
*/
class SignalNetworkConnectionException extends RuntimeException implements SignalException
{
}
