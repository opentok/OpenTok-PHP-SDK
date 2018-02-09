<?php

$autoloader = __DIR__.'/../vendor/autoload.php';
if (!file_exists($autoloader)) {
  die('You must run `composer install` in the sample app directory');
}

require($autoloader);

use Slim\Slim;
use Gregwar\Cache\Cache;

use OpenTok\OpenTok;
use OpenTok\Role;
use OpenTok\MediaMode;
use OpenTok\OutputMode;

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
    'templates.path' => __DIR__.'/../templates',
    'view' => new \Slim\Views\Twig()
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

// If a sessionId has already been created, retrieve it from the cache
$sessionId = $app->cache->getOrCreate('sessionId', array(), function () use ($app) {
    // If the sessionId hasn't been created, create it now and store it
    $session = $app->opentok->createSession(array(
      'mediaMode' => MediaMode::ROUTED
    ));
    return $session->getSessionId();
});

// Configure routes
$app->get('/', function () use ($app) {
    $app->render('index.html');
});

$app->get('/host', function () use ($app, $sessionId) {

    $token = $app->opentok->generateToken($sessionId, array(
        'role' => Role::MODERATOR
    ));

    $app->render('host.html', array(
        'apiKey' => $app->apiKey,
        'sessionId' => $sessionId,
        'token' => $token
    ));
});

$app->get('/participant', function () use ($app, $sessionId) {

    $token = $app->opentok->generateToken($sessionId, array(
        'role' => Role::MODERATOR
    ));

    $app->render('participant.html', array(
        'apiKey' => $app->apiKey,
        'sessionId' => $sessionId,
        'token' => $token
    ));
});

$app->get('/history', function () use ($app) {
    $page = intval($app->request->get('page'));
    if (empty($page)) {
        $page = 1;
    }

    $offset = ($page - 1) * 5;

    $archives = $app->opentok->listArchives($offset, 5);

    $toArray = function ($archive) {
      return $archive->toArray();
    };

    $app->render('history.html', array(
        'archives' => array_map($toArray, $archives->getItems()),
        'showPrevious' => $page > 1 ? '/history?page='.($page-1) : null,
        'showNext' => $archives->totalCount() > $offset + 5 ? '/history?page='.($page+1) : null
    ));
});

$app->get('/download/:archiveId', function ($archiveId) use ($app) {
    $archive = $app->opentok->getArchive($archiveId);
    $app->redirect($archive->url);
});

$app->post('/start', function () use ($app, $sessionId) {

    $archive = $app->opentok->startArchive($sessionId, array(
      'name' => "PHP Archiving Sample App",
      'hasAudio' => ($app->request->post('hasAudio') == 'on'),
      'hasVideo' => ($app->request->post('hasVideo') == 'on'),
      'outputMode' => ($app->request->post('outputMode') == 'composed' ? OutputMode::COMPOSED : OutputMode::INDIVIDUAL)
    ));

    $app->response->headers->set('Content-Type', 'application/json');
    echo $archive->toJson();
});

$app->get('/stop/:archiveId', function ($archiveId) use ($app) {
    $archive = $app->opentok->stopArchive($archiveId);
    $app->response->headers->set('Content-Type', 'application/json');
    echo $archive->toJson();
});

$app->get('/delete/:archiveId', function ($archiveId) use ($app) {
    $app->opentok->deleteArchive($archiveId);
    $app->redirect('/history');
});

$app->run();
