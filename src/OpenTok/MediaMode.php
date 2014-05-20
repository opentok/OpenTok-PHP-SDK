<?php

namespace OpenTok;

use OpenTok\Util\BasicEnum;

abstract class MediaMode extends BasicEnum {
    const ROUTED = 'disabled';
    const RELAYED = 'enabled';
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
