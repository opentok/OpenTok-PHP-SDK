<?php

namespace OpenTok\Exception;

/**
* Defines an exception thrown when a call to an archiving method results in an error due to an
* unexpected value.
*/
class ArchiveUnexpectedValueException extends UnexpectedValueException implements ArchiveException
{
}
