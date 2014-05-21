<?php

namespace OpenTok\Exception;

/**
 * Defines the exception thrown when you use an invalid API or secret and call an archiving method.
 */
class ArchiveAuthenticationException extends \OpenTok\Exception\AuthenticationException implements \OpenTok\Exception\ArchiveException
{
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
