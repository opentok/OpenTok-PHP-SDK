<?php

namespace OpenTok\Util;

use OpenTok\Archive;
use OpenTok\Util\Client;
use OpenTok\Layout;
use OpenTok\Role;
use OpenTok\MediaMode;
use OpenTok\ArchiveMode;
use OpenTok\OutputMode;
use OpenTok\OpenTok;
use OpenTok\Exception\InvalidArgumentException;
use JohnStevenson\JsonWorks\Document;
use JohnStevenson\JsonWorks\Utils as JsonUtils;
use RuntimeException;

/**
* @internal
*/
class Validators
{
    public static $guidRegEx = '/^\[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}\$/';
    public static $archiveSchemaUri;
    public static $broadcastSchemaUri;

    public const STREAM_MODES = ['auto', 'manual'];

    public static function isVonageKeypair($apiKey, $apiSecret): bool
    {
        if (!is_string($apiKey) || !is_string($apiSecret)) {
            throw new InvalidArgumentException("API Key and API Secret must be strings.");
        }

        $isOpenTokKey = preg_match('/^\d+$/', $apiKey);
        $isOpenTokSecret = preg_match('/^[a-f0-9]{40}$/i', $apiSecret);

        if ($isOpenTokKey && $isOpenTokSecret) {
            return false;
        }

        $isVonageApplicationId = preg_match('/^[a-f0-9\-]{36}$/i', $apiKey);
        $isVonagePrivateKey = self::isValidPrivateKey($apiSecret);

        if ($isVonageApplicationId && $isVonagePrivateKey) {
            return true;
        }

        // Mixed formats or invalid formats - throw an exception
        throw new InvalidArgumentException("Invalid Vonage Keypair credentials provided.");
    }

    private static function isValidPrivateKey(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("Private key file does not exist or is not readable.");
        }

        $keyContents = file_get_contents($filePath);

        if ($keyContents === false) {
            throw new RuntimeException("Failed to read private key file.");
        }

        // Check if it contains a valid private RSA key header
        return (bool) preg_match('/^-----BEGIN PRIVATE KEY-----[\s\S]+-----END PRIVATE KEY-----$/m', trim($keyContents));
    }

    public static function validateForceMuteAllOptions(array $options)
    {
        $validOptions = [
            'excludedStreams' => 'array',
            'active' => 'boolean'
        ];

        foreach ($validOptions as $optionName => $optionType) {
            if (isset($options[$optionName])) {
                if (getType($options[$optionName]) !== $optionType) {
                    throw new InvalidArgumentException('Invalid type given in options for: ' . $options[$optionName]);
                }
            }
        }

        if (isset($options['excludedStreams'])) {
            foreach ($options['excludedStreams'] as $streamId) {
                self::validateStreamId($streamId);
            }
        }
    }

    public static function validateApiUrl($apiUrl)
    {
        if (!(is_string($apiUrl) && filter_var($apiUrl, FILTER_VALIDATE_URL))) {
            throw new InvalidArgumentException(
                'The optional apiUrl was not a string: ' . print_r($apiUrl, true)
            );
        }
    }

    public static function validateClient($client)
    {
        if (isset($client) && !($client instanceof Client)) {
            throw new InvalidArgumentException(
                'The optional client was not an instance of \OpenTok\Util\Client. client:' . print_r($client, true)
            );
        }
    }
    public static function validateSessionId($sessionId)
    {
        if (!is_string($sessionId) || empty($sessionId)) {
            throw new InvalidArgumentException(
                'Null or empty session ID is not valid: ' . print_r($sessionId, true)
            );
        }
    }
    public static function validateConnectionId($connectionId)
    {
        if (!is_string($connectionId) || empty($connectionId)) {
            throw new InvalidArgumentException(
                'Null or empty connection ID is not valid: ' . print_r($connectionId, true)
            );
        }
    }

    public static function validateHasStreamMode($streamMode)
    {
        if (!is_string($streamMode)) {
            throw new InvalidArgumentException(
                'The streamMode was not a string: ' . print_r($streamMode, true)
            );
        }

        if (!in_array($streamMode, self::STREAM_MODES)) {
            throw new InvalidArgumentException(
                'The streamMode was not valid: ' . print_r($streamMode, true)
            );
        }
    }

    public static function validateSignalPayload($payload)
    {
        list($type, $data) = array_values($payload);
        if (!is_string($data) || is_null($data || is_null($type))) {
            throw new InvalidArgumentException(
                'Signal Payload cannot be null: ' . print_r($payload, true)
            );
        }
    }
    public static function validateRole($role)
    {
        if (!Role::isValidValue($role)) {
            throw new InvalidArgumentException('Unknown role: ' . print_r($role, true));
        }
    }
    public static function validateExpireTime($expireTime, $createTime)
    {
        if (!is_null($expireTime)) {
            if (!is_numeric($expireTime)) {
                throw new InvalidArgumentException(
                    'Expire time must be a number: ' . print_r($expireTime, true)
                );
            }
            if ($expireTime < $createTime) {
                throw new InvalidArgumentException(
                    'Expire time must be in the future: ' . print_r($expireTime, true) . '<' . print_r($createTime, true)
                );
            }
            $in30Days = $createTime + 2592000;
            if ($expireTime > $in30Days) {
                throw new InvalidArgumentException(
                    'Expire time must be in the next 30 days: ' . print_r($expireTime, true) . '>' . $in30Days
                );
            }
        }
    }
    public static function validateData($data)
    {
        if ($data != null) {
            if (!is_string($data)) {
                throw new InvalidArgumentException(
                    'Connection data must be a string. data:' . print_r($data, true)
                );
            }
            if (!empty($data)) {
                $dataLength = strlen($data);
                if ($dataLength > 1000) {
                    throw new InvalidArgumentException(
                        'Connection data must be less than 1000 characters. Length: ' . $dataLength
                    );
                }
            }
        }
    }
    public static function validateArchiveName($name)
    {
        if ($name != null && !is_string($name) /* TODO: length? */) {
            throw new InvalidArgumentException(
                'The name was not a string: ' . print_r($name, true)
            );
        }
    }
    public static function validateArchiveHasVideo($hasVideo)
    {
        if (!is_bool($hasVideo)) {
            throw new InvalidArgumentException(
                'The hasVideo was not a boolean: ' . print_r($hasVideo, true)
            );
        }
    }
    public static function validateArchiveHasAudio($hasAudio)
    {
        if (!is_bool($hasAudio)) {
            throw new InvalidArgumentException(
                'The hasAudio was not a boolean: ' . print_r($hasAudio, true)
            );
        }
    }
    public static function validateArchiveOutputMode($outputMode)
    {
        if (!OutputMode::isValidValue($outputMode)) {
            throw new InvalidArgumentException('Unknown output mode: ' . print_r($outputMode, true));
        }
    }
    public static function validateArchiveId($archiveId)
    {
        if (!is_string($archiveId) || preg_match(self::$guidRegEx, $archiveId)) {
            throw new InvalidArgumentException(
                'The archiveId was not valid. archiveId:' . print_r($archiveId, true)
            );
        }
    }
    public static function validateArchiveData($archiveData)
    {
        if (!self::$archiveSchemaUri) {
            self::$archiveSchemaUri = __DIR__ . '/archive-schema.json';
        }
        $document = new Document();
        // have to do a encode+decode so that json objects decoded as arrays from Guzzle
        // are re-encoded as objects instead
        $document->loadData(json_decode(json_encode($archiveData)));
        $document->loadSchema(self::$archiveSchemaUri);
        // JSON Pointers are supported for the validation using this library, this is a hack
        $document->loadSchema(JsonUtils::get(JsonUtils::get($document->schema->data, 'definitions'), 'archive'));
        if (!$document->validate()) {
            throw new InvalidArgumentException(
                'The archive data provided is not valid. Errors:' . $document->lastError . ' archiveData:' . print_r($archiveData, true)
            );
        }
    }
    public static function validateArchiveListData($archiveListData)
    {
        if (!self::$archiveSchemaUri) {
            self::$archiveSchemaUri = __DIR__ . '/archive-schema.json';
        }
        $document = new Document();
        // have to do a encode+decode so that json objects decoded as arrays from Guzzle
        // are re-encoded as objects instead
        $document->loadData(json_decode(json_encode($archiveListData)));
        $document->loadSchema(self::$archiveSchemaUri);
        if (!$document->validate()) {
            throw new InvalidArgumentException(
                'The archive data provided is not valid. Errors:' . $document->lastError . ' archiveData:' . print_r($archiveListData, true)
            );
        }
    }
    public static function validateOffsetAndCount($offset, $count)
    {
        if (
            (!is_numeric($offset) || $offset < 0 ) ||
            (($count != null && !is_numeric($count)) || $count < 0 || $count > 1000)
        ) {
            throw new InvalidArgumentException(
                'The offset or count were not valid numbers: offset=' . print_r($offset, true) . ' count=' . print_r($count, true)
            );
        }
    }
    public static function validateSessionIdBelongsToKey($sessionId, $apiKey)
    {
        self::validateSessionId($sessionId);
        $sessionIdParts = self::decodeSessionId($sessionId);
        if (!in_array($apiKey, $sessionIdParts)) {
            throw new InvalidArgumentException(
                'The sessionId must belong to the apiKey. sessionId: ' . print_r($sessionId, true) . ', apiKey: ' . print_r($apiKey, true)
            );
        }
    }
    public static function validateMediaMode($mediaMode)
    {
        if (!MediaMode::isValidValue($mediaMode)) {
            throw new InvalidArgumentException(
                'The media mode option must be either \'MediaMode::ROUTED\' or \'MediaMode::RELAYED\'. mediaMode:' . print_r($mediaMode, true)
            );
        }
    }

    public static function validateArchiveMode($archiveMode)
    {
        if (!ArchiveMode::isValidValue($archiveMode)) {
            throw new InvalidArgumentException(
                'The archive mode option must be either \'ArchiveMode::MANUAL\' or \'ArchiveMode::ALWAYS\'. archiveMode:' . print_r($archiveMode, true)
            );
        }
    }

    public static function validateAutoArchiveMode($archiveMode, $options)
    {
        if ($archiveMode === ArchiveMode::MANUAL) {
            foreach (['archiveName', 'archiveResolution'] as $key) {
                if (array_key_exists($key, $options)) {
                    throw new InvalidArgumentException('Cannot set ' . $key . ' when Archive mode is Manual');
                }
            }
        }

        if (array_key_exists('archiveResolution', $options)) {
            self::validateAutoArchiveResolution($options['archiveResolution']);
        }
    }

    public static function validateLocation($location)
    {
        if ($location != null && !filter_var($location, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new InvalidArgumentException(
                'The location option must be an IPv4 address. location:' . print_r($location, true)
            );
        }
    }
    public static function validateOpenTok($opentok)
    {
        if (!($opentok instanceof OpenTok)) {
            throw new InvalidArgumentException(
                'The opentok parameter must be an instance of OpenTok\OpenTok. opentok:' . print_r($opentok, true)
            );
        }
    }

    public static function validateBroadcastData($broadcastData)
    {
        if (!self::$broadcastSchemaUri) {
            self::$broadcastSchemaUri = __DIR__ . '/broadcast-schema.json';
        }
        $document = new Document();
        // have to do a encode+decode so that json objects decoded as arrays from Guzzle
        // are re-encoded as objects instead
        $document->loadData(json_decode(json_encode($broadcastData)));
        $document->loadSchema(self::$broadcastSchemaUri);
        if (!$document->validate()) {
            throw new InvalidArgumentException(
                'The broadcast data provided is not valid. Errors:' . $document->lastError . ' broadcastData:' . print_r($broadcastData, true)
            );
        }
    }

    public static function validateRtmpStreams(array $rtmpData)
    {
        if (count($rtmpData) > 5) {
            throw new InvalidArgumentException('The maximum permitted RTMP Streams is set to 5');
        }
    }

    public static function validateBroadcastId($broadcastId)
    {
        if (!is_string($broadcastId) || preg_match(self::$guidRegEx, $broadcastId)) {
            throw new InvalidArgumentException(
                'The broadcastId was not valid. broadcastId:' . print_r($broadcastId, true)
            );
        }
    }

	public static function validateBroadcastOutputOptions(array $outputOptions)
	{
		if (
			isset($outputOptions['lowLatency'], $outputOptions['dvr'])
			&& $outputOptions['lowLatency'] === true && $outputOptions['dvr'] === true
		) {
			throw new InvalidArgumentException('When starting in HLS mode, DVR AND lowLatency
			cannot both be true');
		}
	}

    public static function validateLayout($layout)
    {
        if (!($layout instanceof Layout)) {
            throw new InvalidArgumentException(
                'The layout parameter must be an instance of OpenTok\Layout. layout:' . print_r($layout, true)
            );
        }
    }

    public static function validateLayoutStylesheet($stylesheet)
    {
        if (!(is_string($stylesheet))) {
            throw new InvalidArgumentException('The stylesheet was not a string: ' . print_r($stylesheet, true));
        }
    }

    public static function validateResolution($resolution)
    {
        if (!(is_string($resolution))) {
            throw new InvalidArgumentException('The resolution was not a string: ' . print_r($resolution, true));
        }

        $validResolutions = [
            '640x480',
            '1280x720',
            '1920x1080',
            '480x640',
            '720x1280',
            '1080x1920',
        ];

        if (!in_array(strtolower($resolution), $validResolutions)) {
            throw new InvalidArgumentException('The resolution was not a valid resolution: ' . print_r($resolution, true));
        }
    }

    public static function validateStreamId($streamId)
    {
        if (!(is_string($streamId)) || empty($streamId)) {
            throw new InvalidArgumentException('The streamId was not a string: ' . print_r($streamId, true));
        }
    }

    public static function validateLayoutClassList($layoutClassList, $format = 'JSON')
    {
        if ($format === 'JSON') {
            if (!is_array($layoutClassList) || self::isAssoc($layoutClassList)) {
                throw new InvalidArgumentException('The layoutClassList was not a valid JSON array: ' . print_r($layoutClassList, true));
            }
        }
    }

    public static function validateWebsocketOptions(array $websocketOptions)
    {
        if (!array_key_exists('uri', $websocketOptions)) {
            throw new InvalidArgumentException('Websocket configuration must have a uri');
        }
    }

    public static function validateAutoArchiveResolution($archiveResolution)
    {
        if (! in_array($archiveResolution, Archive::getPermittedResolutions(), true)) {
            throw new InvalidArgumentException($archiveResolution . ' is not a valid resolution');
        }
    }

    public static function validateLayoutClassListItem($layoutClassList)
    {
        if (!is_array($layoutClassList)) {
            throw new InvalidArgumentException('Each element in the streamClassArray must have a layoutClassList array.');
        }

        if (!is_string($layoutClassList['id'])) {
            throw new InvalidArgumentException('Each element in the streamClassArray must have an id string.');
        }

        if (!isset($layoutClassList['layoutClassList'])) {
            throw new InvalidArgumentException('layoutClassList not set in array');
        }

        if (!is_array($layoutClassList['layoutClassList'])) {
            throw new InvalidArgumentException('Each element in the layoutClassList array must be a string (defining class names).');
        }
    }

    public static function validateDefaultTimeout($timeout)
    {
        // Guzzle defaults to "null" instead of 0, so allowing that through
        if (is_null($timeout)) {
            return;
        }

        if (!is_numeric($timeout) || ($timeout < 0)) {
            throw new InvalidArgumentException('Default Timeout must be a number greater than zero');
        }
    }

    public static function validateDTMFDigits(string $digits): void
    {
        if (preg_match('/^[\dp\#\*]+$/', $digits)) {
            return;
        }

        throw new InvalidArgumentException('DTMF digits can only support 0-9, p, #, and * characters');
    }

    public static function isAssoc($arr): bool
    {
        if (!function_exists('array_is_list')) {
            function array_is_list(array $arr): bool
            {
                if ($arr === []) {
                    return true;
                }
                return array_keys($arr) === range(0, count($arr) - 1);
            }
        }

        return !array_is_list($arr);
    }

    protected static function decodeSessionId($sessionId)
    {
        $trimmedSessionId = substr($sessionId, 2);
        $parts = explode('-', $trimmedSessionId);
        $data = array();
        foreach ($parts as $part) {
            $decodedPart = base64_decode($part);
            $dataItems = explode('~', $decodedPart);
            $data = array_merge($data, $dataItems);
        }
        return $data;
    }

    public static function validateBroadcastBitrate($maxBitRate): void
    {
        if (!is_int($maxBitRate)) {
            throw new \InvalidArgumentException('Max Bitrate must be a number');
        }

        if ($maxBitRate < 400000 && $maxBitRate > 2000000) {
            throw new \OutOfBoundsException('Max Bitrate must be between 400000 and 2000000');
        }
    }
}
