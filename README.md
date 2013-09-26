# OpenTok PHP SDK

The OpenTok PHP SDK lets you generate [sessions](http://tokbox.com/opentok/tutorials/create-session/) and
[tokens](http://tokbox.com/opentok/tutorials/create-token/) for [OpenTok](http://www.tokbox.com/) applications.

# Installation

Download the PHP files:

<https://github.com/opentok/Opentok-PHP-SDK/archive/master.zip>

Include the OpenTok PHP SDK files in your site, and add the OpenTokSDK.php file in the PHP page:

    <?php
        require_once 'Opentok-PHP-SDK/OpenTokSDK.php';
    ?>

# Requirements

The OpenTok PHP SDK requires PHP 5.2 or greater.

You need an OpenTok API key and API secret, which you can obtain at <https://dashboard.tokbox.com>.

# API_Config

Replace these two values in the API_Config.php file with your OpenTok API key and API Secret.

    const API_KEY = "";
    const API_SECRET = "";

# Creating Sessions
Use the `createSession()` method of the OpenTokSDK object to create a session and a session ID:

<pre>
$apiObj = new OpenTokSDK();

// Creating an OpenTok server-enabled session
$session = $apiObj->createSession();
$sessionId = $session->getSessionId();
echo $sessionId;

// Creating a peer-to-peer session:
$session = $apiObj->createSession(null, array(SessionPropertyConstants::P2P_PREFERENCE=> "enabled") );
$sessionId = $session->getSessionId();
echo $sessionId;
</pre>

# Generating tokens
Use the  `generate_token()` method of the OpenTokSDK object to create an OpenTok token:

<pre>
$apiObj = new OpenTokSDK();

// Generate a publisher token that will expire in 24 hours:
$token = $apiObj->generateToken($sessionId);
echo $token;

// Give the token a moderator role, expiration time 5 days from now,
// and connectionData to pass to other users in the session:
$role = RoleConstants::MODERATOR;
$expTime = time() + (5*24*60*60);
$connData = "hello world!";
$token = $apiObj->generateToken($sessionId, $role, $expTime, $connData );
echo $token;
</pre>

# More information

See the [reference documentation](docs/reference.md).

For more information on OpenTok, go to <http://www.tokbox.com/>.
