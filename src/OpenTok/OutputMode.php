<?php

namespace OpenTok;

use OpenTok\Util\BasicEnum;

/**
 * Defines values for the outputMode option of the \OpenTok\OpenTok::startArchive()
 * method.
 */
abstract class OutputMode extends BasicEnum {
    const COMPOSED = 'composed';
    const INDIVIDUAL = 'individual';
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
