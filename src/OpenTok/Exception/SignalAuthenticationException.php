<?php

namespace OpenTok\Exception;

/**
 * Defines the exception thrown when you use an invalid API or secret and call a signal method.
 */
class SignalAuthenticationException extends \OpenTok\Exception\AuthenticationException implements \OpenTok\Exception\SignalException
{
}
