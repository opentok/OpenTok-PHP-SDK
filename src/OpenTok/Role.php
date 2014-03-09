<?php

namespace OpenTok;

use OpenTok\Util\BasicEnum;

abstract class Role extends BasicEnum {
    const SUBSCRIBER = 'subscriber';
    const PUBLISHER = 'publisher';
    const MODERATOR = 'moderator';
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
