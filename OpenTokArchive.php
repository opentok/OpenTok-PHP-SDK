<?php

require_once 'API_Config.php';


/**
* The OpenTokArgumentException class defines an exception thrown when you pass invalid arguments to
* a method.
*/
class OpenTokArgumentException extends Exception {};

/**
* The OpenTokAuthException class defines an exception thrown when you use an invalid
* OpenTok API key or API secret.
*/
class OpenTokAuthException extends Exception {

    public function __construct() {
        parent::__construct("Invalid API key or secret", 403, null);
    }

};
/**
* The OpenTokArchiveException class defines an exception thrown when a call to an archiving
* method fails.
*/
class OpenTokArchiveException extends Exception {};
/**
* The OpenTokArchiveException class defines an exception thrown when a call to an archiving
* method fails.
*/
class OpenTokRequestException extends Exception {};

/**
* For internal use by the OpenTok SDK.
*/
class OpenTokArchivingRequestOptions {
  
  private $value;
  private $mode;
  
  function __construct($mode, $value) {
    $this->mode = $mode;
    $this->value = $value;
  }
  
  function dataString() {
    if($this->mode == 'json') {
      return json_encode($this->value);
    } elseif ($this->mode == 'form') {
      return $this->value;
    }
  }
  
  function contentType() {
    if($this->mode == 'json') {
      return "application/json";
    } elseif ($this->mode == 'form') {
      return "application/x-www-form-urlencoded";
    }
  }
  
}

/**
* For internal use by the OpenTok SDK.
*/
class OpenTokArchivingInterface {

    function __construct($apiKey, $apiSecret, $endpoint = "https://api.opentok.com") {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->endpoint = $endpoint . "/v2/partner/" . $apiKey;
    }

    protected function http_parse_headers($raw_headers) {
        $headers = array();
        $key = '';

        foreach(explode("\n", $raw_headers) as $i => $h) {
            $h = explode(':', $h, 2);

            if (isset($h[1])) {
                $h[0] = strtolower($h[0]);
                if (!isset($headers[$h[0]])){
                    $headers[$h[0]] = trim($h[1]);
                } elseif (is_array($headers[$h[0]])) {
                    $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
                } else {
                    $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
                }

                $key = $h[0];
            } else {
                if (substr($h[0], 0, 1) == "\t")
                    $headers[$key] .= "\r\n\t".trim($h[0]);
                elseif (!$key)
                    $headers[0] = trim($h[0]);
            }
        }

        return $headers;
    }

    protected function curl_request($headers, $method, $url, $opts) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url); // "http://localhost:9919/?=" . 

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if($method == "POST" || $method == "PUT") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opts->dataString());
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


        $res = curl_exec($ch);
        if(curl_errno($ch)) {
            throw new RequestException('Request error: ' . curl_error($ch));
        }

        // Then, after your curl_exec call:
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($res, 0, $header_size);
        $headers = $this->http_parse_headers($header);
        $body = substr($res, $header_size);

        $statusarr = explode(" ", explode("\r\n", $header)[0]);
        $status = $statusarr[1];

        $response = (object)array(
            "status" => $status
        );
  
        if(strtolower($headers["content-type"]) == "application/json") {
            $response->body = json_decode($body);
        } else {
            $response->body = $body;
        }

        return $response;
    }
    
    protected function file_request($headers, $method, $url, $opts) {
        $http = array(
            'method' => $method
        );

        $http["header"] = $headers;

        if($method == "POST" || $method == "PUT") {
            $http["content"] = $opts->dataString();
        }

        $context_source = array ('http' =>$http);
        $context = stream_context_create($context_source);

        $res = file_get_contents( $url ,false, $context);

        $statusarr = explode(" ", $http_response_header[0]);
        $status = $statusarr[1];

        $headers = $this->http_parse_headers(implode("\r\n", $http_response_header));
  
        $response = (object)array(
            "status" => $status
        );
  
        if(strtolower($headers["content-type"]) == "application/json") {
            $response->body = json_decode($res);
        } else {
            $response->body = $res;
        }

        return $response;
    }

    protected function request($method, $url, $opts = null) {
        $url = $this->endpoint . $url;

        if(($method == 'PUT' || $method == 'POST') && $opts) {
            $bodyFormat = $opts->contentType();
        }
    
        $authString = "X-TB-PARTNER-AUTH: $this->apiKey:$this->apiSecret";

        $headers = array($authString);
  
        if($method == "POST" || $method == "PUT") {
            $headers[1] = "Content-type: " . $opts->contentType();
            $headers[2] = "Content-Length: " . strlen($opts->dataString());
        }

        if (function_exists("curl_init")) {
            $response = $this->curl_request($headers, $method, $url, $opts);
        } else if (function_exists("file_get_contents")) {
            $response = $this->file_request($headers, $method, $url, $opts);
        } else {
            throw new OpenTokArchivingRequestException("Your PHP installion doesn't support curl or file_get_contents. Please enable one of these so that you can make API calls.");
        }
        
        return $response;
    }

    public function startArchive($session_id, $name) {

        if(is_null($session_id) || $session_id == "") {
            throw new OpenTokArgumentException("Session ID is invalid");
        }

        $startArchive = array(
            "action" => "start",
            "sessionId" => $session_id,
            "name" => $name
        );

        $result = $this->request("POST", "/archive", new OpenTokArchivingRequestOptions("json", $startArchive));

        if($result->status < 300) {
            $archive = new OpenTokArchive($result->body, $this);
            return $archive;
        } else if($result->status == 403) {
            throw new OpenTokAuthException();
        } else if($result->status == 400) {
            throw new OpenTokArchiveException("Session ID is invalid");
        } else if($result->status == 404) {
            throw new OpenTokArchiveException("Session not found");
        } else if($result->status == 409) {
            throw new OpenTokArchiveException($result->body["message"]);
        } else if(!is_null($result->body)) {
            throw new OpenTokArchiveException($result->body["message"], $result->status);
        } else {
            throw new OpenTokArchiveException("An unexpected error occurred", $result->status);
        }
    }

    public function getArchive($archive_id) {

        if(is_null($archive_id) || $archive_id == "") {
            throw new OpenTokArgumentException("Archive ID is invalid");
        }

        $result = $this->request("GET", "/archive/" . $archive_id);

        if($result->status < 300) {
            $archive = new OpenTokArchive($result->body, $this);
            return $archive;

        } else if($result->status == 403) {
            throw new OpenTokAuthException();

        } else if($result->status == 404) {
            throw new OpenTokArchiveException("Archive not found");

        } else if(!is_null($result->body)) {
            throw new OpenTokArchiveException($result->body["message"], $result->status);

        } else {
            throw new OpenTokArchiveException("An unexpected error occurred", $result->status);
        }

    }

    public function listArchives($offset, $count) {

        if(!(is_numeric($offset))) {
            throw new OpenTokArgumentException("Offset must (if present) be numeric");
        }

        $args = "offset=" . ($offset ? $offset : 0);

        if(!is_null($count)) {
            if(!(is_numeric($count) && $count > 0 && $count < 1000)) {
                throw new OpenTokArgumentException("Count is invalid");
            }
            $args = $args . "&count=" . $count;
        }

        $result = $this->request("GET", "/archive?" . $args);

        if($result->status < 300) {
            $archive = new OpenTokArchiveList($result->body, $this);
            return $archive;

        } else if($result->status == 403) {
            throw new OpenTokAuthException();
 
        } else if(!is_null($result->body)) {
            throw new OpenTokArchiveException($result->body["message"], $result->status);

        } else {
            throw new OpenTokArchiveException("An unexpected error occurred", $result->status);
        }

    }

    public function stopArchive($archive_id) {

        if(is_null($archive_id) || $archive_id == "") {
            throw new OpenTokArgumentException("Archive ID is invalid");
        }

        $stopArchive = array(
            "action" => "stop"
        );

        $result = $this->request("POST", "/archive/" . $archive_id, new OpenTokArchivingRequestOptions("json", $stopArchive));

        if($result->status < 300) {
            return $result->body;

        } else if($result->status == 403) {
            throw new OpenTokAuthException();

        } else if($result->status == 404) {
            throw new OpenTokArchiveException("Archive not found");

        } else if($result->status == 409) {
            throw new OpenTokArchiveException("Archive is not in started state");

        } else if(!is_null($result->body)) {
            throw new OpenTokArchiveException($result->body["message"], $result->status);

        } else {
            throw new OpenTokArchiveException("An unexpected error occurred", $result->status);
        }

    }

    public function deleteArchive($archive_id) {

        if(is_null($archive_id) || $archive_id == "") {
            throw new OpenTokArgumentException("Archive ID is invalid");
        }

        $result = $this->request("DELETE", "/archive/" . $archive_id);

        if($result->status < 300) {
            return true;

        } else if($result->status == 403) {
            throw new OpenTokAuthException();

        } else if($result->status == 404) {
            throw new OpenTokArchiveException("Archive not found");

        } else if(!is_null($result->body)) {
            throw new OpenTokArchiveException($result->body["message"], $result->status);

        } else {
            throw new OpenTokArchiveException("An unexpected error occurred", $result->status);
        }

    }
}

/**
* Represents an archive of an OpenTok session.
*/
class OpenTokArchive implements JsonSerializable {
	/** @internal */
    private $source;
	/** @internal */
    private $api;

	/** @internal */
    public function __construct($source, $api) {
        $this->source = $source;
        $this->api = $api;
    }

    /**
     * The time at which the archive was created, in milliseconds since the UNIX epoch.
     */
    public function createdAt() {
        return $this->source->createdAt / 1000;
    }

    /**
     * The duration of the archive, in milliseconds.
     */
    public function duration() {
        return $this->source->duration;
    }

    /**
     * The archive ID.
     */
    public function id() {
        return $this->source->id;
    }

    /**
     * The name of the archive. If no name was provided when the archive was created, this is set
     * to null.
     */
    public function name() {
        return $this->source->name;
    }

    /**
     * For archives with the status "stopped", this can be set to "90 mins exceeded", "failure", "session ended",
     * or "user initiated". For archives with the status "failed", this can be set to "system failure".
     */
    public function reason() {
        return $this->source->reason;
    }

    /**
     * The session ID of the OpenTok session associated with this archive.
     */
    public function sessionId() {
        return $this->source->sessionId;
    }

    /**
     * The size of the MP4 file. For archives that have not been generated, this value is set to 0.
     */
    public function size() {
        return $this->source->size;
    }

    /**
     * The status of the archive, which can be one of the following:
     *
     * <ul>
     *   <li> "available" -- The archive is available for download from the OpenTok cloud.</li>
     *   <li> "failed" -- The archive recording failed.</li>
     *   <li> "started" -- The archive started and is in the process of being recorded.</li>
     *   <li> "stopped" -- The archive stopped recording.</li>
     *   <li> "uploaded" -- The archive is available for download from the S3 bucket specified.</li>
     * </ul>
     */
    public function status() {
        return $this->source->status;
    }

    /**
     * The download URL of the available MP4 file. This is only set for an archive with the status set to "available";
     * for other archives, (including archives with the status "uploaded") this method returns null. The download URL is
     * obfuscated, and the file is only available from the URL for 10 minutes. To generate a new URL, call
     * the OpenTokArchive.listArchives() or OpenTokSDK.getArchive() method.
     */
    public function url() {
        return $this->source->url;
    }

    /**
     * Stops the OpenTok archive, if it is being recorded.
     * <p>
     * Archives automatically stop recording after 90 minutes or when all clients have disconnected from the
     * session being archived.
     *
     * @throws OpenTokArchiveException The archive is not being recorded.
     */
    public function stop() {
        $this->source = $this->api->stopArchive($this->source->id);
        return $this;
    }

    /**
     * Deletes an OpenTok archive.
     * <p>
     * You can only delete an archive which has a status of "available", "uploaded", or "deleted". 
     * Deleting an archive removes its record from the list of archives. For an "available" archive, 
     * it also removes the archive file, making it unavailable for download. For a "deleted"
     * archive, the archive remains deleted.
     *
     * @param String $archive_id The archive ID of the archive you want to delete.
     *
     * @throws OpenTokArchiveException There archive status is not "available", "updated",
     * or "deleted".
     */
    public function delete() {
        $this->api->deleteArchive($this->source->id);
        $this->source->status = 'deleted';
        return $this;
    }

    /**
     * @internal
     */
    public function jsonSerialize() {
        return $this->source;
    }
}

/**
* A class for accessing an array of OpenTokArchive objects.
*/
class OpenTokArchiveList implements JsonSerializable {

    /** @internal */
    private $_total;
    /** @internal */
    private $_items;
    /** @internal */
    private $_api;

    /** @internal */
    public function __construct($source, $api) {
        $this->_total = $source->count;
        $items = array();

        foreach ($source->items as $item) {
            array_push($items, new OpenTokArchive($item, $api));
        }

        $this->_items = $items;
        $this->_api = $api;
    }

    /**
     * Returns the number of total archives for the API key.
     */
    public function totalCount() {
        return $this->_total;
    }

    /**
     * Returns an array of OpenTokArchive objects.
     */
    public function items() {
        return $this->_items;
    }

    /**
     * @internal
     */
    public function jsonSerialize() {
        return array(
            "total" => $this->totalCount(),
            "items" => $this->items()
        );
    }

}

?>
