<?php

namespace OpenTok;

use OpenTok\Util\BasicEnum;

/**
 * Defines values for the mediaMode parameter of the OpenTok.OpenTok.generateSession()
 * method.
 */
abstract class MediaMode extends BasicEnum {
    /**
    *   The session will send streams using the OpenTok Media Router.
    */
    const ROUTED = 'disabled';
    /**
    *   The session will attempt send streams directly between clients. If clients cannot connect
    *   due to firewall restrictions, the session uses the OpenTok TURN server to relay streams.
    */
    const RELAYED = 'enabled';
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
