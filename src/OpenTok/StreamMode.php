<?php

namespace OpenTok;

use OpenTok\Util\BasicEnum;

/**
 * Defines streamMode values that can be used when either starting an Archive or a Broadcast
 */
abstract class StreamMode extends BasicEnum
{
    /**
     * Default mode, will automatically add all streams to Archive or Broadcast
     */
    public const AUTO = 'auto';

    /**
     * Manual mode, will allow for manual adding and removal of streams for an Archive or Broadcast
     */
    public const MANUAL = 'manual';
}
