<?php

namespace OpenTok;

use OpenTok\Util\Validators;

/**
 * Defines the object passed in as the $layout parameter of the
 * OpenTok->setArchiveLayout() and OpenTok->updateBroadcastLayout() methods.
 * <p>
 * To instantiate a Layout object, call one of the static methods of the Layout class:
 * <code>getBestFit()</code>, <code>getPIP()</code>, <code>getVerticalPresentation()</code>,
 * <code>getHorizontalPresentation()</code>, or <code>createCustom()</code>.
 * <p>
 * See <a href="OpenTok.OpenTok.html#method_setArchiveLayout">OpenTok->setArchiveLayout()</a>,
 * <a href="OpenTok.OpenTok.html#method_updateBroadcastLayout">OpenTok->updateBroadcastLayout()</a>,
 * <a href="https://tokbox.com/developer/guides/archiving/layout-control.html">Customizing
 * the video layout for composed archives</a>, and
 * <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/#configuring-video-layout-for-opentok-live-streaming-broadcasts">Configuring
 * video layout for OpenTok live streaming broadcasts</a>.
 */
class Layout implements \JsonSerializable
{
    public const LAYOUT_BESTFIT = 'bestFit';
    public const LAYOUT_CUSTOM = 'custom';
    public const LAYOUT_HORIZONTAL = 'horizontalPresentation';
    public const LAYOUT_PIP = 'pip';
    public const LAYOUT_VERTICAL = 'verticalPresentation';

    /**
     * Type of layout that we are sending
     * @var string
     * @ignore
     * */
    private $type;

    /**
     * Type of layout to use for screen sharing
     * @var string
     * @ignore
     */
    private $screenshareType;

    /**
     * Custom stylesheet if our type is 'custom'
     * @var string
     * @ignore
     */
    private $stylesheet;

    /** @ignore */
    private function __construct(string $type, ?string $stylesheet = null)
    {
        $this->type = $type;
        $this->stylesheet = $stylesheet;
    }

    /**
     * Returns a Layout object defining a custom layout type.
     *
     * @param array $options An array containing one property: <code>$stylesheet<code>,
     * which is a string containing the stylesheet to be used for the layout.
     */
    public static function createCustom(array $options): Layout
    {
        // unpack optional arguments (merging with default values) into named variables
        // NOTE: the default value of stylesheet=null will not pass validation, this essentially
        //       means that stylesheet is not optional. its still purposely left as part of the
        //       $options argument so that it can become truly optional in the future.
        $defaults = ['stylesheet' => null];
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($stylesheet) = array_values($options);

        // validate arguments
        Validators::validateLayoutStylesheet($stylesheet);

        return new Layout(static::LAYOUT_CUSTOM, $stylesheet);
    }

    /** @ignore */
    public static function fromData(array $layoutData): Layout
    {
        if (array_key_exists('stylesheet', $layoutData)) {
            return new Layout($layoutData['type'], $layoutData['stylesheet']);
        }

        return new Layout($layoutData['type']);
    }

    /**
     * Returns a Layout object defining the "best fit" predefined layout type.
     */
    public static function getBestFit(): Layout
    {
        return new Layout(static::LAYOUT_BESTFIT);
    }

    /**
     * Returns a Layout object defining the "picture-in-picture" predefined layout type.
     */
    public static function getPIP(): Layout
    {
        return new Layout(static::LAYOUT_PIP);
    }

    /**
     * Returns a Layout object defining the "vertical presentation" predefined layout type.
     */
    public static function getVerticalPresentation(): Layout
    {
        return new Layout(static::LAYOUT_VERTICAL);
    }

    /**
     * Returns a Layout object defining the "horizontal presentation" predefined layout type.
     */
    public static function getHorizontalPresentation(): Layout
    {
        return new Layout(static::LAYOUT_HORIZONTAL);
    }

    public function setScreenshareType(string $screenshareType): Layout
    {
        if ($this->type === Layout::LAYOUT_BESTFIT) {
            $layouts = [
                Layout::LAYOUT_BESTFIT,
                Layout::LAYOUT_HORIZONTAL,
                Layout::LAYOUT_PIP,
                Layout::LAYOUT_VERTICAL
            ];

            if (!in_array($screenshareType, $layouts)) {
                throw new \RuntimeException('Screenshare type must be of a valid layout type');
            }

            $this->screenshareType = $screenshareType;
            return $this;
        }

        throw new \RuntimeException('Screenshare type cannot be set on a layout type other than bestFit');
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Return a json-encoded string representation of the layout
     */
    public function toJson(): string
    {
        return json_encode($this->jsonSerialize());
    }

    public function toArray(): array
    {
        $data = array(
            'type' => $this->type
        );

        // omit 'stylesheet' property unless it is explicitly defined
        if (isset($this->stylesheet)) {
            $data['stylesheet'] = $this->stylesheet;
        }

        // omit 'screenshareType' property unless it is explicitly defined
        if (isset($this->screenshareType)) {
            $data['screenshareType'] = $this->screenshareType;
        }

        return $data;
    }
}
