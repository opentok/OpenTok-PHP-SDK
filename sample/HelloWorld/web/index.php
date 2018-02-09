<?php

$autoloader = __DIR__.'/../vendor/autoload.php';
if (!file_exists($autoloader)) {
  die('You must run `composer install` in the sample app directory');
}

require($autoloader);

use Slim\Slim;
use Gregwar\Cache\Cache;

use OpenTok\OpenTok;

// PHP CLI webserver compatibility, serving static files
$filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

// Verify that the API Key and API Secret are defined
if (!(getenv('API_KEY') && getenv('API_SECRET'))) {
    die('You must define an API_KEY and API_SECRET in the run-demo file');
}

// Initialize Slim application
$app = new Slim(array(
    'templates.path' => __DIR__.'/../templates'
));

// Intialize a cache, store it in the app container
$app->container->singleton('cache', function () {
    return new Cache;
});

// Initialize OpenTok instance, store it in the app contianer
$app->container->singleton('opentok', function () {
    return new OpenTok(getenv('API_KEY'), getenv('API_SECRET'));
});
// Store the API Key in the app container
$app->apiKey = getenv('API_KEY');

// Configure routes
$app->get('/', function () use ($app) {

    // If a sessionId has already been created, retrieve it from the cache
    $sessionId = $app->cache->getOrCreate('sessionId', array(), function () use ($app) {
        // If the sessionId hasn't been created, create it now and store it
        $session = $app->opentok->createSession();
        return $session->getSessionId();
    });

    // Generate a fresh token for this client
    $token = $app->opentok->generateToken($sessionId);

    $app->render('helloworld.php', array(
        'apiKey' => $app->apiKey,
        'sessionId' => $sessionId,
        'token' => $token
    ));
});

$app->run();
