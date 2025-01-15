# OpenTok PHP SDK

>## Notice
>
> This library is designed to work with the Tokbox/OpenTok platform, part of the Vonage Video API. If you are looking to use the Vonage Video API and using the Vonage Customer Dashboard, you will want to install the
> [Vonage Server SDK for PHP]((https://github.com/Vonage/vonage-php-sdk-video)), which includes support for the Vonage Video API.
>
> Not sure which exact platform you are using? Take a look at [this guide](https://api.support.vonage.com/hc/en-us/articles/10817774782492).
>
> If you are using the Tokbox platform, do not worry! The Tokbox platform is not going away, and this library will continue to be updated. While we encourage customers to check out the new Unified platform, there is no rush to  > switch. Both platforms run the exact same infrastructure and capabilities, with the main difference is a unified billing interface and easier access to [Vonage’s other CPaaS APIs](https://www.vonage.com/communications-apis/).
>
> If you are new to the Vonage Video API, head on over to the [Vonage Customer Dashboard](https://dashboard.vonage.com) to sign up for a developer account and check out the [Vonage Server SDK for <language>](link to core SDK). 

[![Build Status](https://travis-ci.org/opentok/OpenTok-PHP-SDK.svg)](https://travis-ci.org/opentok/OpenTok-PHP-SDK) [![Contributor Covenant](https://img.shields.io/badge/Contributor%20Covenant-v2.0%20adopted-ff69b4.svg)](CODE_OF_CONDUCT.md)

<img src="https://assets.tokbox.com/img/vonage/Vonage_VideoAPI_black.svg" height="48px" alt="Tokbox is now known as Vonage" />

The OpenTok PHP SDK provides methods for:

* Generating
[sessions](https://tokbox.com/developer/guides/create-session/) and
[tokens](https://tokbox.com/developer/guides/create-token/) for
[OpenTok](https://www.tokbox.com/) applications that run on the .NET platform
* Working with [OpenTok archives](https://tokbox.com/opentok/tutorials/archiving)
* Working with [OpenTok live streaming broadcasts](https://tokbox.com/developer/guides/broadcast/live-streaming/)
* Working with [OpenTok SIP interconnect](https://tokbox.com/developer/guides/sip)
* [Sending signals to clients connected to a session](https://tokbox.com/developer/guides/signaling/)
* [Disconnecting clients from sessions](https://tokbox.com/developer/guides/moderation/rest/)
* [Forcing clients in a session to disconnect or mute published audio](https://tokbox.com/developer/guides/moderation/)
* Working with OpenTok [Audio Connector](https://tokbox.com/developer/guides/audio-connector)

## Installation

### Composer (recommended):

Composer helps manage dependencies for PHP projects. Find more info here: <http://getcomposer.org>

Add this package (`opentok/opentok`) to your `composer.json` file, or run the following at the
command line:

```bash
$ composer require opentok/opentok ^4.0
```

## Usage

### Initializing

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

#### Initialization Options

The `OpenTok\OpenTok` object just allow for some overrides of values when special needs arise, such as
needing to point to a different datacenter or change the timeout of the underlying HTTP client. For
these situations, you can pass an array of additional options as the third parameter.

We allow the following options:
* `apiUrl` - Change the domain that the SDK points to. Useful when needing to select a specific datacenter
or point to a mock version of the API for testing
* `client` - Custom API client that inherits from `OpenTok\Utils\Client`, useful for customizing an HTTP client
* `timeout` - Change the default HTTP timeout, which defaults to forever. You can pass a number of seconds to change the timeout.

```php
use OpenTok\OpenTok;
use MyCompany\CustomOpenTokClient;

$options = [
    'apiUrl' => 'https://custom.domain.com/',
    'client' => new CustomOpenTokClient(),
    'timeout' => 10,
]
$opentok = new OpenTok($apiKey, $apiSecret, $options);
```
#### Migrating to Vonage Video API

There is some useful behaviour on initialization in this SDK that will help as a stopgap before switching out from the
legacy TokBok API to the new, Vonage Video API. To do this, you add a `private_key_path` and
`application_id` into the `$options`. Note that the SDK will read the private key path from the root directory of 
the SDK, so you will need to adjust the directory structures accordingly.

```php
use OpenTok\OpenTok;
use MyCompany\CustomOpenTokClient;

$options = [
    'application_id' => 'your_application_id',
    'private_key_path' => './path-to-your.key'
]

$opentok = new OpenTok($apiKey, $apiSecret, $options);
```

### Creating Sessions

To create an OpenTok Session, use the `createSession($options)` method of the
`OpenTok\OpenTok` class. The `$options` parameter is an optional array used to specify the following:

- Setting whether the session will use the OpenTok Media Router or attempt to send streams directly
  between clients.

- Setting whether the session will automatically create archives (implies use of routed session)

- Specifying a location hint.

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

### Generating Tokens

Once a Session is created, you can start generating Tokens for clients to use when connecting to it.
You can generate a token either by calling the `generateToken($sessionId, $options)` method of the
`OpenTok\OpenTok` class, or by calling the `generateToken($options)` method on the `OpenTok\Session`
instance after creating it. The `$options` parameter is an optional array used to set the role,
expire time, and connection data of the Token. For layout control in archives and broadcasts,
the initial layout class list of streams published from connections using this token can be set as well.

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
    'data'       => 'name=Johnny',
    'initialLayoutClassList' => array('focus')
));
```

### Working with Streams

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

### Working with Archives

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
    'name' => 'Important Presentation',     // default: null
    'hasAudio' => true,                     // default: true
    'hasVideo' => true,                     // default: true
    'outputMode' => OutputMode::COMPOSED,   // default: OutputMode::COMPOSED
    'resolution' => '1280x720'              // default: '640x480'
);
$archive = $opentok->startArchive($sessionId, $archiveOptions);

// Store this archiveId in the database for later use
$archiveId = $archive->id;
```

If you set the `outputMode` option to `OutputMode::INDIVIDUAL`, it causes each stream in the archive to be recorded to its own individual file. Please note that you cannot specify the resolution when you set the `outputMode` option to `OutputMode::INDIVIDUAL`. The `OutputMode::COMPOSED` setting (the default) causes all streams in the archive to be recorded to a single (composed) file.

Note that you can also create an automatically archived session, by passing in `ArchiveMode::ALWAYS`
as the `archiveMode` key of the `options` parameter passed into the `OpenTok->createSession()`
method (see "Creating Sessions," above).

You can stop the recording of a started archive using the `stopArchive($archiveId)` method of the
`OpenTok\OpenTok` object. You can also do this using the `stop()` method of the
`OpenTok\Archive` instance.

```php
// Stop an Archive from an archiveId (fetched from database)
$opentok->stopArchive($archiveId);
// Stop an Archive from an Archive instance (returned from startArchive)
$archive->stop();
```

To get an `OpenTok\Archive` instance (and all the information about it) from an archive ID, use the
`getArchive($archiveId)` method of the `OpenTok\OpenTok` class.

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
done using the `listArchives($offset, $count, $sessionId)` method of the `OpenTok/OpenTok` class. The parameters
`$offset`, `$count`, and `$sessionId` are optional and can help you paginate through the results, and subset the
data by a specific session. This will return an instance of the `OpenTok\ArchiveList` class.

```php
$archiveList = $opentok->listArchives();

// Get an array of OpenTok\Archive instances
$archives = $archiveList->getItems();
// Get the total number of Archives for this API Key
$totalCount = $archiveList->totalCount();
```

For composed archives, you can change the layout dynamically, using the `setArchiveLayout($archiveId, $layoutType)` method:

```php
use OpenTok\OpenTok;

$layout = Layout::getPIP(); // Or use another get method of the Layout class.
$opentok->setArchiveLayout($archiveId, $layout);
```

You can set the initial layout class for a client's streams by setting the `layout` option when
you create the token for the client, using the `OpenTok->generateToken()` method or the
`Session->generateToken()` method. And you can change the layout classes for a stream
by calling the `OpenTok->updateStream()` method.

Setting the layout of composed archives is optional. By default, composed archives use the
"best fit" layout (see [Customizing the video layout for composed
archives](https://tokbox.com/developer/guides/archiving/layout-control.html)).

For more information on archiving, see the
[OpenTok archiving](https://tokbox.com/developer/guides/archiving/) developer guide.

### Working with Broadcasts

You can only start live streaming broadcasts for sessions that use the OpenTok Media Router
(sessions with the media mode set to routed).

Start the live streaming broadcast of an OpenTok Session using the
`startBroadcast($sessionId, $options)` method of the `OpenTok\OpenTok` class.
This will return an `OpenTok\Broadcast` instance. The `$options` parameter is
an array used to define the broadcast streams, assign broadcast options such as layout,
maxDuration, resolution, and more.

```php
// Define options for the broadcast
$options = [
  'layout' => Layout::getBestFit(),
  'maxDuration' => 5400,
  'resolution' => '1280x720',
  'output' => [
    'hls' => [
      'dvr' => true,
      'lowLatency' => false
    ],
    'rtmp' => [
      [
        'id' => 'foo',
        'serverUrl' => 'rtmps://myfooserver/myfooapp',
        'streamName' => 'myfoostream'
      ],
      [
        'id' => 'bar',
        'serverUrl' => 'rtmps://myfooserver/mybarapp',
        'streamName' => 'mybarstream'
      ],
    ]
  ]
];

// Start a live streaming broadcast of a session
$broadcast = $opentok->startBroadcast($sessionId, $options);

// Store the broadcast ID in the database for later use
$broadcastId = $broadcast->id;
```

You can stop the live streaming broadcast using the `stopBroadcast($broadcastId)` method of the
`OpenTok\OpenTok` object. You can also do this using the `stop()` method of the
`OpenTok\Broadcast` instance.

```php
// Stop a broadcast from an broadcast ID (fetched from database)
$opentok->stopBroadcast($broadcastId);

// Stop a broadcast from an Broadcast instance (returned from startBroadcast)
$broadcast->stop();
```

To get an `OpenTok\Broadcast` instance (and all the information about it) from a broadcast ID,
use the `getBroadcast($broadcastId)` method of the `OpenTok\OpenTok` class.

```php
$broadcast = $opentok->getBroadcast($broadcastId);
```

You can set change the layout dynamically, using the
`OpenTok->updateBroadcastLayout($broadcastId, $layout)` method:

```php
use OpenTok\OpenTok;

$layout = Layout::getPIP(); // Or use another get method of the Layout class.
$opentok->updateBroadcastLayout($broadcastId, $layout);
```

You can use the `Layout` class to set the layout types:
`Layout::getHorizontalPresentation()`, `Layout::getVerticalPresentation()`, `Layout::getPIP()`,
`Layout::getBestFit()`, `Layout::createCustom()`.

```php
$layoutType = Layout::getHorizontalPresentation();
$opentok->setArchiveLayout($archiveId, $layoutType);

// For custom Layouts, you can do the following
$options = array(
    'stylesheet' => 'stream.instructor {position: absolute; width: 100%;  height:50%;}'
);

$layoutType = Layout::createCustom($options);
$opentok->setArchiveLayout($archiveId, $layoutType);
```

You can also set the Screenshare Layout by calling the `setScreenshareType()` method on a layout object.

```php
$layout = Layout::getBestFit(); // Other types are not currently supported
$layout->setScreenshareType(Layout::LAYOUT_VERTICAL);
```

You can set the initial layout class for a client's streams by setting the `layout` option when
you create the token for the client, using the `OpenTok->generateToken()` method or the
`Session->generateToken()` method. And you can change the layout classes for a stream
by calling the `OpenTok->updateStream()` method.

Setting the layout of live streaming broadcasts is optional. By default, broadcasts use the
"best fit" layout (see [Configuring video layout for OpenTok live streaming
broadcasts](https://tokbox.com/developer/guides/broadcast/live-streaming/#configuring-video-layout-for-opentok-live-streaming-broadcasts)).

For more information on live streaming broadcasts, see the
[OpenTok live streaming broadcasts](https://tokbox.com/developer/guides/broadcast/live-streaming/)
developer guide.

### Force a Client to Disconnect

Your application server can disconnect a client from an OpenTok session by calling the `forceDisconnect($sessionId, $connectionId)`
method of the `OpenTok\OpenTok` class.

```php
use OpenTok\OpenTok;

// Force disconnect a client connection
$opentok->forceDisconnect($sessionId, $connectionId);
```

### Forcing clients in a session mute published audio

You can force the publisher of a specific stream to stop publishing audio using the 
`Opentok.forceMuteStream($sessionId, $stream)` method.

You can force the publisher of all streams in a session (except for an optional list of streams)
to stop publishing audio using the `Opentok.forceMuteAll($sessionId, $excludedStreamIds)` method.
You can then disable the mute state of the session by calling the
`Opentok.DisableForceMute(sessionId)` or `Opentok.DisableForceMuteAsync(sessionId)`
method.

### Sending Signals

Once a Session is created, you can send signals to everyone in the session or to a specific connection.
You can send a signal by calling the `signal($sessionId, $payload, $connectionId)` method of the
`OpenTok\OpenTok` class.

The `$sessionId` parameter is the session ID of the session.

The `$payload` parameter is an associative array used to set the
following:

- `data` (string) -- The data string for the signal. You can send a maximum of 8kB.

- `type` (string) -- &mdash; (Optional) The type string for the signal. You can send a maximum of 128 characters, and only the following characters are allowed: A-Z, a-z, numbers (0-9), '-', '\_', and '~'.

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

### Working with SIP Interconnect

You can add an audio-only stream from an external third-party SIP gateway using the SIP
Interconnect feature. This requires a SIP URI, the session ID you wish to add the audio-only
stream to, and a token to connect to that session ID.

To initiate a SIP call, call the `dial($sessionId, $token, $sipUri, $options)` method of the
`OpenTok\OpenTok` class:

```php
$sipUri = 'sip:user@sip.partner.com;transport=tls';

$options = array(
  'headers' =>  array(
    'X-CUSTOM-HEADER' => 'headerValue'
  ),
  'auth' => array(
    'username' => 'username',
    'password' => 'password'
  ),
  'secure' => true,
  'from' => 'from@example.com'
);

$opentok->dial($sessionId, $token, $sipUri, $options);
```

For more information, see the [OpenTok SIP Interconnect developer
guide](https://tokbox.com/developer/guides/sip/).

### Working with Audio Connector

You can start an [Audio Connector](https://tokbox.com/developer/guides/audio-connector) WebSocket
by calling the `connectAudio()` method of the
`OpenTok\OpenTok` class.

## Samples

There are three sample applications included in this repository. To get going as fast as possible, clone the whole
repository and follow the Walkthroughs:

- [HelloWorld](sample/HelloWorld/README.md)
- [Archiving](sample/Archiving/README.md)
- [SipCall](sample/SipCall/README.md)

## Documentation

Reference documentation is available at
<https://tokbox.com/developer/sdks/php/reference/index.html>.

## Requirements

You need an OpenTok API key and API secret, which you can obtain by logging into your
[Vonage Video API account](https://tokbox.com/account).

The OpenTok PHP SDK requires PHP 7.2 or higher.

## Release Notes

See the [Releases](https://github.com/opentok/opentok-php-sdk/releases) page for details
about each release.

## Important changes since v2.2.0

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

- `\OpenTok\OpenTok->createSession()`
- `\OpenTok\OpenTok->generateToken()`

Note also that the `options` parameter of the `OpenTok->createSession()` method has a `mediaMode`
property instead of a `p2p` property.

The API_Config class has been removed. Store your OpenTok API key and API secret in code outside of the SDK files.

See the reference documentation
<http://www.tokbox.com/opentok/libraries/server/php/reference/index.html> and in the
docs directory of the SDK.

## Running code quality tooling

This library makes use of two code quality tools, plus a full PHPUnit test suite.

* [phpcs](https://github.com/squizlabs/PHP_CodeSniffer)

To run phpcs, enter the following on the command line:

```bash
 $ composer run phpcs
 ```

* [phpstan](https://github.com/phpstan/phpstan)

To run phpstan, enter the following on the command line:

```bash
 $ composer run phpstan
 ```

* [phpunit](https://phpunit.de/)

To run PhpUnit, enter the following on the command line:

```bash
 $ composer run test
 ```

## Development and Contributing

Interested in contributing? We :heart: pull requests! See the [Development](DEVELOPING.md) and
[Contribution](CONTRIBUTING.md) guidelines.

## Getting Help

We love to hear from you so if you have questions, comments or find a bug in the project, let us know! You can either:

- Open an issue on this repository
- See <https://support.tokbox.com/> for support options
- Tweet at us! We're [@VonageDev on Twitter](https://twitter.com/VonageDev)
- Or [join the Vonage Developer Community Slack](https://developer.nexmo.com/community/slack)
