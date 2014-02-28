<?php

namespace OpenTok;

use OpenTok\Util\BasicEnum;

abstract class Role extends BasicEnum {
    const SUBSCRIBER = 'subscriber';
    const PUBLISHER = 'publisher';
    const MODERATOR = 'moderator';
}
