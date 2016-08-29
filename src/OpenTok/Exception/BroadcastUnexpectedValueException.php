<?php

namespace OpenTok\Exception;

/**
* Defines an exception thrown when a call to a broadcast method results in an error due to an
* unexpected value.
*/
class BroadcastUnexpectedValueException extends \OpenTok\Exception\UnexpectedValueException implements \OpenTok\Exception\BroadcastException
{
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
