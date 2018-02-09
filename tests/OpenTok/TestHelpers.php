<?php

namespace OpenTok;

use GuzzleHttp\Psr7\Response;
use \Firebase\JWT\JWT;

class TestHelpers {
    // TODO: untested, unused
    public static function decodeSessionId($sessionId)
    {
        $trimmedSessionId = substr($sessionId, 2);
        $parts = explode('-', $trimmedSessionId);
        $data = array();
        foreach($parts as $part) {
            $decodedPart = base64_decode($part);
            $dataItems = explode('~', $decodedPart);
            $data = array_merge($data, $dataItems);
        }
        return $data;
    }

    public static function decodeToken($token)
    {
        $trimmedToken = substr($token, 4); // removes T1==
        $decodedToken = base64_decode($trimmedToken);
        $parts = explode(':', $decodedToken); // splits into partner info and data string
        parse_str($parts[0], $parsedPartnerInfo);
        parse_str($parts[1], $parsedDataString);
        return array_merge($parsedPartnerInfo, $parsedDataString, array(
            'dataString' => $parts[1]
        ));
    }

    public static function validateOpenTokAuthHeader($apiKey, $apiSecret, $token)
    {
        if (!isset($token)) {
            return false;
        }

        try {
            $decodedToken = JWT::decode($token, $apiSecret, array('HS256'));
        } catch(\Exception $e) {
            return false;
        }

        if (!property_exists($decodedToken, 'iss') || $decodedToken->iss !== $apiKey) {
            return false;
        }

        if (!property_exists($decodedToken, 'ist') || 'project' !== $decodedToken->ist) {
            return false;
        }

        if (!property_exists($decodedToken, 'exp') || time() >= $decodedToken->exp) {
            return false;
        }

        if (!property_exists($decodedToken, 'jti')) {
            return false;
        }

        return true;
    }

    public static function mocksToResponses($mocks, $basePath)
    {
        return array_map(function ($mock) use ($basePath) {
            $code = !empty($mock['code']) ? $mock['code'] : 200;
            $headers = !empty($mock['headers']) ? $mock['headers'] : [];
            $body = null;
            if (!empty($mock['body'])) {
                $body = $mock['body'];
            } else if (!empty($mock['path'])) {
                $body = file_get_contents($basePath . $mock['path']);
            }
            return new Response($code, $headers, $body);
        }, $mocks);
    }
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
