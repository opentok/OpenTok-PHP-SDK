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

    public function testIsAssocWithValidArray(): void
    {
        $haystack = [
            'one' => '1',
            'two' => '2',
            'three' => '3',
            'four' => '4'
        ];

        $this->assertTrue(Validators::isAssoc($haystack));
    }

    public function testIsAssocWithInvalidArray(): void
    {
        $haystack = [
            'one',
            'two',
            'three',
            'four'
        ];

        $this->assertFalse(Validators::isAssoc($haystack));
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


    /**
     * @dataProvider resolutionProvider
     */
    public function testValidResolutions($resolution, $isValid): void
    {
        if (!$isValid) {
            $this->expectException(InvalidArgumentException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        Validators::validateResolution($resolution);
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
