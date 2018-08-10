<?php

namespace OpenTok;

use OpenTok\OpenTok;
use OpenTok\MediaMode;
use OpenTok\ArchiveMode;
use OpenTok\Util\Validators;

/**
* Represents an OpenTok session.
* <p>
* Use the \OpenTok\OpenTok->createSession() method to create an OpenTok session. Use the
* getSessionId() method of the Session object to get the session ID.
*/
class Session
{
    /**
     * @internal
     */
    protected $sessionId;
    /**
     * @internal
     */
    protected $location;
    /**
     * @internal
     */
    protected $mediaMode;
    /**
     * @internal
     */
    protected $archiveMode;
    /**
     * @internal
     */
    protected $opentok;

    /**
     * @internal
     */
    function __construct($opentok, $sessionId, $properties = array())
    {
        // unpack arguments
        $defaults = array('mediaMode' => MediaMode::ROUTED, 'archiveMode' => ArchiveMode::MANUAL, 'location' => null);
        $properties = array_merge($defaults, array_intersect_key($properties, $defaults));
        list($mediaMode, $archiveMode, $location) = array_values($properties);

        Validators::validateOpenTok($opentok);
        Validators::validateSessionId($sessionId);
        Validators::validateLocation($location);
        Validators::validateMediaMode($mediaMode);
        Validators::validateArchiveMode($archiveMode);

        $this->opentok = $opentok;
        $this->sessionId = $sessionId;
        $this->location = $location;
        $this->mediaMode = $mediaMode;
        $this->archiveMode = $archiveMode;

    }

    /**
    * Returns the session ID, which uniquely identifies the session.
    */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
    * Returns the location hint IP address.
    *
    * See <a href="OpenTok.OpenTok.html#method_createSession">OpenTok->createSession()</a>.
    */
    public function getLocation()
    {
        return $this->location;
    }

    /**
    * Returns MediaMode::RELAYED if the session's streams will be transmitted directly between
    * peers; returns MediaMode::ROUTED if the session's streams will be transmitted using the
    * OpenTok Media Router.
    *
    * See <a href="OpenTok.OpenTok.html#method_createSession">OpenTok->createSession()</a>
    * and <a href="OpenTok.MediaMode.html">ArchiveMode</a>.
    */
    public function getMediaMode()
    {
        return $this->mediaMode;
    }

    /**
    * Defines whether the session is automatically archived (ArchiveMode::ALWAYS)
    * or not (ArchiveMode::MANUAL).
    *
    * See <a href="OpenTok.OpenTok.html#method_createSession">OpenTok->createSession()</a>
    * and <a href="OpenTok.ArchiveMode.html">ArchiveMode</a>.
    */
    public function getArchiveMode()
    {
        return $this->archiveMode;
    }

    /**
     * @internal
     */
    public function __toString()
    {
        return $this->sessionId;
    }

    /**
     * Creates a token for connecting to the session. In order to authenticate a user,
     * the client passes a token when connecting to the session.
     * <p>
     * For testing, you can also generate tokens or by logging in to your
     * <a href="https://tokbox.com/account">TokBox account</a>.
     *
     * @param array $options This array defines options for the token. This array include the
     * following keys, all of which are optional:
     *
     * <ul>
     *
     *    <li><code>'role'</code> (string) &mdash; One of the constants defined in the RoleConstants
     *    class. The default role is publisher</li>
     *
     *    <li><code>'expireTime'</code> (int) &mdash; The timestamp for when the token expires,
     *    in milliseconds since the Unix epoch. The default expiration time is 24 hours
     *    after the token creation time. The maximum expiration time is 30 days after the
     *    token creation time.</li>
     *
     *    <li><code>'data'</code> (string) &mdash; A string containing connection metadata
     *    describing the end-user. For example, you can pass the user ID, name, or other data
     *    describing the end-user. The length of the string is limited to 1000 characters.
     *    This data cannot be updated once it is set.</li>
     *
     * </ul>
     *
     * @return string The token string.
     */
    public function generateToken($options = array())
    {
        return $this->opentok->generateToken($this->sessionId, $options);
    }
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
