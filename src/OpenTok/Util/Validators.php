<?php

namespace OpenTok\Util;

use OpenTok\Util\Client;
use OpenTok\Role;
use OpenTok\MediaMode;
use OpenTok\OpenTok;

use OpenTok\Exception\InvalidArgumentException;

use JohnStevenson\JsonWorks\Document;
use JohnStevenson\JsonWorks\Utils as JsonUtils;

/**
* @internal
*/
class Validators
{
    static $guidRegEx = '/^\[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}\$/';
    static $schemaStore;
    static $schemaUri;

    public static function validateApiKey($apiKey)
    {
        if (!(is_string($apiKey) || is_int($apiKey))) {
            throw new InvalidArgumentException(
                'The apiKey was not a string nor an integer: '.print_r($apiKey, true)
            );
        }
    }
    public static function validateApiSecret($apiSecret)
    {
        if (!(is_string($apiSecret))) {
            throw new InvalidArgumentException('The apiSecret was not a string: '.print_r($apiSecret, true));
        }
    }
    public static function validateApiUrl($apiUrl)
    {
        if (!(is_string($apiUrl) && filter_var($apiUrl, FILTER_VALIDATE_URL))) {
            throw new InvalidArgumentException(
                'The optional apiUrl was not a string: '.print_r($apiUrl, true)
            );
        }
    }
    public static function validateClient($client)
    {
        if (isset($client) && !($client instanceof Client)) {
            throw new InvalidArgumentException(
                'The optional client was not an instance of \OpenTok\Util\Client. client:'.print_r($client, true)
            );
        }
    }
    public static function validateSessionId($sessionId)
    {
        if(!is_string($sessionId) || empty($sessionId)){
            throw new InvalidArgumentException(
                'Null or empty session ID is not valid: '.print_r($sessionId, true)
            );
        }
    }
    public static function validateRole($role)
    {
        if (!Role::isValidValue($role)) {
            throw new InvalidArgumentException('Unknown role: '.print_r($role, true));
        }
    }
    public static function validateExpireTime($expireTime, $createTime)
    {
        if(!is_null($expireTime)) {
            if(!is_numeric($expireTime)) {
                throw new InvalidArgumentException(
                    'Expire time must be a number: '.print_r($expireTime, true)
                );
            }
            if($expireTime < $createTime) {
                throw new InvalidArgumentException(
                    'Expire time must be in the future: '.print_r($expireTime, true).'<'.print_r($createTime, true)
                );
            }
            $in30Days = $createTime + 2592000;
            if($expireTime > $in30Days) {
                throw new InvalidArgumentException(
                    'Expire time must be in the next 30 days: '.print_r($expireTime, true).'>'.$in30Days
                );
            }
        }
    }
    public static function validateData($data)
    {
        if ($data != null) {
            if (!is_string($data)) {
                throw new InvalidArgumentException(
                    'Connection data must be a string. data:'.print_r($data, true)
                );
            }
            if(!empty($data)) {
                $dataLength = strlen($data);
                if($dataLength > 1000) {
                    throw new InvalidArgumentException(
                        'Connection data must be less than 1000 characters. Length: '.$dataLength
                    );
                }
            }
        }
    }
    public static function validateArchiveName($name)
    {
        if ($name != null && !is_string($name) /* TODO: length? */) {
            throw new InvalidArgumentException(
                'The name was not a string: '.print_r($name, true)
            );
        }
    }
    public static function validateArchiveId($archiveId)
    {
        if ( !is_string($archiveId) || preg_match(self::$guidRegEx, $archiveId) ) {
            throw new InvalidArgumentException(
                'The archiveId was not valid. archiveId:'.print_r($archiveId, true)
            );
        }
    }
    public static function validateArchiveData($archiveData)
    {
        if (!self::$schemaUri) { self::$schemaUri = realpath(__DIR__.'/archive-schema.json'); }
        $document = new Document();
        // have to do a encode+decode so that json objects decoded as arrays from Guzzle
        // are re-encoded as objects instead
        $document->loadData(json_decode(json_encode($archiveData)));
        $document->loadSchema(self::$schemaUri);
        // JSON Pointers are supported for the validation using this library, this is a hack
        $document->loadSchema(JsonUtils::get(JsonUtils::get($document->schema->data, 'definitions'), 'archive'));
        if (!$document->validate()) {
            throw new InvalidArgumentException(
                'The archive data provided is not valid. Errors:'.$document->lastError.' archiveData:'.print_r($archiveData, true)
            );
        }
    }
    public static function validateArchiveListData($archiveListData)
    {
        if (!self::$schemaUri) { self::$schemaUri = realpath(__DIR__.'/archive-schema.json'); }
        $document = new Document();
        // have to do a encode+decode so that json objects decoded as arrays from Guzzle
        // are re-encoded as objects instead
        $document->loadData(json_decode(json_encode($archiveListData)));
        $document->loadSchema(self::$schemaUri);
        if (!$document->validate()) {
            throw new InvalidArgumentException(
                'The archive data provided is not valid. Errors:'.$document->lastError.' archiveData:'.print_r($archiveData, true)
            );
        }
    }
    public static function validateOffsetAndCount($offset, $count)
    {
        if ((!is_numeric($offset) || $offset < 0 ) ||
            (($count != null && !is_numeric($count)) || $count < 0 || $count > 1000) ) {

            throw new InvalidArgumentException(
                'The offset or count were not valid numbers: offset='.print_r($offset, true).' count='.print_r($count, true)
            );
        }
    }
    public static function validateSessionIdBelongsToKey($sessionId, $apiKey)
    {
        self::validateSessionId($sessionId);
        $sessionIdParts = self::decodeSessionId($sessionId);
        if (!in_array($apiKey, $sessionIdParts)) {
            throw new InvalidArgumentException(
                'The sessionId must belong to the apiKey. sessionId: '.print_r($sessionId, true).', apiKey: '.print_r($apiKey, true)
            );
        }
    }
    public static function validateMediaMode($mediaMode)
    {
        if (!MediaMode::isValidValue($mediaMode)) {
            throw new InvalidArgumentException(
                'The media mode option must be either \'MediaMode::ROUTED\' or \'MediaMode::RELAYED\'. mediaMode:'.print_r($mediaMode, true)
            );
        }
    }
    public static function validateLocation($location)
    {
        if ($location != null && !filter_var($location, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new InvalidArgumentException(
                'The location option must be an IPv4 address. location:'.print_r($location, true)
            );
        }
    }
    public static function validateOpenTok($opentok)
    {
        if (!($opentok instanceof OpenTok)) {
            throw new InvalidArgumentException(
                'The opentok parameter must be an instance of OpenTok\OpenTok. opentok:'.print_r($opentok, true)
            );
        }
    }

    // Helpers

    protected static function decodeSessionId($sessionId)
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
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
