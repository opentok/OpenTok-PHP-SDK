<?php

namespace OpenTok\Exception;

/**
* Defines an exception thrown when a call to a signal method results in no
* response from the server
*/
class SignalNetworkConnectionException extends \RuntimeException implements SignalException
{

}
