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
        $request->addHeader('X-OPENTOK-AUTH', $this->createAuthHeader());
    }

    private function createAuthHeader()
    {
        $token = array(
            'ist' => 'project',
            'iss' => $this->apiKey,
            'iat' => time(), // this is in seconds
            'exp' => time()+(5 * 60),
            'jti' => uniqid(),
        );
        return JWT::encode($token, $this->apiSecret);
    }
}
