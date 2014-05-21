<?php

namespace OpenTok\Exception;

// TODO: this kind of exception has a detailed message from the HTTP response, use it.
/**
* Defines an exception thrown when a call to an archiving method results in an error response from
* the server.
*/
class ArchiveDomainException extends \OpenTok\Exception\DomainException implements \OpenTok\Exception\ArchiveException
{
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
