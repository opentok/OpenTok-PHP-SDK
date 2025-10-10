<?php

namespace OpenTokTest;

use Exception;
use Firebase\JWT\Key;
use GuzzleHttp\Psr7\Response;
use \Firebase\JWT\JWT;

class TestHelpers
{
    // TODO: untested, unused
    /**
     * @return mixed[]
     */
    public static function decodeSessionId($sessionId): array
    {
        $trimmedSessionId = substr((string) $sessionId, 2);
        $parts = explode('-', $trimmedSessionId);
        $data = [];
        foreach ($parts as $part) {
            $decodedPart = base64_decode($part);
            $dataItems = explode('~', $decodedPart);
            $data = array_merge($data, $dataItems);
        }
        return $data;
    }

    public static function decodeToken($token): array
    {
        $trimmedToken = substr((string) $token, 4); // removes T1==
        $decodedToken = base64_decode($trimmedToken);
        $parts = explode(':', $decodedToken); // splits into partner info and data string
        parse_str($parts[0], $parsedPartnerInfo);
        parse_str($parts[1], $parsedDataString);
        return array_merge($parsedPartnerInfo, $parsedDataString, ['dataString' => $parts[1]]);
    }

    public static function validateOpenTokAuthHeader($apiKey, $apiSecret, $token)
    {
        if (!isset($token)) {
            return false;
        }

        try {
            $decodedToken = JWT::decode($token, new Key($apiSecret, 'HS256'));
        } catch (Exception) {
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
        return property_exists($decodedToken, 'jti');
    }

    public static function mocksToResponses($mocks, $basePath): array
    {
        return array_map(function ($mock) use ($basePath): Response {
            $code = empty($mock['code']) ? 200 : $mock['code'];
            $headers = empty($mock['headers']) ? [] : $mock['headers'];
            $body = null;
            if (!empty($mock['body'])) {
                $body = $mock['body'];
            } elseif (!empty($mock['path'])) {
                $body = file_get_contents($basePath . $mock['path']);
            }
            return new Response($code, $headers, $body);
        }, $mocks);
    }
}

