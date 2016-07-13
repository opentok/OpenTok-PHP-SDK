<?php

namespace OpenTok;

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

    public static function decodeToken($token, $secret)
    {
        try {
          $decodedToken = JWT::decode($token, $secret, array('HS256'));
        } catch (Exception $e) {
          $this->fail("Failed to decode token");
        }
        return $decodedToken;
    }
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
