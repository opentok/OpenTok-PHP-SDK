<?php

namespace OpenTok\Exception;

// TODO: this kind of exception has a detailed message from the HTTP response, use it.
/**
* Defines an exception thrown when a call to a signal method results in an error response from
* the server.
*/
class SignalDomainException extends \OpenTok\Exception\DomainException implements \OpenTok\Exception\SignalException
{
}
