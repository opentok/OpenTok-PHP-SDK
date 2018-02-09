<?php

$autoloader = __DIR__.'/../vendor/autoload.php';
if (!file_exists($autoloader)) {
  die('You must run `composer install` in the sample app directory');
}

require($autoloader);

// remove this before push
$autoloader2 = __DIR__.'/../../../vendor/autoload.php';
require($autoloader2);

use Slim\Slim;
use Gregwar\Cache\Cache;

use OpenTok\OpenTok;
use OpenTok\MediaMode;

// PHP CLI webserver compatibility, serving static files
$filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

// Verify that the API Key and API Secret are defined
if (!(getenv('API_KEY') && getenv('API_SECRET') && getenv('SIP_URI') && getenv('SIP_SECURE'))) {
    die('You must define API_KEY, API_SECRET, SIP_URI, and SIP_SECURE in the run-demo file');
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

$app->sip = array(
  'uri' => getenv('SIP_URI'),
  'username' => getenv('SIP_USERNAME'),
  'password' => getenv('SIP_PASSWORD'),
  'secure' => (getenv('SIP_SECURE') === 'true'),
  'from' => getenv('SIP_FROM'),
);

// Configure routes
$app->get('/', function () use ($app) {
    // If a sessionId has already been created, retrieve it from the cache
    $sessionId = $app->cache->getOrCreate('sessionId', array(), function () use ($app) {
        // If the sessionId hasn't been created, create it now and store it
        $session = $app->opentok->createSession(array('mediaMode' => MediaMode::ROUTED));
        return $session->getSessionId();
    });

    // Generate a fresh token for this client
    $token = $app->opentok->generateToken($sessionId, array('role' => 'moderator'));

    $app->render('index.php', array(
        'apiKey' => $app->apiKey,
        'sessionId' => $sessionId,
        'token' => $token
    ));
});

$app->post('/sip/start', function () use ($app) {
    $sessionId = $app->request->post('sessionId');

    // generate a token
    $token = $app->opentok->generateToken($sessionId, array('data' => 'sip=true'));

    // create the options parameter
    $options = array(
      'secure' => $app->sip['secure'],
      'from' => $app->sip['from'],
    );
    if ($app->sip['username'] !== false) {
        $options['auth'] = array('username' => $app->sip['username'], 'password' => $app->sip['password']);
    }

    // make the sip call
    $sipCall = $app->opentok->dial($sessionId, $token, $app->sip['uri'], $options);

    echo $sipCall->toJson();
});

$app->run();
