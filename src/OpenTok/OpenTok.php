<?php

/**
* OpenTok PHP Library
* http://www.tokbox.com/
*
* Copyright (c) 2011, TokBox, Inc.
* Permission is hereby granted, free of charge, to any person obtaining
* a copy of this software and associated documentation files (the "Software"),
* to deal in the Software without restriction, including without limitation
* the rights to use, copy, modify, merge, publish, distribute, sublicense,
* and/or sell copies of the Software, and to permit persons to whom the
* Software is furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included
* in all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
* OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
* THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*/

namespace OpenTok;

use OpenTok\Session;
use OpenTok\Archive;
use OpenTok\Role;
use OpenTok\Util\Client;
use OpenTok\Util\Validators;

use OpenTok\Exception\UnexpectedValueException;

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
     * The following example shows how to obtain a token that has a role of "subscriber" and
     * that has a connection metadata string:
     * <p>
     * For testing, you can also use the <a href="https://dashboard.tokbox.com/projects">OpenTok
     * dashboard</a> page to generate test tokens.
     *
     * @param string $sessionId The session ID corresponding to the session to which the user
     * will connect.
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
    public function generateToken($sessionId, $options = array())
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'role' => Role::PUBLISHER,
            'expireTime' => null,
            'data' => null
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($role, $expireTime, $data) = array_values($options);

        // additional token data
        $createTime = time();
        $nonce = microtime(true) . mt_rand();

        // validate arguments
        Validators::validateSessionIdBelongsToKey($sessionId, $this->apiKey);
        Validators::validateRole($role);
        Validators::validateExpireTime($expireTime, $createTime);
        Validators::validateData($data);

        $dataString = "session_id=$sessionId&create_time=$createTime&role=$role&nonce=$nonce" .
            (($expireTime) ? "&expire_time=$expireTime" : '') .
            (($data) ? "&connection_data=" . urlencode($data) : '');
        $sig = $this->_sign_string($dataString, $this->apiSecret);

        return "T1==" . base64_encode("partner_id=$this->apiKey&sig=$sig:$dataString");
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
    *     <li><code>'p2p'</code> (boolean) &mdash; Whether the session's streams will be transmitted
    * directly between peers (true) or using the OpenTok Media Router (false). By default,
    * sessions use the OpenTok Media Router.
    * <p>
    * The <a href="http://www.tokbox.com/blog/mantis-next-generation-cloud-technology-for-webrtc/">
    * OpenTok Media Router</a> provides benefits not available in peer-to-peer sessions.
    * For example, the OpenTok Media Router can decrease bandwidth usage in multiparty sessions.
    * Also, the OpenTok Media Router can improve the quality of the user experience through
    * <a href="http://www.tokbox.com/blog/quality-of-experience-and-traffic-shaping-the-next-step-with-mantis/">
    * dynamic traffic shaping</a>.
    * <p>
    * For peer-to-peer sessions, the session will attempt to transmit streams directly
    * between clients. If clients cannot connect due to firewall restrictions, the session uses
    * the OpenTok TURN server to relay audio-video streams.
    * <p>
    * You will be billed for streamed minutes if you use the OpenTok Media Router or if the
    * peer-to-peer session uses the OpenTok TURN server to relay streams. For information on
    * pricing, see the <a href="http://www.tokbox.com/pricing">OpenTok pricing page</a>.
    *
    *    <li><code>'location'</code> &mdash; n IP address that the OpenTok servers will use to
    * situate the session in its global network. If you do not set a location hint,
    * the OpenTok servers will be based on the first client connecting to the session.</li>
    *
    * </ul>
    *
    * @return string A session ID for the new session. For example, when using the OpenTok.js
    * library, use this session ID when calling the <code>OT.initSession()</code> method.
    */
    public function createSession($options=array())
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array('p2p' => null, 'location' => null);
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($p2p, $location) = array_values($options);

        // validate arguments
        Validators::validateP2p($p2p);
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
            'p2p' => $p2p
        ));
    }

    /**
     * Starts archiving an OpenTok 2.0 session.
     * <p>
     * Clients must be actively connected to the OpenTok session for you to successfully start
     * recording an archive.
     * <p>
     * You can only record one archive at a time for a given session. You can only record archives
     * of OpenTok server-enabled sessions; you cannot archive peer-to-peer sessions.
     *
     * @param String $sessionId The session ID of the OpenTok session to archive.
     * @param String $name The name of the archive. You can use this name to identify the archive.
     * It is a property of the Archive object, and it is a property of archive-related events in the
     * OpenTok JavaScript SDK.
     * @return Archive The Archive object, which includes properties defining the archive, including
     * the archive ID.
     */
    public function startArchive($sessionId, $name=null)
    {
        // validate arguments
        Validators::validateSessionId($sessionId);
        Validators::validateArchiveName($name);

        $params = array( 'sessionId' => $sessionId );
        if ($name) { $params['name'] = $name; }

        // make API call
        $archiveJson = $this->client->startArchive($params);

        return new Archive($archiveJson, array( 'client' => $this->client ));
    }

    /**
     * Stops an OpenTok archive that is being recorded.
     * <p>
     * Archives automatically stop recording after 90 minutes or when all clients have disconnected
     * from the session being archived.
     *
     * @param String $archiveId The archive ID of the archive you want to stop recording.
     * @return Archive The Archive object corresponding to the archive being stopped.
     */
    public function stopArchive($archiveId)
    {
        Validators::validateArchiveId($archiveId);

        $archiveJson = $this->client->stopArchive($archiveId);
        return new Archive($archiveJson, array( 'client' => $this->client ));
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

        $archiveJson = $this->client->getArchive($archiveId);
        return new Archive($archiveJson, array( 'client' => $this->client ));
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

        $archiveListJson = $this->client->listArchives($offset, $count);
        return new ArchiveList($archiveListJson, array( 'client' => $this->client ));
    }

    /** @internal */
    private function _sign_string($string, $secret)
    {
        return hash_hmac("sha1", $string, $secret);
    }
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
