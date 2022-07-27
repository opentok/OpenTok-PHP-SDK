<?php

namespace OpenTokTest\Validators;

use OpenTok\Exception\InvalidArgumentException;
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
            'active' => true
        ];

        Validators::validateForceMuteAllOptions($options);
    }

    public function testWillFailWhenStreamIdsAreNotCorrect(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $options = [
            'excludedStreams' => [
                3536,
                'streamId2'
            ],
            'active' => true
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
            'active' => 'true'
        ];

        Validators::validateForceMuteAllOptions($options);
    }

    public function testWillFailWhenStreamIdsIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $options = [
            'excludedStreams' => 'streamIdOne',
            'active' => false
        ];

        Validators::validateForceMuteAllOptions($options);
    }

    public function testWillValidateWebsocketConfiguration(): void
    {
        $this->expectNotToPerformAssertions();
        $websocketConfig = [
            'uri' => 'ws://valid-websocket',
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
}
