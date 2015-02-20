# OpenTok Hello World PHP

This is a simple demo app that shows how you can use the OpenTok-PHP-SDK to create Sessions,
generate Tokens with those Sessions, and then pass these values to a JavaScript client that can
connect and conduct a group chat.

## Running the App

First, download the dependencies using [Composer](http://getcomposer.org) in this directory.

```
$ ../../composer.phar install
```

Next, input your own API Key and API Secret into the `run-demo` script file:

```
  export API_KEY=0000000
  export API_SECRET=abcdef1234567890abcdef01234567890abcdef
```

Finally, start the PHP CLI development server (requires PHP >= 5.4) using the `run-demo` script

```
$ ./run-demo
```

Visit <http://localhost:8080> in your browser. Open it again in a second window. Smile! You've just
set up a group chat.

## Walkthrough

This demo application uses the [Slim PHP micro-framework](http://www.slimframework.com/) and
a [lightweight caching library](https://github.com/Gregwar/Cache). These are similar to many other
popular web frameworks and data caching/storage software. These concepts won't be explained but can
be explore further at each of the websites linked above.

### Main Controller (web/index.php)

The first thing done in this file is to require the autoloader which pulls in all the dependencies
that were installed by Composer. We now have the Slim framework, the Cache library, and most
importantly the OpenTok SDK available.

```php
require($autoloader);

use Slim\Slim;
use Gregwar\Cache\Cache;

use OpenTok\OpenTok;
```

Next the controller performs some basic checks on the environment, initializes the Slim application
(`$app`), and sets up the cache to be stored in the application's container (`$app->cache`).

The first thing that we do with OpenTok is to initialize an instance and also store it in the
application container. At the same time, we also store the apiKey separately so that we can access
it on its own. Notice that we needed to get the `API_KEY` and `API_SECRET` from the environment
variables.

```php
// Initialize OpenTok instance, store it in the app contianer
$app->container->singleton('opentok', function () {
    return new OpenTok(getenv('API_KEY'), getenv('API_SECRET'));
});
// Store the API Key in the app container
$app->apiKey = getenv('API_KEY');
```

Now we are ready to configure some routes. We only need one GET route for the root path because this
application only has one page. Inside the route handler, we query the cache to see if we have stored
a `sessionId` previously. The reason we use a cache in this application is because we want to generate
a session only once, no matter how many times the page is loaded, so that all visitors can join the
same OpenTok Session. In other applications, it would be common to save the `sessionId` in a database
table. If the cache does not have a `sessionId` stored, like on the first run of the application, we
use the stored OpenTok instance to create a Session. When we return its `sessionId`, that will be
stored in the cache for later use.

**NOTE:** in order to clear the cache, just delete the cache folder created in your demo app directory.

```php
// If a sessionId has already been created, retrieve it from the cache
$sessionId = $app->cache->getOrCreate('sessionId', array(), function() use ($app) {
    // If the sessionId hasn't been created, create it now and store it
    $session = $app->opentok->createSession();
    return $session->getSessionId();
});
```

Next inside the route handler, we generate a Token, so the client has permission to connect to that
Session. This is again done by accessing the stored OpenTok instance. Since the token is not cached,
a fresh one is generated each time.

```php
// Generate a fresh token for this client
$token = $app->opentok->generateToken($sessionId);
```

Lastly, we load a template called `helloworld.php` in the `templates/` directory, and pass in the
three values needed for a client to connect to a Session: `apiKey`, `sessionId`, and `token`.

```php
$app->render('helloworld.php', array(
    'apiKey' => $app->apiKey,
    'sessionId' => $sessionId,
    'token' => $token
));
```

### Main Template (templates/helloworld.php)

This file simply sets up the HTML page for the JavaScript application to run, imports the
JavaScript library, and passes the values created by the server into the JavaScript application
inside `web/js/helloworld.js`

### JavaScript Applicaton (web/js/helloworld.js)

The group chat is mostly implemented in this file. At a high level, we connect to the given
Session, publish a stream from our webcam, and listen for new streams from other clients to
subscribe to.

For more details, read the comments in the file or go to the
[JavaScript Client Library](http://tokbox.com/opentok/libraries/client/js/) for a full reference.
