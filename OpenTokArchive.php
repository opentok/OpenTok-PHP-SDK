<?php

require_once 'API_Config.php';

class OpenTokArgumentException extends Exception {};
class OpenTokAuthException extends Exception {

    public function __construct() {
        parent::__construct("Invalid Partner ID or Secret", 403, null);
    }

};
class OpenTokArchiveException extends Exception {};
class OpenTokRequestException extends Exception {};

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

class OpenTokArchive implements JsonSerializable {
    private $source;
    private $api;

    public function __construct($source, $api) {
        $this->source = $source;
        $this->api = $api;
    }

    public function createdAt() {
        return $this->source->createdAt / 1000;
    }

    public function duration() {
        return $this->source->duration;
    }

    public function id() {
        return $this->source->id;
    }

    public function name() {
        return $this->source->name;
    }

    public function reason() {
        return $this->source->reason;
    }

    public function sessionId() {
        return $this->source->sessionId;
    }

    public function size() {
        return $this->source->size;
    }

    public function status() {
        return $this->source->status;
    }

    public function url() {
        return $this->source->url;
    }

    public function stop() {
        $this->source = $this->api->stopArchive($this->source->id);
        return $this;
    }

    public function delete() {
        $this->api->deleteArchive($this->source->id);
        $this->source->status = 'deleted';
        return $this;
    }

    public function jsonSerialize() {
        return $this->source;
    }
}

class OpenTokArchiveList implements JsonSerializable {

    private $_total;
    private $_items;
    private $_api;

    public function __construct($source, $api) {
        $this->_total = $source->count;
        $items = array();

        foreach ($source->items as $item) {
            array_push($items, new OpenTokArchive($item, $api));
        }

        $this->_items = $items;
        $this->_api = $api;
    }

    public function totalCount() {
        return $this->_total;
    }

    public function items() {
        return $this->_items;
    }

    public function jsonSerialize() {
        return array(
            "total" => $this->totalCount(),
            "items" => $this->items()
        );
    }

}

?>
