<?php

namespace OpenTok;

use \Firebase\JWT\JWT;

use OpenTok\Session;
use OpenTok\Archive;
use OpenTok\Role;
use OpenTok\MediaMode;
use OpenTok\ArchiveMode;
use OpenTok\OutputMode;
use OpenTok\Util\Client;
use OpenTok\Util\Validators;

use OpenTok\Exception\UnexpectedValueException;
use OpenTok\Exception\InvalidArgumentException;

/**
* Contains methods for creating OpenTok sessions, generating tokens, and working with archives.
* <p>
* To create a new OpenTok object, call the OpenTok() constructor with your OpenTok API key
* and the API secret from <a href="https://dashboard.tokbox.com">the OpenTok dashboard</a>. Do not
* publicly share your API secret. You will use it with the OpenTok() constructor (only on your web
* server) to create OpenTok sessions.
* <p>
* Be sure to include the entire OpenTok server SDK on your web server.
*/
class OpenTok {

    /** @internal */
    private $apiKey;
    /** @internal */
    private $apiSecret;
    /** @internal */
    private $client;

    /** @internal */
    public function __construct($apiKey, $apiSecret, $options = array())
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array('apiUrl' => 'https://api.opentok.com', 'client' => null);
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($apiUrl, $client) = array_values($options);

        // validate arguments
        Validators::validateApiKey($apiKey);
        Validators::validateApiSecret($apiSecret);
        Validators::validateApiUrl($apiUrl);
        Validators::validateClient($client);

        $this->client = isset($client) ? $client : new Client();
        $this->client->configure($apiKey, $apiSecret, $apiUrl);
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    /**
     * Creates a token for connecting to an OpenTok session. In order to authenticate a user
     * connecting to an OpenTok session, the client passes a token when connecting to the session.
     * <p>
     * For testing, you can also use the <a href="https://dashboard.tokbox.com/projects">OpenTok
     * dashboard</a> page to generate test tokens.
     *
     * @param string $sessionId The session ID corresponding to the session to which the user
     * will connect.
     *
     * @param array $options This array defines options for the token. This array includes the
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
    public function generateToken($sessionId, $options = array())
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'ist' => 'project',
            'iss' => $this->apiKey,
            'sub' => $sessionId,
            'iat' => time(),
            'exp' => time()+(24 * 60 * 60),
            'jti' => (float) rand() / (float) getrandmax(),
            'role' => Role::PUBLISHER,
            'scope' => 'session.connect',
            'connectionData' => null,
            'initialLayoutClassList' => null
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        $role = $options['role'];
        $exp = $options['exp'];
        $connectionData = $options['connectionData'];

        // additional token data
        $createTime = time();
        $nonce = microtime(true) . mt_rand();

        // validate arguments
        Validators::validateSessionIdBelongsToKey($sessionId, $this->apiKey);
        Validators::validateRole($role);
        Validators::validateExpireTime($exp, $createTime);
        Validators::validateData($connectionData);

        return JWT::encode($options, $this->apiSecret);
    }

    /**
    * Creates a new OpenTok session and returns the session ID, which uniquely identifies
    * the session.
    * <p>
    * For example, when using the OpenTok JavaScript library, use the session ID when calling the
    * <a href="http://tokbox.com/opentok/libraries/client/js/reference/OT.html#initSession">
    * OT.initSession()</a> method (to initialize an OpenTok session).
    * <p>
    * OpenTok sessions do not expire. However, authentication tokens do expire (see the
    * generateToken() method). Also note that sessions cannot explicitly be destroyed.
    * <p>
    * A session ID string can be up to 255 characters long.
    * <p>
    * Calling this method results in an OpenTokException in the event of an error.
    * Check the error message for details.
    * <p>
    * You can also create a session using the
    * <a href="http://www.tokbox.com/opentok/api/#session_id_production">OpenTok
    * REST API</a> or the <a href="https://dashboard.tokbox.com/projects">OpenTok dashboard</a>.
    *
    * @param array $options (Optional) This array defines options for the session. The array includes
    * the following keys (all of which are optional):
    *
    * <ul>
    *
    *    <li><code>'archiveMode'</code> (ArchiveMode) &mdash; Whether the session is automatically
    *    archived (<code>ArchiveMode::ALWAYS</code>) or not (<code>ArchiveMode::MANUAL</code>).
    *    By default, the setting is <code>ArchiveMode.MANUAL</code>, and you must call the
    *    <code>OpenTok->startArchive()</code> method to start archiving. To archive the session
    *    (either automatically or not), you must set the <code>mediaMode</code> key to
    *    <code>MediaMode::ROUTED</code>.</li>
    *
    *    <li><code>'location'</code> (String) &mdash; An IP address that the OpenTok servers
    *    will use to situate the session in its global network. If you do not set a location hint,
    *    the OpenTok servers will be based on the first client connecting to the session.</li>
    *
    *     <li><code>'mediaMode'</code> (MediaMode) &mdash; Whether the session will transmit
    *     streams using the OpenTok Media Router (<code>MediaMode.ROUTED</code>) or not
    *     (<code>MediaMode.RELAYED</code>). By default, the <code>mediaMode</code> property
    *     is set to <code>MediaMode.RELAYED</code>.
    *
    *     <p>
    *     With the <code>mediaMode</code> parameter set to <code>MediaMode.RELAYED</code>, the
    *     session will attempt to transmit streams directly between clients. If clients cannot
    *     connect due to firewall restrictions, the session uses the OpenTok TURN server to relay
    *     audio-video streams.
    *
    *     <p>
    *     The
    *     <a href="https://tokbox.com/opentok/tutorials/create-session/#media-mode" target="_top">
    *     OpenTok Media Router</a> provides the following benefits:
    *
    *     <ul>
    *       <li>The OpenTok Media Router can decrease bandwidth usage in multiparty sessions.
    *           (When the <code>mediaMode</code> parameter is set to <code>MediaMode.ROUTED</code>,
    *           each client must send a separate audio-video stream to each client subscribing to
    *           it.)</li>
    *       <li>The OpenTok Media Router can improve the quality of the user experience through
    *         recovery</a>. With these features, if a client's connectivity degrades to a degree
    *         that it does not support video for a stream it's subscribing to, the video is dropped
    *         on that client (without affecting other clients), and the client receives audio only.
    *         If the client's connectivity improves, the video returns.</li>
    *       <li>The OpenTok Media Router supports the
    *         <a href="https://tokbox.com/opentok/tutorials/archiving" target="_top">archiving</a>
    *         feature, which lets you record, save, and retrieve OpenTok sessions.</li>
    *     </ul>
    *
    * </ul>
    *
    * @return \OpenTok\Session A Session object representing the new session. Call the
    * <code>getSessionId()</code> method of this object to get the session ID. For example,
    * when using the OpenTok.js library, use this session ID when calling the
    * <code>OT.initSession()</code> method.
    */
    public function createSession($options=array())
    {
        if (array_key_exists('archiveMode', $options) &&
            $options['archiveMode'] != ArchiveMode::MANUAL) {

            if (array_key_exists('mediaMode', $options) &&
                $options['mediaMode'] != MediaMode::ROUTED) {

                throw new InvalidArgumentException('A session must be routed to be archived.');
            } else {
              $options['mediaMode'] = MediaMode::ROUTED;
            }
        }

        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'mediaMode' => MediaMode::RELAYED,
            'archiveMode' => ArchiveMode::MANUAL,
            'location' => null
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($mediaMode, $archiveMode, $location) = array_values($options);

        // validate arguments
        Validators::validateMediaMode($mediaMode);
        Validators::validateArchiveMode($archiveMode);
        Validators::validateLocation($location);

        // make API call
        $sessionXml = $this->client->createSession($options);

        // check response
        $sessionId = $sessionXml->Session->session_id;
        if (!$sessionId) {
            $errorMessage = 'Failed to create a session. Server response: '. (string)$sessionXml;
            throw new UnexpectedValueException($errorMessage);
        }

        return new Session($this, (string)$sessionId, array(
            'location' => $location,
            'mediaMode' => $mediaMode,
            'archiveMode' => $archiveMode
        ));
    }

    /**
     * Starts archiving an OpenTok session.
     * <p>
     * Clients must be actively connected to the OpenTok session for you to successfully start
     * recording an archive.
     * <p>
     * You can only record one archive at a time for a given session. You can only record archives
     * of sessions that use the OpenTok Media Router (sessions with the
     * <a href="http://tokbox.com/opentok/tutorials/create-session/#media-mode">media mode</a>
     * set to routed); you cannot archive sessions with the media mode set to relayed.
     * <p>
     * For more information on archiving, see the
     * <a href="https://tokbox.com/opentok/tutorials/archiving/">OpenTok archiving</a> programming
     * guide.
     *
     * @param String $sessionId The session ID of the OpenTok session to archive.
     * @param array $options (Optional) This array defines options for the archive. The array
     * includes the following keys (all of which are optional):
     *
     * <ul>
     *
     *    <li><code>'name'</code> (String) &mdash; The name of the archive. You can use this name to
     *    identify the archive. It is a property of the Archive object, and it is a property of
     *    archive-related events in the OpenTok client SDKs.</li>
     *
     *    <li><code>'hasVideo'</code> (Boolean) &mdash; Whether the archive will record video
     *    (true, the default) or not (false). If you set both <code>hasAudio</code> and
     *    <code>hasVideo</code> to false, the call to the <code>startArchive()</code> method results
     *    in an error.</li>
     *
     *    <li><code>'hasAudio'</code> (Boolean) &mdash; Whether the archive will record audio
     *    (true, the default) or not (false). If you set both <code>hasAudio</code> and
     *    <code>hasVideo</code> to false, the call to the <code>startArchive()</code> method results
     *    in an error.</li>
     *
     *    <li><code>'outputMode'</code> (OutputMode) &mdash; Whether all streams in the
     *    archive are recorded to a single file (<code>OutputMode::COMPOSED</code>, the default)
     *    or to individual files (<code>OutputMode::INDIVIDUAL</code>).</li>
     *
     * <ul>
     *
     * @return Archive The Archive object, which includes properties defining the archive, including
     * the archive ID.
     */
    public function startArchive($sessionId, $options=array())
    {
        // support for deprecated method signature, remove in v3.0.0 (not before)
        if (!is_array($options)) {
          $options = array('name' => $options);
        }

        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'name' => null,
            'hasVideo' => true,
            'hasAudio' => true,
            'outputMode' => OutputMode::COMPOSED
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($name, $hasVideo, $hasAudio, $outputMode) = array_values($options);

        // validate arguments
        Validators::validateSessionId($sessionId);
        Validators::validateArchiveName($name);
        Validators::validateArchiveHasVideo($hasVideo);
        Validators::validateArchiveHasAudio($hasAudio);
        Validators::validateArchiveOutputMode($outputMode);

        // make API call
        $archiveData = $this->client->startArchive($sessionId, $options);

        return new Archive($archiveData, array( 'client' => $this->client ));
    }

    /**
     * Stops an OpenTok archive that is being recorded.
     * <p>
     * Archives automatically stop recording after 120 minutes or when all clients have disconnected
     * from the session being archived.
     *
     * @param String $archiveId The archive ID of the archive you want to stop recording.
     * @return Archive The Archive object corresponding to the archive being stopped.
     */
    public function stopArchive($archiveId)
    {
        Validators::validateArchiveId($archiveId);

        $archiveData = $this->client->stopArchive($archiveId);
        return new Archive($archiveData, array( 'client' => $this->client ));
    }

    /**
     * Gets an Archive object for the given archive ID.
     *
     * @param String $archiveId The archive ID.
     *
     * @throws ArchiveException There is no archive with the specified ID.
     * @throws OpenTokArgumentException The archive ID provided is null or an empty string.
     *
     * @return Archive The Archive object.
     */
    public function getArchive($archiveId)
    {
        Validators::validateArchiveId($archiveId);

        $archiveData = $this->client->getArchive($archiveId);
        return new Archive($archiveData, array( 'client' => $this->client ));
    }

    /**
     * Deletes an OpenTok archive.
     * <p>
     * You can only delete an archive which has a status of "available", "uploaded", or "deleted".
     * Deleting an archive removes its record from the list of archives. For an "available" archive,
     * it also removes the archive file, making it unavailable for download. For a "deleted"
     * archive, the archive remains deleted.
     *
     * @param String $archiveId The archive ID of the archive you want to delete.
     *
     * @return Boolean Returns true on success.
     *
     * @throws ArchiveException There archive status is not "available", "updated",
     * or "deleted".
     */
    public function deleteArchive($archiveId)
    {
        Validators::validateArchiveId($archiveId);

        return $this->client->deleteArchive($archiveId);
    }

    /**
     * Returns an ArchiveList. The <code>items()</code> method of this object returns a list of
     * archives that are completed and in-progress, for your API key.
     *
     * @param integer $offset Optional. The index offset of the first archive. 0 is offset of the
     * most recently started archive. 1 is the offset of the archive that started prior to the most
     * recent archive. If you do not specify an offset, 0 is used.
     * @param integer $count Optional. The number of archives to be returned. The maximum number of
     * archives returned is 1000.
     * @return ArchiveList An ArchiveList object. Call the items() method of the ArchiveList object
     * to return an array of Archive objects.
     */
    public function listArchives($offset=0, $count=null)
    {
        // validate params
        Validators::validateOffsetAndCount($offset, $count);

        $archiveListData = $this->client->listArchives($offset, $count);
        return new ArchiveList($archiveListData, array( 'client' => $this->client ));
    }

    /** @internal */
    private function _sign_string($string, $secret)
    {
        return hash_hmac("sha1", $string, $secret);
    }
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
