<?php

namespace OpenTok;

use OpenTok\Util\Validators;

class Layout {
    // NOTE: after PHP 5.3.0 support is dropped, the class can implement JsonSerializable

    private static $bestFit = null;
    private static $pip = null;
    private static $verticalPresentation = null;
    private static $horizontalPresentaton = null;

    public static function getBestFit()
    {
        if (is_null(self::$bestFit)) {
            self::$bestFit = new Layout('bestFit');
        }
        return self::$bestFit;
    }

    public static function getPIP()
    {
        if (is_null(self::$pip)) {
            self::$pip = new Layout('pip');
        }
        return self::$pip;
    }

    public static function getVerticalPresentation()
    {
        if (is_null(self::$verticalPresentation)) {
            self::$verticalPresentation = new Layout('verticalPresentation');
        }
        return self::$verticalPresentation;
    }

    public static function getHorizontalPresentation()
    {
        if (is_null(self::$horizontalPresentaton)) {
            self::$horizontalPresentaton = new Layout('horizontalPresentaton');
        }
        return self::$horizontalPresentaton;
    }

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


    public static function fromData($layoutData)
    {
        if (array_key_exists('stylesheet', $layoutData)) {
            return new Layout($layoutData['type'], $layoutData['stylesheet']);
        } else {
            return new Layout($layoutData['type']);
        }
    }

    private $type;
    private $stylesheet;

    private function __construct($type, $stylesheet = null)
    {
        $this->type = $type;
        $this->stylesheet = $stylesheet;
    }

    public function jsonSerialize() {
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
