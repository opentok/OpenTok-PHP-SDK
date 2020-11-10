<?php

namespace OpenTok\Exception;

/**
* Defines an exception thrown when a call to a force disconnect method results in an error response from
* the server.
*/
class ForceDisconnectConnectionException extends DomainException implements ForceDisconnectException
{
    /** @ignore */
    public function __construct($message, $code)
    {
        parent::__construct($message, $code);
    }
}
