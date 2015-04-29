<?php

namespace OpenTok;

use OpenTok\Util\BasicEnum;

/**
 * Defines values for the archiveMode parameter of the \OpenTok\OpenTok->createSession()
 * method.
 */
abstract class ArchiveMode extends BasicEnum {
    const MANUAL = 'manual';
    const ALWAYS = 'always';
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
