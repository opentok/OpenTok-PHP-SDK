<?php

namespace OpenTokTest\Validators;

use OpenTok\Exception\InvalidArgumentException;
use OpenTok\Util\Validators;
use PHPUnit\Framework\TestCase;

class ValidatorsTest extends TestCase
{
    public function testWillPassCorrectPayload(): void
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
        $websocketConfig = [
            'uri' => 'ws://valid-websocket',
            'streams' => [
                '525503c7-913e-43a1-84b4-31b2e9fe668b',
                '14026813-4f50-4a5a-9b72-fea25430916d'
            ]
        ];
        Validators::validateWebsocketOptions($websocketConfig);
        // This smells awful but the way validators have been structured mean it's a necessary evil
        $this->assertTrue(true);
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
