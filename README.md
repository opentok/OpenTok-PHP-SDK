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

The OpenTok PHP SDK requires PHP 5.4 or greater.

You need an OpenTok API key and API secret, which you can obtain at <https://dashboard.tokbox.com>.

# Changes in v2.0 of the OpenTok PHP SDK

This version of the SDK includes support for working with OpenTok 2.0 archives. (This API does not work
with OpenTok 1.0 archives.)

Note that this version of the OpenTok PHP SDK requires PHP 5.4 or greater.

# API_Config

Replace these two values in the API_Config.php file with your OpenTok API key and API Secret.

    const API_KEY = "";
    const API_SECRET = "";

# Creating sessions
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

# Working with OpenTok 2.0 archives

The following code starts recording an archive of an OpenTok 2.0 session
and returns the archive ID (on success). Note that you can only start an archive
on a session that has clients connected.

<pre>
$apiObj = new OpenTokSDK();
$session_id = ""; // Replace this with an OpenTok session ID.
$name = "my first archive";

try {
  $apiObj->startArchive($session_id);
} catch (Exception $error) {
  echo $error->getMessage();
}
</pre>

The following method stops the recording of an archive, returning
true on success, and false on failure.

<pre>
$apiObj = new OpenTokSDK();
$archive_id = ""; // Replace with a valid archive ID.

try {
  $apiObj->stopArchive($archive_id);
} catch (Exception $error) {
  echo $error->getMessage();
}
</pre>

The following method logs information on a given archive.

<pre>
$apiObj = new OpenTokSDK();
$archive_id = ""; // Replace with a valid archive ID.

try {
  $archive = $apiObj->deleteArchive($archive_id);
  echo "Deleted archive: ", $archive_id, "\n";
} catch (Exception $error) {
  echo $error->getMessage();
}
</pre>

The following method logs information on a given archive.

<pre>
$apiObj = new OpenTokSDK();
$archive_id = ""; // Replace with a valid archive ID.

try {
  $archive = $apiObj->getArchive($archive_id);
  echo "createdAt: ", $archive->createdAt(), "\n";
  echo "duration: ", $archive->duration(), "\n";
  echo "id: ", $archive->id(), "\n";
  echo "name: ", $archive->name(), "\n";
  echo "reason: ", $archive->reason(), "\n";
  echo "sessionId: ", $archive->sessionId(), "\n";
  echo "size: ", $archive->size(), "\n";
} catch (Exception $error) {
  echo $error->getMessage();
}
</pre>

The following method logs information on all archives (up to 1000)
for your API key.

<pre>
$apiObj = new OpenTokSDK();
try {
  $archive_list = $apiObj->listArchives();
  $count = $archive_list->totalCount();
  echo("Number of archives: {$count}\n");
  foreach ($archive_list->items() as $archive) {
      echo $archive->id(), "\n";
  }
} catch (Exception $error) {
  echo $error->getMessage();
}
</pre>


# More information

See the [reference documentation](docs/reference.md).

For details on the API, see the comments in Java files in src/main/java/com/opentok.


For more information on OpenTok, go to <http://www.tokbox.com/>.
