<?php

namespace OpenTok;

use OpenTok\Util\BasicEnum;

/**
 * Defines values for the role parameter of the \OpenTok\OpenTok->generateToken()
 * method.
 */
abstract class Role extends BasicEnum
{
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
    * In addition to the privileges granted to a publisher, a moderator can perform
    * moderation functions, such as forcing clients to disconnect, to stop publishing streams,
    * or to mute audio in published streams.
    *
    * See the
    * <a href="https://tokbox.com/developer/guides/moderation/">Moderation developer guide</a>.
    */
    const MODERATOR = 'moderator';
}
