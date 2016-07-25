<?php

namespace OpenTok\Util\Plugin;

use \Firebase\JWT\JWT;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Guzzle\Common\Event;

/**
* @internal
*/
class OpentokAuth implements EventSubscriberInterface
{
    protected $apiKey;
    protected $apiSecret;

    public static function getSubscribedEvents()
    {
        return array('request.before_send' => 'onBeforeSend');
    }

    public function __construct($apiKey, $apiSecret)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    public function onBeforeSend(Event $event)
    {
        $request = $event['request'];
        $sessionId = null;
        if (method_exists($request, 'getBody')) {
          $body = json_decode($request->getBody());
          if ($body !== null && property_exists($body, 'sessionId')) {
            $sessionId = $body->sessionId;
          }
        }

        $request->addHeader('X-OPENTOK-AUTH', $this->createAuthHeader($sessionId));
    }

    private function createAuthHeader($sessionId = null)
    {
        $token = array(
            'ist' => 'project',
            'iss' => $this->apiKey,
            'iat' => time(), // this is in seconds
            'exp' => time()+(5 * 60),
            'jti' => uniqid(),
        );

        if ($sessionId !== null) {
          $token['sub'] = $sessionId;
        }

        return JWT::encode($token, $this->apiSecret);
    }
}
