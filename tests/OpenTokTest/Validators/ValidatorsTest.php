<?php

namespace OpenTokTest\Validators;

use OpenTok\Exception\InvalidArgumentException;
use OpenTok\Util\Client;
use OpenTok\Util\Validators;
use PHPUnit\Framework\TestCase;

class ValidatorsTest extends TestCase
{
    public function testWillValidateStringApiKey(): void
    {
        $this->expectNotToPerformAssertions();
        $apiKey = '47347801';
        Validators::validateApiKey($apiKey);
    }

    public function testWillValidateIntegerApiKey(): void
    {
        $this->expectNotToPerformAssertions();
        $apiKey = 47347801;
        Validators::validateApiKey($apiKey);
    }

    public function testWillInvalidateApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $apiKey = [47347801];
        Validators::validateApiKey($apiKey);
    }

    public function testWillValidateApiSecret(): void
    {
        $this->expectNotToPerformAssertions();
        $secret = 'cdff574f0b071230be098e279d16931116c43fcf';
        Validators::validateApiSecret($secret);
    }

    public function testWillInvalidateApiSecret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $secret = 3252556;
        Validators::validateApiSecret($secret);
    }

    public function testWillValidateApiUrl(): void
    {
        $this->expectNotToPerformAssertions();
        $apiUrl = 'https://api.opentok.com';
        Validators::validateApiUrl($apiUrl);
    }

    public function testWillInvalidateApiUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $apiUrl = 'dave@opentok.com';
        Validators::validateApiUrl($apiUrl);
    }

    public function testWillPassCorrectForceMutePayload(): void
    {
        $this->expectNotToPerformAssertions();

        $options = [
            'excludedStreams' => [
                'streamId1',
                'streamId2'
            ],
            'active'          => true
        ];

        Validators::validateForceMuteAllOptions($options);
    }

    public function testIsAssocWithIndexedArray(): void
    {
        $array = [1, 2, 3, 4];
        $this->assertFalse(Validators::isAssoc($array));
    }

    public function testIsAssocWithAssociativeArray(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $this->assertTrue(Validators::isAssoc($array));
    }

    public function testIsAssocWithMixedKeysArray(): void
    {
        $array = [1, 'a' => 2, 3];
        $this->assertTrue(Validators::isAssoc($array));
    }

    public function testIsAssocWithEmptyArray(): void
    {
        $array = [];
        $this->assertFalse(Validators::isAssoc($array));
    }

    public function testWillFailWhenStreamIdsAreNotCorrect(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $options = [
            'excludedStreams' => [
                3536,
                'streamId2'
            ],
            'active'          => true
        ];

        Validators::validateForceMuteAllOptions($options);
    }

    public function testWillFailWhenActiveIsNotBool(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $options = [
            'excludedStreams' => [
                'streamId1',
                'streamId2'
            ],
            'active'          => 'true'
        ];

        Validators::validateForceMuteAllOptions($options);
    }

    public function testWillFailWhenStreamIdsIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $options = [
            'excludedStreams' => 'streamIdOne',
            'active'          => false
        ];

        Validators::validateForceMuteAllOptions($options);
    }

    public function testWillValidateWebsocketConfiguration(): void
    {
        $this->expectNotToPerformAssertions();
        $websocketConfig = [
            'uri'     => 'ws://valid-websocket',
            'streams' => [
                '525503c7-913e-43a1-84b4-31b2e9fe668b',
                '14026813-4f50-4a5a-9b72-fea25430916d'
            ]
        ];
        Validators::validateWebsocketOptions($websocketConfig);
    }

    public function testWillThrowExceptionOnInvalidWebsocketConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $websocketConfig = [
            'streams' => [
                '525503c7-913e-43a1-84b4-31b2e9fe668b',
                '14026813-4f50-4a5a-9b72-fea25430916d'
            ]
        ];
        Validators::validateWebsocketOptions($websocketConfig);
    }

    /**
     * @dataProvider resolutionProvider
     */
    public function testValidResolutions($resolution, $isValid): void
    {
        if ( ! $isValid) {
            $this->expectException(InvalidArgumentException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        Validators::validateResolution($resolution);
    }

    public function testValidLayoutClassListItemErrorOnString(): void
    {
        $input = 'example_id';
        $this->expectException(\InvalidArgumentException::class);
        Validators::validateLayoutClassListItem($input);
    }

    public function testValidLayoutClassListItem(): void
    {
        $layoutClassList = [
            'id' => 'example_id',
            'layoutClassList' => ['class1', 'class2']
        ];

        $this->assertNull(Validators::validateLayoutClassListItem($layoutClassList));
    }

    public function testInvalidIdType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $layoutClassList = [
            'id' => 123,
            'layoutClassList' => ['class1', 'class2']
        ];

        Validators::validateLayoutClassListItem($layoutClassList);
    }

    public function testMissingLayoutClassList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $layoutClassList = [
            'id' => 'example_id'
        ];

        Validators::validateLayoutClassListItem($layoutClassList);
    }

    public function testInvalidLayoutClassListType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $layoutClassList = [
            'id' => 'example_id',
            'layoutClassList' => 'invalid_class'
        ];

        Validators::validateLayoutClassListItem($layoutClassList);
    }
    public function testValidateClient(): void
    {
        $client = new Client();
        Validators::validateClient($client);

        // No exception, which was the test so fake a pass
        $this->assertTrue(true);
    }

    public function testExceptionOnInvalidClient(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $client = new \stdClass();
        Validators::validateClient($client);
    }

    public function testThrowsErrorOnInvalidStreamMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $streamMode = ['auto'];
        Validators::validateHasStreamMode($streamMode);
    }

    public function testValidateSignalPayload(): void
    {
        $validPayload = ['type' => 'signal_type', 'data' => 'signal_data'];
        $this->assertNull(Validators::validateSignalPayload($validPayload));

        $invalidDataPayload = ['type' => 'signal_type', 'data' => 123];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Signal Payload cannot be null:");
        Validators::validateSignalPayload($invalidDataPayload);

        $invalidTypePayload = ['type' => null, 'data' => 'signal_data'];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Signal Payload cannot be null:");
        Validators::validateSignalPayload($invalidTypePayload);

        // Invalid payload: both type and data are null
        $invalidBothPayload = ['type' => null, 'data' => null];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Signal Payload cannot be null:");
        Validators::validateSignalPayload($invalidBothPayload);
    }

    /**
     * @dataProvider connectionIdProvider
     */
    public function testConnectionId($input, $expectException): void
    {
        if ($expectException) {
            $this->expectException(\InvalidArgumentException::class);
        }

        Validators::validateConnectionId($input);
        $this->assertTrue(true);
    }

    public function connectionIdProvider(): array
    {
        return [
            [['this' => 'is not a string'], true],
            ['', true],
            ['valid_connection_string', false]
        ];
    }

    public function resolutionProvider(): array
    {
        return [
            ['640x480', true],
            ['1280x720', true],
            ['1920x1080', true],
            ['480x640', true],
            ['720x1280', true],
            ['1080x1920', true],
            ['1080X1920', true],
            ['923x245', false]
        ];
    }
}
