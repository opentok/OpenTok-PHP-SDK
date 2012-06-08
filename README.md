# OpenTok

OpenTok is a free set of APIs from TokBox that enables websites to weave live group video communication into their online experience. Check out <http://www.tokbox.com/> for more information.  
This is the official OpenTok PHP Server SDK for generating Sessions, Tokens, and retriving Archives. Please visit our [getting started page](http://www.tokbox.com/opentok/tools/js/gettingstarted) if you are unfamiliar with these concepts.  

# Installation

Download these php files  
`git clone https://github.com/opentok/Opentok-PHP-SDK.git`

Include these files in your site.  
<pre>
<?php
    require_once 'SDK/OpenTokSDK.php';
    require_once 'SDK/OpenTokArchive.php';
    require_once 'SDK/OpenTokSession.php';
?>
</pre>


# Requirements

You need an api-key and secret. Request them at <http://www.tokbox.com/opentok/tools/js/apikey>.  

# OpenTokSDK

In order to use any of the server side functions, you must first create an `OpenTokSDK` object with your developer credentials.  
`OpenTokSDK` takes 2-3 parameters:
> key (string) - Given to you when you register  
> secret (string) - Given to you when you register  
> Production (Boolean) - OPTIONAL. Puts your app in staging or production environment. Default falue is `FALSE`  
For more information about production apps, check out <http://www.tokbox.com/opentok/api/tools/js/launch>

<pre>
// Creating an OpenTok Object in Staging Environment
$apiObj = new OpenTokSDK('1127', 'your app secret');

// Creating an OpenTok Object in Production Environment
$apiObj = new OpenTokSDK('1127', 'your app secret', TRUE); 
</pre>

# Creating Sessions
Use your `OpenTokSDK` object to create `session_id`  
`createSession` takes 1-2 parameters:
> location (string) -  give Opentok a hint on where you are running your application  
> properties (object) - OPTIONAL. Set peer to peer as `enabled` or `disabled`

<pre>
// Creating Simple Session object, passing IP address to determine closest production server
// Passing IP address to determine closest production server
$session = $apiObj->createSession( $_SERVER["REMOTE_ADDR"] );

// Creating Simple Session object 
// Enable p2p connections
$session = $apiObj->createSession( $_SERVER["REMOTE_ADDR"], array(SessionPropertyConstants::P2P_PREFERENCE=> "enabled") );
</pre>


Example: P2P disabled by default
<pre>
@location = 'localhost'
session_id = @opentok.create_session(@location)
</pre>

Example: P2P enabled
<pre>
session_properties = {OpenTok::SessionPropertyConstants::P2P_PREFERENCE => "enabled"}    # or disabled
session_id = @opentok.create_session( @location, session_properties )
</pre>

### Generating Token
With the generated session_id, you can start generating tokens for each user.
`generate_token` takes in hash with 1-4 properties:
> session_id (string) - REQUIRED  
> role (string) - OPTIONAL. subscriber, publisher, or moderator  
> expire_time (int) - OPTIONAL. Time when token will expire in unix timestamp  
> connection_data (string) - OPTIONAL. Metadata to store data (names, user id, etc)

Example:
<pre>
token = @opentok.generate_token :session_id => session, :role => OpenTok::RoleConstants::PUBLISHER, :connection_data => "username=Bob,level=4"
</pre>

### Downloading Archive Videos
To Download archived video, you must have an Archive ID which you get from the javascript library

#### Quick Overview of the javascript library: <http://www.tokbox.com/opentok/api/tools/js/documentation/api/Session.html#createArchive>
1. Create an event listener on `archiveCreated` event: `session.addEventListener('archiveCreated', archiveCreatedHandler);`  
2. Create an archive: `archive = session.createArchive(...);`  
3. When archive is successfully created `archiveCreatedHandler` would be triggered. An Archive object containing `archiveId` property is passed into your function. Save this in your database, this archiveId is what you use to reference the archive for playbacks and download videos  
4. After your archive has been created, you can start recording videos into it by calling `session.startRecording(archive)`  
 Optionally, you can also use the standalone archiving, which means that each archive would have only 1 video: <http://www.tokbox.com/opentok/api/tools/js/documentation/api/RecorderManager.html>

### Get Archive Manifest
With your **moderator token** and OpentokSDK Object, you can generate OpenTokArchive Object, which contains information for all videos in the Archive  
`get_archive_manifest()` takes in 2 parameters: **archiveId** and **moderator token**  
> archive_id (string) - REQUIRED. 
> **returns** an `OpenTokArchive` object. The *resources* property of this object is array of `OpenTokArchiveVideoResource` objects, and each `OpenTokArchiveVideoResource` object represents a video in the archive.

Example:(Make sure you have the OpentokSDK Object)
<pre>
@token = 'moderator_token'
@archiveId = '5f74aee5-ab3f-421b-b124-ed2a698ee939' #Obtained from Javascript Library
otArchive = @opentok.get_archive_manifest(@archiveId, @token)
</pre>

### Get video ID
`OpenTokArchive.resources` is an array of `OpenTokArchiveVideoResource` objects. OpenTokArchiveVideoResource has `getId()` method that returns the videoId
`getId()` will return the video ID (a String)

Example:
<pre>
otArchive = @opentok.get_archive_manifest(@archiveId, @token)
otVideoResource = otArchive.resources[0]
videoId = otVideoResource.getId()
</pre>

### Get Download Url
`OpenTokArchive` has `downloadArchiveURL` that will return an url string for downloading the video in the archive. You must call this function every time you want the file, because this url expires after 24 hours
> video_id (string) - REQUIRED  
> token (string) - REQUIRED  
> returns url string

Example:
<pre>
url = otArchive.downloadArchiveURL(video_id, token)
</pre>
