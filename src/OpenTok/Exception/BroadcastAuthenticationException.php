<?php

namespace OpenTok\Exception;

/**
 * Defines the exception thrown when you use an invalid API or secret and call a broadcast method.
 */
class BroadcastAuthenticationException extends \OpenTok\Exception\AuthenticationException implements \OpenTok\Exception\BroadcastException
{
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
