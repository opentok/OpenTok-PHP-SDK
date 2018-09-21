<?php

namespace OpenTok;

use OpenTok\Util\Client;
use OpenTok\Util\Validators;

use OpenTok\Exception\InvalidArgumentException;
use OpenTok\Exception\ArchiveUnexpectedValueException;

/**
* Represents data from a SIP Call
*
* @property string $id
* The unique identifier of the call that is created.
*
* @property string $connectionId
* The unique identifier of the connection that represents the SIP call in
* the session. You can use this value to disconnect the SIP call using OpenTok.forceDisconnect()
* method.
*
* @property string $streamId
* The ID of the stream connected to the OpenTok session streaming the audio received from
* the SIP call.
*/
class SipCall {

    /** @internal */
    private $data;

    /** @internal */
    public function __construct($sipCallData)
    {
        $this->data['id'] = $sipCallData['id'];
        $this->data['connectionId'] = $sipCallData['connectionId'];
        $this->data['streamId'] = $sipCallData['streamId'];
    }

    /**
    * Returns the conference ID.
    */
    /** @internal */
    public function __get($name)
    {
        switch($name) {
            case 'id':
            case 'connectionId':
            case 'streamId':
                return $this->data[$name];
                break;
            default:
                return null;
        }
    }


    /**
     * Returns a JSON representation of this SipCall object.
     */
    public function toJson()
    {
        return json_encode($this->data);
    }
}

/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
