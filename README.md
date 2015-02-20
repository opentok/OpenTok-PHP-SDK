# OpenTok PHP SDK

[![Build Status](https://travis-ci.org/opentok/OpenTok-PHP-SDK.svg)](https://travis-ci.org/opentok/OpenTok-PHP-SDK)

The OpenTok PHP SDK lets you generate [sessions](http://tokbox.com/opentok/tutorials/create-session/) and
[tokens](http://tokbox.com/opentok/tutorials/create-token/) for [OpenTok](http://www.tokbox.com/)
applications, and [archive](http://tokbox.com/#archiving) OpenTok 2.0 sessions.

If you are updating from a previous version of this SDK, see
[Important changes since v2.2.0](#important-changes-since-v220).

# Installation

## Composer (recommended):

Composer helps manage dependencies for PHP projects. Find more info here: <http://getcomposer.org>

Add this package (`opentok/opentok`) to your `composer.json` file, or just run the following at the
command line:

```
$ ./composer.phar require opentok/opentok 2.2.x
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

* Setting whether the session will use the OpenTok Media Router or attempt send streams directly
  between clients.

* Specifying a location hint.

The `getSessionId()` method of the `OpenTok\Session` instance returns the session ID,
which you use to identify the session in the OpenTok client libraries.

```php
use OpenTok\MediaMode;

// Create a session that attempts to use peer-to-peer streaming:
$session = $openTok->createSession();
// A session that uses the OpenTok Media Router:
$session = $openTok->createSession(array( 'mediaMode' => MediaMode::ROUTED ));
// A session with a location hint:
$session = $openTok->createSession(array( 'location' => '12.34.56.78' ));

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
$token = OpenTok->generateToken($sessionId);
// Generate a Token by calling the method on the Session (returned from createSession)
$token = $session->generateToken();

// Set some options in a token
$token = $session->generateToken(array(
    'role'       => Role::MODERATOR,
    'expireTime' => time()+(7 * 24 * 60 * 60), // in one week
    'data'       => 'name=Johnny'
));
```

## Working with Archives

You can start the recording of an OpenTok Session using the `startArchive($sessionId, $name)` method
of the `OpenTok\OpenTok` class. This will return an `OpenTok\Archive` instance. The parameter
`$name` is optional and is used to assign a name for the Archive. Note that you can only start an
Archive on a Session that has clients connected.

```php
$archive = $opentok->startArchive($sessionId, $name);

// Store this archiveId in the database for later use
$archiveId = $archive->id;
```

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

# Samples

There are two sample applications included in this repository. To get going as fast as possible, clone the whole
repository and follow the Walkthroughs:

*  [HelloWorld](sample/HelloWorld/README.md)
*  [Archiving](sample/Archiving/README.md)

# Documentation

Reference documentation is available at <http://www.tokbox.com/opentok/libraries/server/php/reference/index.html> and in the
docs directory of the SDK.

# Requirements

You need an OpenTok API key and API secret, which you can obtain at <https://dashboard.tokbox.com>.

The OpenTok PHP SDK requires PHP 5.3 or greater.

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

This version of the SDK includes support for working with OpenTok 2.0 archives. (This API does not
work with OpenTok 1.0 archives.)

The names of many methods of the API have changed. Many method names have
changed to use camel case, including the following:

* OpenTok.createSession()
* OpenTok.generateToken()

Note also that the `options` parameter of the `OpenTok.createSession()` method has a `mediaMode`
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
