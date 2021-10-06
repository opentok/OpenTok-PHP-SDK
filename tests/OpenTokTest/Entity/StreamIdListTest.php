<?php

namespace OpenTokTest\Entity;

use OpenTok\Type\StreamIdListType;
use PHPUnit\Framework\TestCase;

class StreamIdListTest extends TestCase
{
    public function testCanCreateObject(): void
    {
        $streamIdList = new StreamIdListType(['test1', 'test2']);
        $this->assertInstanceOf(StreamIdListType::class, $streamIdList);
    }

    public function testCanAppendToObject(): void
    {
        $streamIdList = new StreamIdListType(['test1', 'test2']);
        $this->assertCount(2, $streamIdList);
        $streamIdList[] = 'test3';
        $this->assertCount(3, $streamIdList);
    }
}