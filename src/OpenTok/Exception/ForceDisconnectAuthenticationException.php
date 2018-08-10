<?php

namespace OpenTok\Exception;

/**
 * Defines the exception thrown when you use an invalid API or secret and call the force disconnect method.
 */
class ForceDisconnectAuthenticationException extends \OpenTok\Exception\AuthenticationException implements \OpenTok\Exception\ForceDisconnectException
{
}
