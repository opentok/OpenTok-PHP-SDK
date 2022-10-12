<?php

namespace OpenTokTest;

use OpenTok\Render;
use PHPUnit\Framework\TestCase;

class RenderTest extends TestCase
{
    public function testCannotHydrateWithWrongPayload(): void
    {
        $this->expectError(\TypeError::class);
        $payload = [
            'id' => '1248e7070b81464c9789f46ad10e7764',
            'sessionId' => '2_MX4xMDBfjE0Mzc2NzY1NDgwMTJ-TjMzfn4',
            'projectId' => 'e2343f23456g34709d2443a234',
            'createdAt' => 1437676551000,
            'updatedAt' => 1437676551000,
            'url' => 'https://webapp.customer.com',
            'resolution' => '1280x720',
            'status«' => 'started',
            'streamId' => 'e32445b743678c98230f238'
        ];

        $render = new Render($payload);
    }

    public function testCanHydrateFromPayload(): void
    {
        $payload = [
            'id' => '1248e7070b81464c9789f46ad10e7764',
            'sessionId' => '2_MX4xMDBfjE0Mzc2NzY1NDgwMTJ-TjMzfn4',
            'projectId' => 'e2343f23456g34709d2443a234',
            'createdAt' => 1437676551000,
            'updatedAt' => 1437676551000,
            'url' => 'https://webapp.customer.com',
            'resolution' => '1280x720',
            'status' => 'started',
            'streamId' => 'e32445b743678c98230f238'
        ];

        $jsonPayload = json_encode($payload);

        $render = new Render($jsonPayload);

        $this->assertEquals('1248e7070b81464c9789f46ad10e7764', $render->id);
        $this->assertEquals('2_MX4xMDBfjE0Mzc2NzY1NDgwMTJ-TjMzfn4', $render->sessionId);
        $this->assertEquals('e2343f23456g34709d2443a234', $render->projectId);
        $this->assertEquals(1437676551000, $render->createdAt);
        $this->assertEquals(1437676551000, $render->updatedAt);
        $this->assertEquals('https://webapp.customer.com', $render->url);
        $this->assertEquals('1280x720', $render->resolution);
        $this->assertEquals('started', $render->status);
        $this->assertEquals('e32445b743678c98230f238', $render->streamId);
    }
}

