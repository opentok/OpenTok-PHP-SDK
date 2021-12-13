<?php

namespace OpenTok\Exception;

/**
 * Defines the exception thrown when you use an invalid API or secret and call an archiving method.
 */
class ArchiveAuthenticationException extends AuthenticationException implements ArchiveException
{
}
