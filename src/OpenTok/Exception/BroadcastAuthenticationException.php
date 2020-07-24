<?php

namespace OpenTok\Exception;

/**
 * Defines the exception thrown when you use an invalid API or secret and call a broadcast method.
 */
class BroadcastAuthenticationException extends AuthenticationException implements BroadcastException
{
}
