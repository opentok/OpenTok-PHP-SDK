OpenTok PHP SDK reference
=========================

You need to instantiate an OpenTokSDK object before calling any of its methods.
To create a new OpenTokSDK object, call the OpenTokSDK constructor with the API key
and the API secret TokBox issued you. (You get an API key when you
<a href="https://dashboard.tokbox.com/users/sign_in">sign up</a> for an OpenTok account.) Do not reveal
your API secret string. You use it with the OpenTokSDK constructor (only on your web
server) to create OpenTok sessions.

Set the API\_KEY and API\_SECRET values in the API_Config.php file:

    const API_KEY = "";
    const API_SECRET = "";

Then instantiate your SDK:

    // Creating an OpenTok Object
    $apiObj = new OpenTokSDK();


create_session() method
-----------------------
The `create_session()` method of the OpenTokSDK object to create a new OpenTok
session and obtain a session ID.

The `create_session()` method has the following parameters:

* `location` (String) &mdash; An IP address that TokBox will use to situate the session in its global network.
  In general, you should not pass in a location hint; if no location hint is passed in, the session uses a media server
based on the location of the first client connecting to the session. Pass a location hint in only if you know the general
geographic region (and a representative IP address) and you think the first client connecting may not be in that region.

* `properties` (Object) &mdash; Optional. An object used to define
peer-to-peer preferences for the session. The `properties` option includes one property &mdash;
`p2p.preference` (a string). This property determines whether the session's streams will
be transmitted directly between peers. You can set the following possible values:

  * "disabled" (the default) &mdash; The session's streams will all be relayed using the OpenTok media server.
    <br><br>
    **In OpenTok v2:** The <a href="http://www.tokbox.com/blog/mantis-next-generation-cloud-technology-for-webrtc/">OpenTok
media server</a> provides benefits not available in peer-to-peer sessions. For example, the OpenTok media server can
decrease bandwidth usage in multiparty sessions. Also, the OpenTok server can improve the quality of the user experience
through <a href="http://www.tokbox.com/blog/quality-of-experience-and-traffic-shaping-the-next-step-with-mantis/">dynamic
traffic shaping</a>. For information on pricing, see the <a href="http://www.tokbox.com/pricing">OpenTok pricing page</a>.

  * "enabled" &mdash; The session will attempt to transmit streams directly between clients.
    <br><br>
    **In OpenTok v1:** Peer-to-peer streaming decreases latency and improves quality. If peer-to-peer streaming
fails (either when streams are initially published or during the course of a session), the session falls back to using
the OpenTok media server to relaying streams. (Peer-to-peer streaming uses UDP, which may be blocked by a firewall.)
For a session created with peer-to-peer streaming enabled, only two clients can connect to the session at a time.
If an additional client attempts to connect, the TB object on the client dispatches an exception event.


The `create_session` method returns a Session object. This
object includes a `sessionID` property, which is the session ID for the
new session. For example, when using the OpenTok JavaScript library, use this
session ID in JavaScript on the page that you serve to the client.
The JavaScript will use this value when calling the `connect()`
method of the Session object (to connect a user to an OpenTok session).

OpenTok sessions do not expire. However, authentication tokens do expire (see the next section on the
generate_token() method.) Also note that sessions cannot explicitly be destroyed.

Calling the `create_session()` method results in an `OpenTokException`
in the event of an error. Check the error message for details.

Here is a simple example:

<pre>
&lt;?php
    require_once 'SDK/OpenTokSDK.php';

    $apiObj = new OpenTokSDK();

    if($_REQUEST['sessionId']) {
        $sessionId = $_REQUEST['sessionId'];
    } else {
        $session = $apiObj-&gt;create_session();
        $sessionId = $session-&gt;getSessionId();
    }
?&gt;
</pre>

This code checks to see if the session ID is already defined (for example, as a `sessionId` variable in
the query string). Otherwise, it creates a new session.

In an HTML page, you then can embed PHP that references the API key, the session ID,
and the generated token string, as in the following:

<pre>
var apiKey = &lt;?php print API_Config::API_KEY?&gt;;
var sessionId = '&lt;?php print $sessionId; ?&gt;';
var token = '&lt;?php print $apiObj-&gt;generate_token($sessionId); ?&gt;';
</pre>

(See the following section for information on the `generate_token()` method.)


Here is an example that creates a new session with peer-to-peer streaming enabled:

<pre>
&lt;?php
    require_once 'SDK/OpenTokSDK.php';

    $apiObj = new OpenTokSDK();

    $session = $apiObj->create_session(null,
        array(SessionPropertyConstants::P2P_PREFERENCE=&gt;"enabled"));
    echo $session->getSessionId();
?&gt;
</pre>

You can also create a session using the <a href="http://www.tokbox.com/opentok/api/#session_id_production">OpenTok
REST API</a> or the <a href="https://dashboard.tokbox.com/projects">OpenTok dashboard</a>.


generate_token() method
-----------------------

In order to authenticate a user connecting to a OpenTok
session, a user must pass an authentication token along with the API key.

The method has the following parameters:

* `session_id` (String) &mdash; The session ID corresponding to the session to which the user will connect.

* `role` (String) &mdash; Optional. Each role defines a set of permissions granted to the token.
Valid values are defined in the RoleConstants class in the server-side SDKs:

  * `SUBSCRIBER` &mdash; A subscriber can only subscribe to streams.</li>
  
  * `PUBLISHER` &mdash; A publisher can publish streams, subscribe to streams, and signal.
    (This is the default value if you do not specify a value for the `role` parameter.)</li>
   
  * `MODERATOR` &mdash; In addition to the privileges granted to a publisher, a moderator
    can call the `forceUnpublish()` and `forceDisconnect()` method of the
    Session object.</li>

* `expire_time` (int) &mdash; Optional. The time when the token
will expire, defined as an integer value for a Unix timestamp (in seconds).
If you do not specify this value, tokens expire in 24 hours after being created.
The `expiration_time` value, if specified, must be within 30 days
of the creation time.

* `connection_data` (String) &mdash; Optional. A string containing metadata describing the connection.
For example, you can pass the user ID, name, or other data describing the connection.
The length of the string is limited to 1000 characters.

Calling the `generate_token()` method returns the token string.

The following PHP code example shows how to obtain a token:

<pre>
&lt;?PHP
require_once 'OpenTokSDK.php';

$otSDK = new OpenTokSDK(A);

// Replace with the correct session ID:
print $otSDK-&gt;generate_token('your_session_ID');
?&gt;
</pre>

The following PHP code example shows how to obtain a token that has a role of "publisher" and that has
a connection metadata string:

<pre>
&lt;?PHP
require_once 'OpenTokSDK.php';

$otSDK = new OpenTokSDK();

// Replace with the correct session ID:
$sessionId = "your_session_ID";

$role = RoleConstants::PUBLISHER;

// Replace with meaningful metadata for the connection.
$connectionData = "username=Bob,userLevel=4";

print $otSDK-&gt;generate_token($sessionId, $role, null, $connectionData);
?&gt;
</pre>

For testing, you can also use the <a href="https://dashboard.tokbox.com/projects">OpenTok dashboard</a>
page to generate test tokens.
