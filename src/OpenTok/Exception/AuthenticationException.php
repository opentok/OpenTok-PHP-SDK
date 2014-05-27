<?php

namespace OpenTok\Exception;

// TODO: this kind of exception is more meaningful if we pass in the apiKey and apiSecret
/**
 * Defines the exception thrown when you use an invalid API or secret.
 */
class AuthenticationException extends OpenTok\Exception\DomainException implements \OpenTok\Exception\Exception
{
    public function __construct($apiKey, $apiSecret, $code = 0, $previous)
    {
        $message = 'The OpenTok API credentials were rejected. apiKey='.$apiKey.', apiSecret='.$apiSecret;
         parent::__construct($message, $code, $previous);
    }
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
