<?php

namespace OpenTok\Type;

use OpenTok\Exception\InvalidArgumentException;
use OpenTok\Util\Validators;

class StreamIdListType extends \ArrayObject
{
    /**
     * @param array<string> $values
     * @throws InvalidArgumentException
     */
    public function __construct(array $values)
    {
        foreach ($values as $arrayValue) {
            Validators::validateStreamId($arrayValue);
        }

        parent::__construct($values);
    }

    /**
     * @param int|string $key
     * @param mixed $value
     * @throws InvalidArgumentException
     */
    public function offsetSet($key, $value): void
    {
        Validators::validateStreamId($value);

        parent::offsetSet($key, $value);
    }
}