<?php

namespace OpenTok\Exception;

/**
* Defines an exception thrown when a call to a broadcast method results in an error due to an
* unexpected value.
*/
class BroadcastUnexpectedValueException extends UnexpectedValueException implements BroadcastException
{
}
