<?php

namespace OpenTok;

use Iterator;

/**
 * An object, returned by the <a href="OpenTok.OpenTok.html#method_listConnections">OpenTok.listConnections()</a>
 * method, representing a list of connections in an OpenTok session.
 */
class ConnectionList implements Iterator
{
    /** @ignore */
    private ?array $items = null;

    /** @ignore */
    private int $position = 0;

    /** @ignore */
    public function __construct(
        /** @ignore */
        private $data
    ) {
    }

    /**
     * Returns the number of total connections for the session ID.
     *
     * @return int
     */
    public function totalCount()
    {
        return $this->data['count'];
    }

    /**
     * Returns the project ID (Application ID).
     *
     * @return string
     */
    public function getProjectId()
    {
        return $this->data['projectId'];
    }

    /**
     * Returns the session ID.
     *
     * @return string
     */
    public function getSessionId()
    {
        return $this->data['sessionId'];
    }

    /**
     * Returns an array of Connection objects.
     *
     * @return Connection[]
     */
    public function getItems()
    {
        if (!is_array($this->items)) {
            $items = [];
            foreach ($this->data['items'] as $connectionData) {
                $items[] = new Connection($connectionData);
            }
            $this->items = $items;
        }
        return $this->items;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->data;
    }

    // Iterator interface methods

    /**
     * Rewind the Iterator to the first element
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Return the current element
     */
    public function current(): Connection
    {
        $items = $this->getItems();
        return $items[$this->position];
    }

    /**
     * Return the key of the current element
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Move forward to next element
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Checks if current position is valid
     */
    public function valid(): bool
    {
        $items = $this->getItems();
        return isset($items[$this->position]);
    }
}
