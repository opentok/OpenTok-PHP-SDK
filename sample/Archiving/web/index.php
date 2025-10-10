<?php

use Slim\Views\Twig;

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
use OpenTok\Role;
use OpenTok\MediaMode;
use OpenTok\OutputMode;

// PHP CLI webserver compatibility, serving static files
$filename = __DIR__ . preg_replace('#(\?.*)$#', '', (string) $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

// Verify that the API Key and API Secret are defined
if (!(getenv('API_KEY') && getenv('API_SECRET'))) {
    die('You must define an API_KEY and API_SECRET in the run-demo file');
}

// Initialize Slim application
$app = new Slim(['templates.path' => __DIR__ . '/../templates', 'view' => new Twig()]);

// Intialize a cache, store it in the app container
$app->container->singleton('cache', fn(): Cache => new Cache());

// Initialize OpenTok instance, store it in the app contianer
$app->container->singleton('opentok', fn(): OpenTok => new OpenTok(getenv('API_KEY'), getenv('API_SECRET')));
// Store the API Key in the app container
$app->apiKey = getenv('API_KEY');

// If a sessionId has already been created, retrieve it from the cache
$sessionId = $app->cache->getOrCreate('sessionId', [], function () use ($app) {
    // If the sessionId hasn't been created, create it now and store it
    $session = $app->opentok->createSession(['mediaMode' => MediaMode::ROUTED]);
    return $session->getSessionId();
});

// Configure routes
$app->get('/', function () use ($app): void {
    $app->render('index.html');
});

$app->get('/host', function () use ($app, $sessionId): void {

    $token = $app->opentok->generateToken($sessionId, ['role' => Role::MODERATOR]);

    $app->render('host.html', ['apiKey' => $app->apiKey, 'sessionId' => $sessionId, 'token' => $token]);
});

$app->get('/participant', function () use ($app, $sessionId): void {

    $token = $app->opentok->generateToken($sessionId, ['role' => Role::MODERATOR]);

    $app->render('participant.html', ['apiKey' => $app->apiKey, 'sessionId' => $sessionId, 'token' => $token]);
});

$app->get('/history', function () use ($app): void {
    $page = intval($app->request->get('page'));
    if ($page === 0) {
        $page = 1;
    }

    $offset = ($page - 1) * 5;

    $archives = $app->opentok->listArchives($offset, 5);

    $toArray = fn($archive) => $archive->toArray();

    $app->render('history.html', ['archives' => array_map($toArray, $archives->getItems()), 'showPrevious' => $page > 1 ? '/history?page=' . ($page - 1) : null, 'showNext' => $archives->totalCount() > $offset + 5 ? '/history?page=' . ($page + 1) : null]);
});

$app->get('/download/:archiveId', function ($archiveId) use ($app): void {
    $archive = $app->opentok->getArchive($archiveId);
    $app->redirect($archive->url);
});

$app->post('/start', function () use ($app, $sessionId): void {

    $archive = $app->opentok->startArchive($sessionId, ['name' => "PHP Archiving Sample App", 'hasAudio' => ($app->request->post('hasAudio') == 'on'), 'hasVideo' => ($app->request->post('hasVideo') == 'on'), 'outputMode' => ($app->request->post('outputMode') == 'composed' ? OutputMode::COMPOSED : OutputMode::INDIVIDUAL)]);

    $app->response->headers->set('Content-Type', 'application/json');
    echo $archive->toJson();
});

$app->get('/stop/:archiveId', function ($archiveId) use ($app): void {
    $archive = $app->opentok->stopArchive($archiveId);
    $app->response->headers->set('Content-Type', 'application/json');
    echo $archive->toJson();
});

$app->get('/delete/:archiveId', function ($archiveId) use ($app): void {
    $app->opentok->deleteArchive($archiveId);
    $app->redirect('/history');
});

$app->run();
