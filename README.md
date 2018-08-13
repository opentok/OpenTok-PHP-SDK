# OpenTok PHP SDK

[![Build Status](https://travis-ci.org/opentok/OpenTok-PHP-SDK.svg)](https://travis-ci.org/opentok/OpenTok-PHP-SDK)

The OpenTok PHP SDK lets you generate [sessions](http://tokbox.com/opentok/tutorials/create-session/) and
[tokens](http://tokbox.com/opentok/tutorials/create-token/) for [OpenTok](http://www.tokbox.com/)
applications, and [archive](http://tokbox.com/opentok/tutorials/archiving/) sessions.

# Installation

## Composer (recommended):

Composer helps manage dependencies for PHP projects. Find more info here: <http://getcomposer.org>

Add this package (`opentok/opentok`) to your `composer.json` file, or just run the following at the
command line:

```
$ ./composer.phar require opentok/opentok 4.2.x
```

## Manually:

Download the `opentok.phar` file for the latest release from the [Releases](https://github.com/opentok/opentok-php-sdk/releases)
page.

Place `opentok.phar` in the [include_path](http://www.php.net/manual/en/ini.core.php#ini.include-path) OR
require it in any script which uses the `OpenTok\*` classes.

# Usage

## Initializing

This package follows the [PSR-4](http://www.php-fig.org/psr/psr-4/) autoloading standard. If you are
using composer to install, you just require the generated autoloader:

```php
require "<projectpath>/vendor/autoload.php";
```

Once the files of the SDK are loaded, you initialize an `OpenTok\OpenTok` object with your own API
Key and API Secret.

```php
use OpenTok\OpenTok;

$opentok = new OpenTok($apiKey, $apiSecret);
```

## Creating Sessions

To create an OpenTok Session, use the `createSession($options)` method of the
`OpenTok\OpenTok` class. The `$options` parameter is an optional array used to specify the following:

* Setting whether the session will use the OpenTok Media Router or attempt to send streams directly
  between clients.

* Setting whether the session will automatically create archives (implies use of routed session)

* Specifying a location hint.

The `getSessionId()` method of the `OpenTok\Session` instance returns the session ID,
which you use to identify the session in the OpenTok client libraries.

```php
use OpenTok\MediaMode;
use OpenTok\ArchiveMode;

// Create a session that attempts to use peer-to-peer streaming:
$session = $opentok->createSession();

// A session that uses the OpenTok Media Router, which is required for archiving:
$session = $opentok->createSession(array( 'mediaMode' => MediaMode::ROUTED ));

// A session with a location hint:
$session = $opentok->createSession(array( 'location' => '12.34.56.78' ));

// An automatically archived session:
$sessionOptions = array(
    'archiveMode' => ArchiveMode::ALWAYS,
    'mediaMode' => MediaMode::ROUTED
);
$session = $opentok->createSession($sessionOptions);


// Store this sessionId in the database for later use
$sessionId = $session->getSessionId();
```

## Generating Tokens

Once a Session is created, you can start generating Tokens for clients to use when connecting to it.
You can generate a token either by calling the `generateToken($sessionId, $options)` method of the
`OpenTok\OpenTok` class, or by calling the `generateToken($options)` method on the `OpenTok\Session`
instance after creating it. The `$options` parameter is an optional array used to set the role,
expire time, and connection data of the Token.

```php
use OpenTok\Session;
use OpenTok\Role;

// Generate a Token from just a sessionId (fetched from a database)
$token = $opentok->generateToken($sessionId);
// Generate a Token by calling the method on the Session (returned from createSession)
$token = $session->generateToken();

// Set some options in a token
$token = $session->generateToken(array(
    'role'       => Role::MODERATOR,
    'expireTime' => time()+(7 * 24 * 60 * 60), // in one week
    'data'       => 'name=Johnny'
));
```

## Working with Streams

You can get information about a stream by calling the `getStream($sessionId, $streamId)` method of the
`OpenTok\OpenTok` class.

```php
use OpenTok\Session;

// Get stream info from just a sessionId (fetched from a database)
$stream = $opentok->getStream($sessionId, $streamId);

// Stream properties
$stream->id; // string with the stream ID
$stream->videoType; // string with the video type
$stream->name; // string with the name
$stream->layoutClassList; // array with the layout class list
```

You can get information about all the streams in a session by calling the `listStreams($sessionId)` method of the
`OpenTok\OpenTok` class.

```php
use OpenTok\Session;

// Get list of streams from just a sessionId (fetched from a database)
$streamList = $opentok->listStreams($sessionId);

$streamList->totalCount(); // total count
```

## Working with Archives

You can only archive sessions that use the OpenTok Media Router
(sessions with the media mode set to routed).

You can start the recording of an OpenTok Session using the `startArchive($sessionId, $name)` method
of the `OpenTok\OpenTok` class. This will return an `OpenTok\Archive` instance. The parameter
`$archiveOptions` is an optional array and is used to assign a name, whether to record audio and/or
video, the desired output mode for the Archive, and the desired resolution if applicable. Note that you can only start an
Archive on a Session that has clients connected.

```php
// Create a simple archive of a session
$archive = $opentok->startArchive($sessionId);


// Create an archive using custom options
$archiveOptions = array(
    'name' => 'Important Presentation',      // default: null
    'hasAudio' => true,                      // default: true
    'hasVideo' => true,                      // default: true
    'outputMode' => OutputMode::COMPOSED,    // default: OutputMode::COMPOSED
    'resolution' => '1280x720'               // default: '640x480'
);
$archive = $opentok->startArchive($sessionId, $archiveOptions);

// Store this archiveId in the database for later use
$archiveId = $archive->id;
```

If you set the `outputMode` option to `OutputMode::INDIVIDUAL`, it causes each stream in the archive to be recorded to its own individual file. Please note that you cannot specify the resolution when you set the `outputMode` option to `OutputMode::INDIVIDUAL`. The `OutputMode::COMPOSED` setting (the default) causes all streams in the archive to be recorded to a single (composed) file.

You can stop the recording of a started Archive using the `stopArchive($archiveId)` method of the
`OpenTok\OpenTok` object. You can also do this using the `stop()` method of the
`OpenTok\Archive` instance.

```php
// Stop an Archive from an archiveId (fetched from database)
$opentok->stopArchive($archiveId);
// Stop an Archive from an Archive instance (returned from startArchive)
$archive->stop();
```

To get an `OpenTok\Archive` instance (and all the information about it) from an archive ID, use the
`getArchvie($archiveId)` method of the `OpenTok\OpenTok` class.

```php
$archive = $opentok->getArchive($archiveId);
```

To delete an Archive, you can call the `deleteArchive($archiveId)` method of the `OpenTok\OpenTok`
class or the `delete()` method of an `OpenTok\Archive` instance.

```php
// Delete an Archive from an archiveId (fetched from database)
$opentok->deleteArchive($archiveId);
// Delete an Archive from an Archive instance (returned from startArchive, getArchive)
$archive->delete();
```

You can also get a list of all the Archives you've created (up to 1000) with your API Key. This is
done using the `listArchives($offset, $count)` method of the `OpenTok/OpenTok` class. The parameters
`$offset` and `$count` are optional and can help you paginate through the results. This will return
an instance of the `OpenTok\ArchiveList` class.

```php
$archiveList = $opentok->listArchives();

// Get an array of OpenTok\Archive instances
$archives = $archiveList->getItems();
// Get the total number of Archives for this API Key
$totalCount = $archiveList->totalCount();
```

Note that you can also create an automatically archived session, by passing in `ArchiveMode::ALWAYS`
as the `archiveMode` key of the `options` parameter passed into the `OpenTok->createSession()`
method (see "Creating Sessions," above).

For more information on archiving, see the
[OpenTok archiving](https://tokbox.com/opentok/tutorials/archiving/) programming guide.

## Force Disconnect

Your application server can disconnect a client from an OpenTok session by calling the `forceDisconnect($sessionId, $connectionId)` 
method of the `OpenTok\OpenTok` class.

```php
use OpenTok\OpenTok;

// Force disconnect a client connection
$opentok->forceDisconnect($sessionId, $connectionId);
```
## Sending Signals

Once a Session is created, you can send signals to everyone in the session or to a specific connection.
You can send a signal by calling the `signal($sessionId, $payload, $connectionId)` method of the
`OpenTok\OpenTok` class.

The `$sessionId` parameter is the session ID of the session.

The `$payload` parameter is an associative array used to set the
following:

* `data` (string) -- The data string for the signal. You can send a maximum of 8kB.

* `type` (string) -- &mdash; (Optional) The type string for the signal. You can send a maximum of 128 characters, and only the following characters are allowed: A-Z, a-z, numbers (0-9), '-', '_', and '~'.

The `$connectionId` parameter is an optional string used to specify the connection ID of
a client connected to the session. If you specify this value, the signal is sent to
the specified client. Otherwise, the signal is sent to all clients connected to the session.


```php
use OpenTok\OpenTok;

// Send a signal to a specific client
$signalPayload = array(
    'data' => 'some signal message',
    'type' => 'signal type'
);
$connectionId = 'da9cb410-e29b-4c2d-ab9e-fe65bf83fcaf';
$opentok->signal($sessionId, $signalPayload, $connectionId);

// Send a signal to everyone in the session
$signalPayload = array(
    'data' => 'some signal message',
    'type' => 'signal type'
);
$opentok->signal($sessionId, $signalPayload);
```

For more information, see the [OpenTok signaling developer
guide](https://tokbox.com/developer/guides/signaling/).

# Samples

There are two sample applications included in this repository. To get going as fast as possible, clone the whole
repository and follow the Walkthroughs:

*  [HelloWorld](sample/HelloWorld/README.md)
*  [Archiving](sample/Archiving/README.md)

# Documentation

Reference documentation is available at
<https://tokbox.com/developer/sdks/php/reference/index.html>.

# Requirements

You need an OpenTok API key and API secret, which you can obtain by logging into your
[TokBox account](https://tokbox.com/account).

The OpenTok PHP SDK requires PHP 5.6+ or PHP 7+

For PHP 5.5 and lower please use PHP SDK v2.5

# Release Notes

See the [Releases](https://github.com/opentok/opentok-php-sdk/releases) page for details
about each release.

# Important changes since v2.2.0

**Changes in v2.2.1:**

The default setting for the `createSession()` method is to create a session with the media mode set
to relayed. In previous versions of the SDK, the default setting was to use the OpenTok Media Router
(media mode set to routed). In a relayed session, clients will attempt to send streams directly
between each other (peer-to-peer); if clients cannot connect due to firewall restrictions, the
session uses the OpenTok TURN server to relay audio-video streams.

**Changes in v2.2.0:**

This version of the SDK includes support for working with OpenTok archives.

The names of many methods of the API have changed. Many method names have
changed to use camel case, including the following:

* `\OpenTok\OpenTok->createSession()`
* `\OpenTok\OpenTok->generateToken()`

Note also that the `options` parameter of the `OpenTok->createSession()` method has a `mediaMode`
property instead of a `p2p` property.

The API_Config class has been removed. Store your OpenTok API key and API secret in code outside of the SDK files.

See the reference documentation
<http://www.tokbox.com/opentok/libraries/server/php/reference/index.html> and in the
docs directory of the SDK.

# Development and Contributing

Interested in contributing? We :heart: pull requests! See the [Development](DEVELOPING.md) and
[Contribution](CONTRIBUTING.md) guidelines.

# Support

See <https://support.tokbox.com> for all our support options.

Find a bug? File it on the [Issues](https://github.com/opentok/opentok-php-sdk/issues) page. Hint:
test cases are really helpful!
