<?php

namespace OpenTok;

use OpenTok\Util\BasicEnum;

/**
 * Defines values for the outputMode option of the \OpenTok\OpenTok->startArchive() method
 * and for the outputMode property of the Archive class.
 *
 * See <a href="OpenTok.OpenTok.html#method_startArchive">OpenTok->startArchive()</a>
 * and <a href="OpenTok.Archive.html#property_outputMode">Archive.outputMode</a>.
 */
abstract class OutputMode extends BasicEnum {
    /**
     * All streams in the archive are recorded to a single (composed) file.
     */
    const COMPOSED = 'composed';
    /**
     * Each stream in the archive is recorded to its own individual file.
     */
    const INDIVIDUAL = 'individual';
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
