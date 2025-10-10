<?php

$autoloader = __DIR__ . '/../vendor/autoload.php';
$sdkAutoloader = __DIR__ . '/../../../vendor/autoload.php';

if (!file_exists($autoloader)) {
    die('You must run `composer install` in the sample app directory');
}

if (!file_exists($sdkAutoloader)) {
    die('You must run `composer install` in the SDK root directory');
}

require($autoloader);
require($sdkAutoloader);

use Slim\Slim;
use Gregwar\Cache\Cache;

use OpenTok\OpenTok;
use OpenTok\MediaMode;

// PHP CLI webserver compatibility, serving static files
$filename = __DIR__ . preg_replace('#(\?.*)$#', '', (string) $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

// Verify that the API Key and API Secret are defined
if (!(getenv('API_KEY') && getenv('API_SECRET') && getenv('SIP_URI') && getenv('SIP_SECURE'))) {
    die('You must define API_KEY, API_SECRET, SIP_URI, and SIP_SECURE in the run-demo file');
}

// Initialize Slim application
$app = new Slim(['templates.path' => __DIR__ . '/../templates']);

// Intialize a cache, store it in the app container
$app->container->singleton('cache', fn(): Cache => new Cache());

// Initialize OpenTok instance, store it in the app contianer
$app->container->singleton('opentok', fn(): OpenTok => new OpenTok(getenv('API_KEY'), getenv('API_SECRET')));
// Store the API Key in the app container
$app->apiKey = getenv('API_KEY');

$app->sip = ['uri' => getenv('SIP_URI'), 'username' => getenv('SIP_USERNAME'), 'password' => getenv('SIP_PASSWORD'), 'secure' => (getenv('SIP_SECURE') === 'true'), 'from' => getenv('SIP_FROM')];

// Configure routes
$app->get('/', function () use ($app): void {
    // If a sessionId has already been created, retrieve it from the cache
    $sessionId = $app->cache->getOrCreate('sessionId', [], function () use ($app) {
        // If the sessionId hasn't been created, create it now and store it
        $session = $app->opentok->createSession(['mediaMode' => MediaMode::ROUTED]);
        return $session->getSessionId();
    });

    // Generate a fresh token for this client
    $token = $app->opentok->generateToken($sessionId, ['role' => 'moderator']);

    $app->render('index.php', ['apiKey' => $app->apiKey, 'sessionId' => $sessionId, 'token' => $token]);
});

$app->post('/sip/start', function () use ($app): void {
    $sessionId = $app->request->post('sessionId');

    // generate a token
    $token = $app->opentok->generateToken($sessionId, ['data' => 'sip=true']);

    // create the options parameter
    $options = ['secure' => $app->sip['secure'], 'from' => $app->sip['from']];
    if ($app->sip['username'] !== false) {
        $options['auth'] = ['username' => $app->sip['username'], 'password' => $app->sip['password']];
    }

    // make the sip call
    $sipCall = $app->opentok->dial($sessionId, $token, $app->sip['uri'], $options);

    echo $sipCall->toJson();
});

$app->run();
