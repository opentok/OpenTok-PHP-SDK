<?php

require_once 'API_Config.php';

class OpenTokArchive {

    private $archiveId;
    private $archiveTitle;
    private $server_url;

    //Array of resources listed in this Manifest
    private $resources = array();

    //Array of the timeline from the Manifest file
    private $timeline = array();

    public function __construct($archiveId, $archiveTitle, $resources, $timeline, $server_url) {
        $this->archiveId = $archiveId;
        $this->archiveTitle = $archiveTitle;
        $this->resources = $resources;
        $this->timeline = $timeline;
        $this->server_url = $server_url;
    }

    /*************/
    ////Getters///
    /*************/
    public function getId() {
        return ((string) $this->archiveId);
    }

    public function getTitle() {
        return $this->archiveTitle;
    }

    public function getResources() {
        return $this->resources;
    }

    public function getTimeline() {
        return $this->timeline;
    }

    /*************/
    ////Public FNs/
    /*************/
    public function downloadArchiveURL($videoId, $token) {
        $url = $this->server_url . '/archive/url/' .$this->archiveId.'/'.$videoId;
        $authString = "X-TB-TOKEN-AUTH: ".$token;

        $res=$this->doApiCall($url,$authString);
		
        return $res;
    }

	/**
	 * This function deletes an archive. It was added by Tobias Nyholm
	 */
	public function deleteArchive($token) {
        $url = $this->server_url . '/archive/delete/' .$this->archiveId.'/';
        $authString = "X-TB-TOKEN-AUTH: ".$token;

        $this->doApiCall($url,$authString);

    }

	/**
	 * This function does the actuall call and return the result
	 */
	private function doApiCall($url, $authString){
		if(function_exists("curl_init")) {            
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, Array($authString));   
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $res = curl_exec($ch);
            if(curl_errno($ch)) {
                throw new RequestException('Request error: ' . curl_error($ch));
            }

            curl_close($ch);
        }
        else {        
            if (function_exists("file_get_contents")) {
                $context_source = array ('http' => array ( 'method' => 'GET', 'header'=> Array($authString) ) );
                $context = stream_context_create($context_source);
                $res = @file_get_contents( $url ,false, $context);                
            }
            else{
                throw new RequestException("Your PHP installion neither supports the file_get_contents method nor cURL. Please enable one of these functions so that you can make API calls.");
            }        
        }
		
		return $res;
	}

    /*************/
    ////Parser/////
    /*************/
    public static function parseManifest($manifest, $server_url) {
        $archiveId = $manifest['archiveid'];
        $title = $manifest['title'];
        $resources = array();
        $timeline = array();

        foreach($manifest->resources->video as $videoResourceItem) {
            $resources[] = OpenTokArchiveVideoResource::parseXML($videoResourceItem); 
        }

        foreach($manifest->timeline->event as $timelineItem) {
            $timeline[] = OpenTokArchiveTimelineEvent::parseXML($timelineItem);
        }

        return new OpenTokArchive($archiveId, $title, $resources, $timeline, $server_url);
    }

}

class OpenTokArchiveVideoResource {
    private $id;
    private $type = 'video';
    private $length;

    public function __construct($id, $length) {
        $this->id = $id;
        $this->length = $length;
    }

    public function getId() {
        return $this->id;
    }

    public function getLength() {
        return $this->length;
    }

    public static function parseXML($videoResourceItem) {
        return new OpenTokArchiveVideoResource($videoResourceItem['id'], $videoResourceItem['length']);
    }
}

class OpenTokArchiveTimelineEvent {
    private $eventType;
    private $resourceId;
    private $offset;

    public function __construct($eventType, $resourceId, $offset) {
        $this->eventType = $eventType;
        $this->resourceId = $resourceId;
        $this->offset = $offset;
    }

    public function getEventType() {
        return $this->eventType;
    }

    public function getResourceId() {
        return $this->resourceId;
    }

    public function getOffset() {
        return $this->offset;
    }

    public static function parseXML($timelineItem) {
        return new OpenTokArchiveTimelineEvent($timelineItem['type'], $timelineItem['id'], $timelineItem['offset']);
    }
}

