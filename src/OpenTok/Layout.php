<?php

namespace OpenTok;

use OpenTok\Util\Validators;

/**
 * Defines the object passed in as the $layout parameter of the
 * OpenTok->updateArchiveLayout() and OpenTok->updateArchiveLayout() methods.
 * <p>
 * To instantiate a Layout object, call one of the static methods of the Layout class:
 * <code>getBestFit()</code>, <code>getPIP()</code>, <code>getVerticalPresentation()</code>,
 * <code>getHorizontalPresentation()</code>,or  <code>createCustom()</code>.
 * <p>
 * See <a href="OpenTok.html#method_updateArchiveLayout">OpenTok->updateArchiveLayout()</a>,
 * <a href="OpenTok.html#method_updateBroadcastLayout">OpenTok->updateBroadcastLayout()</a>,
 * <a href="https://tokbox.com/developer/guides/archiving/layout-control.html">Customizing
 * the video layout for composed archives</a>, and
 * <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/#configuring-video-layout-for-opentok-live-streaming-broadcasts">Configuring
 * video layout for OpenTok live streaming broadcasts</a>.
 */
class Layout {
    // NOTE: after PHP 5.3.0 support is dropped, the class can implement JsonSerializable

    /** @ignore */
    private static $bestFit = null;
    /** @ignore */
    private static $pip = null;
    /** @ignore */
    private static $verticalPresentation = null;
    /** @ignore */
    private static $horizontalPresentation = null;

    /**
     * Returns a Layout object defining the "best fit" predefined layout type.
     */
    public static function getBestFit()
    {
        if (is_null(self::$bestFit)) {
            self::$bestFit = new Layout('bestFit');
        }
        return self::$bestFit;
    }

    /**
     * Returns a Layout object defining the "picture-in-picture" predefined layout type.
     */
    public static function getPIP()
    {
        if (is_null(self::$pip)) {
            self::$pip = new Layout('pip');
        }
        return self::$pip;
    }

    /**
     * Returns a Layout object defining the "vertical presentation" predefined layout type.
     */
    public static function getVerticalPresentation()
    {
        if (is_null(self::$verticalPresentation)) {
            self::$verticalPresentation = new Layout('verticalPresentation');
        }
        return self::$verticalPresentation;
    }

    /**
     * Returns a Layout object defining the "horizontal presentation" predefined layout type.
     */
    public static function getHorizontalPresentation()
    {
        if (is_null(self::$horizontalPresentation)) {
            self::$horizontalPresentation = new Layout('horizontalPresentation');
        }
        return self::$horizontalPresentation;
    }

    /**
     * Returns a Layout object defining a custom layout type.
     *
     * @param array $options An array containing one property: <code>$stylesheet<code>,
     * which is a string containing the stylesheet to be used for the layout.
     */
   public static function createCustom($options)
    {
        // unpack optional arguments (merging with default values) into named variables
        // NOTE: the default value of stylesheet=null will not pass validation, this essentially
        //       means that stylesheet is not optional. its still purposely left as part of the
        //       $options argument so that it can become truly optional in the future.
        $defaults = array('stylesheet' => null);
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($stylesheet) = array_values($options);

        // validate arguments
        Validators::validateLayoutStylesheet($stylesheet);

        return new Layout('custom', $stylesheet);
    }

    /** @ignore */
    public static function fromData($layoutData)
    {
        if (array_key_exists('stylesheet', $layoutData)) {
            return new Layout($layoutData['type'], $layoutData['stylesheet']);
        } else {
            return new Layout($layoutData['type']);
        }
    }

    /** @ignore */
    private $type;
    /** @ignore */
    private $stylesheet;

    /** @ignore */
    private function __construct($type, $stylesheet = null)
    {
        $this->type = $type;
        $this->stylesheet = $stylesheet;
    }

    public function jsonSerialize()
    {
        $data = array(
            'type' => $this->type
        );

        // omit 'stylesheet' property unless it is explicitly defined
        if (isset($this->stylesheet)) {
            $data['stylesheet'] = $this->stylesheet;
        }
        return $data;
    }

    public function toJson()
    {
        return json_encode($this->jsonSerialize());
    }
}
