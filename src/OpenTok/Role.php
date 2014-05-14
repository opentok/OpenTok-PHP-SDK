<?php

namespace OpenTok;

use OpenTok\Util\BasicEnum;

/**
 * Defines values for the role parameter of the OpenTok.OpenTok.generateToken()
 * method.
 */
abstract class Role extends BasicEnum {
    /**
    *   A subscriber can only subscribe to streams.
    */
    const SUBSCRIBER = 'subscriber';
    /**
    * A publisher can publish streams, subscribe to streams, and signal. (This is the default
    * value if you do not set a role.)
    */
    const PUBLISHER = 'publisher';
    /**
    * In addition to the privileges granted to a publisher, in clients using the OpenTok.js 2.2
    * library, a moderator can call the <code>forceUnpublish()</code> and
    * <code>forceDisconnect()</code> method of the Session object.
    */
    const MODERATOR = 'moderator';
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
