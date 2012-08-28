# OpenTok

OpenTok is a free set of APIs from TokBox that enables websites to weave live group video communication into their online experience. Check out <http://www.tokbox.com/> for more information.  
This is the official OpenTok PHP Server SDK for generating Sessions, Tokens, and retriving Archives. Please visit our [getting started page](http://www.tokbox.com/opentok/tools/js/gettingstarted) if you are unfamiliar with these concepts.  

# Installation

Download the php files  
`git clone https://github.com/opentok/Opentok-PHP-SDK.git`

Include these files in your site.  

    <?php
        require_once 'SDK/OpenTokSDK.php';
        require_once 'SDK/OpenTokArchive.php';
        require_once 'SDK/OpenTokSession.php';
    ?>

# Requirements

You need an api-key and secret. Request them at <http://www.tokbox.com/opentok/tools/js/apikey>.  

# OpenTokSDK

In order to use any of the server side functions, you must first create an `OpenTokSDK` object with your developer credentials.  
`OpenTokSDK` takes 2-3 parameters:
> key (string) - Given to you when you register  
> secret (string) - Given to you when you register  

<pre>
// Creating an OpenTok Object
$apiObj = new OpenTokSDK('1127', 'your app secret');
</pre>

# Creating Sessions
Use your `OpenTokSDK` object to create `session_id`  
`createSession` takes 1-2 parameters:
> location (string) -  give Opentok a hint on where you are running your application  
> properties (object) - OPTIONAL. Set peer-to-peer as `enabled` or `disabled`. Disabled by default  

<pre>
// Creating Simple Session object, passing IP address to determine closest production server
$session = $apiObj->createSession( $_SERVER["REMOTE_ADDR"] );

// Creating Simple Session object 
// Enable p2p connections
$session = $apiObj->createSession( $_SERVER["REMOTE_ADDR"], array(SessionPropertyConstants::P2P_PREFERENCE=> "enabled") );
</pre>


# Generating Token
With the generated session_id, you can start generating tokens for each user.
`generate_token` takes in hash with 1-4 properties:
> session_id (string) - REQUIRED  
> role (string) - OPTIONAL. subscriber, publisher, or moderator  
> expire_time (int) - OPTIONAL. Time when token will expire in unix timestamp  
> connection_data (string) - OPTIONAL. Metadata to store data (names, user id, etc)

<pre>
// You must have a valid sessionId and an OpenTokSDK object
$apiObj = new OpenTokSDK('11421872', '296cebc2fc4104cd348016667ffa2a3909ec636f');
$sessionId = '1_MX4xMTQyMTg3Mn5-MjAxMi0wNi0wOCAwMTowNjo1MC40NTMxMzIrMDA6MDB-MC40OTY0OTM3NjIzMjh';

// After creating a session, call generateToken(). Require parameter: SessionId
$token = $apiObj->generateToken($sessionId);

// Giving the token a moderator role, expire time 5 days from now, and connectionData to pass to other users in the session
$token = $apiObj->generateToken($sessionId, RoleConstants::MODERATOR, time() + (5*24*60*60), "hello world!" );
echo $token;
</pre>

# Downloading Archive Videos
To Download archived video, you must have an Archive ID which you get from the javascript library  
If You don't know how to get an Archive ID, please refer to the [documentation](http://www.tokbox.com/opentok/api/tools/js/documentation/api/Session.html#createArchive) or our [quick tutorial](http://www.tokbox.com/blog/how-i-built-minute-grams-3-minute-tutorial/)  

# Delete Archive
Delete a achive, you must have an Archive ID which you get from the javascript library  
If You don't know how to get an Archive ID, please refer to the [documentation](http://www.tokbox.com/opentok/api/tools/js/documentation/api/Session.html#createArchive) or our [quick tutorial](http://www.tokbox.com/blog/how-i-built-minute-grams-3-minute-tutorial/)  


# OpenTokArchive
Make sure you have a valid *moderator token* and an OpenTokSDK object  
getArchiveManifest(...) creates an OpenTokArchive Object, which contains information for all videos in the Archive  
`get_archive_manifest()` takes in 2 parameters: **archiveId** and **moderator token**  
> archive_id (string) - REQUIRED.  
> token (string) - REQUIRED.   
> **returns** an `OpenTokArchive` object. The *resources* property of this object is array of `OpenTokArchiveVideoResource` objects, and each `OpenTokArchiveVideoResource` object represents a video in the archive.

<pre>
// Make sure token has the moderator role
$token = $apiObj->generateToken($sessionId, RoleConstants::MODERATOR);

// This archiveId is generated from your javascript library after you record something
$archiveId = '5f74aee5-ab3f-421b-b124-ed2a698ee939';

// Create an archive object
$archive = $apiObj->getArchiveManifest($archiveId, $token);
</pre>

# OpenTokArchiveVideoResource
The OpenTokArchive has a getResources() function that returns and array of `OpenTokArchiveVideoResource` object.  

<pre>
// Create an archive object
$archive = $apiObj->getArchiveManifest($archiveId, $token);

// Get the array of video sources
$resources = $archive->getResources();
</pre>


# Video Id
OpenTokArchiveVideoResource has `getId()` method that returns the videoId, which you can use to get the video file  
`getId()` will return the video ID (String)

Example:
<pre>
// Get the array of video sources
$resources = $archive->getResources();

// Get video Id
$vid = $resources[0]->getId();  
</pre>

# Get Download Url
`OpenTokArchive` has `downloadArchiveURL` that will return an url string for downloading the video in the archive. You must call this function every time you want the file, because this url expires after 24 hours
> video_id (string) - REQUIRED  
> token (string) - REQUIRED  
> returns url string

Example:
<pre>
// Get video Id
$vid = $resources[0]->getId();  

// Get file URL
$url = $archive->downloadArchiveURL($vid, $token);
</pre>

# Updates/Changes
* Production apps are set by Boolean parameter when initializing OpenTokSDK  
* downloadArchiveURL now takes in 2 parameters, and returns the file URL  
