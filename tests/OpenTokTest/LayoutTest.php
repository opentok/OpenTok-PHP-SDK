<?php

namespace OpenTokTest;

use OpenTok\Layout;
use PHPStan\Testing\TestCase;

class LayoutTest extends TestCase
{
    public function testStylesheetIsNotInSerializedArrayIfNotCustom()
    {
        $layouts = [
            Layout::LAYOUT_BESTFIT => Layout::getBestFit(),
            Layout::LAYOUT_HORIZONTAL => Layout::getHorizontalPresentation(),
            Layout::LAYOUT_PIP => Layout::getPIP(),
            Layout::LAYOUT_VERTICAL => Layout::getVerticalPresentation(),
        ];

        foreach ($layouts as $type => $object) {
            $this->assertSame(['type' => $type], $object->toArray());
        }
    }

    public function testStylesheetIsInSerializedArrayIfCustom()
    {
        $layout = Layout::createCustom(['stylesheet' => 'foo']);

        $this->assertSame(['type' => LAYOUT::LAYOUT_CUSTOM, 'stylesheet' => 'foo'], $layout->toArray());
    }

    public function testScreenshareTypeSerializesProperly()
    {
        $layout = Layout::getBestFit();
        $layout->setScreenshareType(Layout::LAYOUT_PIP);

        $expected = [
            'type' => 'bestFit',
            'screenshareType' => 'pip'
        ];

        $this->assertSame($expected, $layout->toArray());
    }

    public function testScreenshareTypeCannotBeSetToInvalidValue()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Screenshare type must be of a valid layout type');

        $layout = Layout::getBestFit();
        $layout->setScreenshareType('bar');
    }

    public function testScreenshareTypeCannotBeSetOnInvalidLayoutType()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Screenshare type cannot be set on a layout type other than bestFit');

        $layout = Layout::createCustom(['stylesheet' => 'foo']);
        $layout->setScreenshareType(Layout::LAYOUT_PIP);
    }
}
