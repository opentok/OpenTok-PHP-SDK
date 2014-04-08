# OpenTok PHP SDK

[![Build Status](https://travis-ci.org/opentok/Opentok-PHP-SDK.svg?branch=modernization)](https://travis-ci.org/opentok/Opentok-PHP-SDK)

The OpenTok PHP SDK lets you generate [sessions](http://tokbox.com/opentok/tutorials/create-session/) and
[tokens](http://tokbox.com/opentok/tutorials/create-token/) for [OpenTok](http://www.tokbox.com/) applications.
This version of the SDK also includes support for working with OpenTok 2.0 archives.

# Installation

## Composer (recommended):

Composer helps manage dependencies for PHP projects. Find more info here: <http://getcomposer.org>

Add this package (`opentok/opentok`) to your `composer.json` file, or just run the following at the
command line:

```
$ composer require opentok/opentok 2.2.x
```

## Manually:

Download the latest release from the [Releases](https://github.com/opentok/Opentok-PHP-SDK/releases)
page. Extract the files into a directory inside your project.

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

To create an OpenTok Session, use the `createSession($properties)` method of the
`OpenTok\OpenTok` class. The `$options` parameter is an optional array used to specify whether you
are creating a p2p Session and specifying a location hint. The `getSessionId()` method of the
`OpenTok\Session` instance is useful to get a sessionId that can be saved to a persistent store
(e.g. database).

```php
// Just a plain Session
$session = $openTok->createSession();
// A p2p Session
$session = $openTok->createSession(array( 'p2p' => true ));
// A Session with a location hint
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
// Generate a Token by callin the method on the Session (returned from createSession)
$token = $session->generateToken();

// Set some options in a token
$token = $session->generateToken(array(
    'role'       => Role::MODERATOR,
    'expireTime' => time()+(7 * 24 * 60 * 60) // in one week
    'data'       => 'name=Johnny'
));
```

## Working with Archives

You can start the recording of an OpenTok Session using the `startArchive($sessionId, $name)` method
of the `OpenTok\OpenTok` class. This will return an `OpenTok\Archive` instance. The parameter
`$name` is optional and is used to assign a name for the Archive. Note that you can only start an
Archive on a Session that has clients connected.

```php
$archive = $opentok->startArchive($sessionId);

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

To get an `OpenTok\Archive` instance (and all the information about it) from an archiveId, use the
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
`$offset` and `$count` are optional and can help you paginate through the results. This wil return
an instance of the `OpenTok\ArchiveList` class.

```php
$archiveList = $opentok->listArchives();

// Get an array of OpenTok\Archive instances
$archives = $archiveList->items();
// Get the total number of Archives for this API Key
$totalCount = $archiveList->totalCount();
```

# Documentation

TODO: Reference documentation is available at http://opentok.github.io/opentok-php-sdk/

# Requirements

You need an OpenTok API key and API secret, which you can obtain at <https://dashboard.tokbox.com>.

The OpenTok PHP SDK requires PHP 5.3 or greater.

# Release Notes

TODO: See the [Releases](https://github.com/opentok/opentok-php-sdk/releases) page for details about each
release.

## Important changes in v2.0

This version of the SDK includes support for working with OpenTok 2.0 archives. (This API does not work
with OpenTok 1.0 archives.)

# Development and Contributing

Interested in contributing? We <3 pull requests! File a new
[Issue](https://github.com/opentok/opentok-php-sdk/issues) or take a look at the existing ones. If
you are going to send us a pull request, please try to run the test suite first and also include
tests for your changes.

# Support

See http://tokbox.com/opentok/support/ for all our support options.

Find a bug? File it on the [Issues](https://github.com/opentok/opentok-php-sdk/issues) page. Hint:
test cases are really helpful!
