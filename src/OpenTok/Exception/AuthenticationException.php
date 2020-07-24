<?php

namespace OpenTok\Exception;

// TODO: this kind of exception is more meaningful if we pass in the apiKey and apiSecret
/**
 * Defines the exception thrown when you use an invalid API or secret.
 */
class AuthenticationException extends DomainException implements Exception
{
  /** @ignore */
    public function __construct($apiKey, $apiSecret, $code = 0, $previous)
    {
        $message = 'The OpenTok API credentials were rejected. apiKey=' . $apiKey . ', apiSecret=' . $apiSecret;
        parent::__construct($message, $code, $previous);
    }
}
