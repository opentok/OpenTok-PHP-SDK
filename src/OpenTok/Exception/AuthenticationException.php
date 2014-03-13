<?php

namespace OpenTok\Exception;

// TODO: this kind of exception is more meaningful if we pass in the apiKey and apiSecret
class AuthenticationException extends OpenTok\DomainException implements \OpenTok\Exception\Exception
{
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
