<?php

namespace OpenTok;

use OpenTok\Util\BasicEnum;

/**
 * Defines values for the archiveMode parameter of the \OpenTok\OpenTok->createSession() method
 * and the return value for the \OpenTok\Session->getArchiveMode() method.
 *
 * See <a href="OpenTok.OpenTok.html#method_createSession">OpenTok->createSession()</a>
 * and <a href="OpenTok.Archive.html#method_getArchiveMode">Session->getArchiveMode()</a>.
 */
abstract class ArchiveMode extends BasicEnum {
    /**
     * The session is not archived automatically. To archive the session, you can call the
     * \OpenTok\OpenTok->startArchive() method.
     */
    const MANUAL = 'manual';
    /**
     * The session is archived automatically (as soon as there are clients publishing streams
     * to the session).
     */
    const ALWAYS = 'always';
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
