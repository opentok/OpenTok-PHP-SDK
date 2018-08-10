<?php

namespace OpenTok;

class StreamList {

    private $data;

    public function __construct($streamListData)
    {
        $this->data = $streamListData;
    }

    /**
     * Returns the number of total streams for the session ID.
     */
    public function totalCount()
    {
        return $this->data['count'];
    }

    /**
     * Returns an array of Stream objects.
     */
    public function getItems()
    {
        if (!$this->items) {
            $items = array();
            foreach($this->data['items'] as $streamData) {
                $items[] = new Stream($streamData);
            }
            $this->items = $items;
        }
        return $this->items;
    }


    public function jsonSerialize()
    {
        return $this->data;
    }
}
