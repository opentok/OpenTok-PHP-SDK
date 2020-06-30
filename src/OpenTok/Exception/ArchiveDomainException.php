<?php

namespace OpenTok\Exception;

// TODO: this kind of exception has a detailed message from the HTTP response, use it.
/**
* Defines an exception thrown when a call to an archiving method results in an error response from
* the server.
*/
class ArchiveDomainException extends DomainException implements ArchiveException
{
}
