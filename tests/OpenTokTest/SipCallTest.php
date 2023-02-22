<?php
namespace OpenTokTest;

use OpenTok\SipCall;
use PHPUnit\Framework\TestCase;

class SipCallTest extends TestCase
{
    public function testSipCallAttributes(): void
    {
        $sipCallData = [
            'id' => '1_MX4xMjM0NTY3OH4',
            'connectionId' => 'VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI',
            'streamId' => '0123456789abcdef0123456789abcdef0123456789'
        ];

        $sipCall = new SipCall($sipCallData);

        $this->assertEquals('1_MX4xMjM0NTY3OH4', $sipCall->id);
        $this->assertEquals('VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI', $sipCall->connectionId);
        $this->assertEquals('0123456789abcdef0123456789abcdef0123456789', $sipCall->streamId);
        $this->assertNull($sipCall->observeForceMute);
    }
}