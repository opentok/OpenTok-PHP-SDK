<?php

require_once 'API_Config.php';

class OpenTokArchive {

    private $archiveId;

    private $archiveTitle;

    //Array of resources listed in this Manifest
    private $resources = array();

    //Array of the timeline from the Manifest file
    private $timeline = array();

    public function __construct($archiveId, $archiveTitle, $resources, $timeline) {
        $this->archiveId = $archiveId;
        $this->archiveTitle = $archiveTitle;
        $this->resources = $resources;
        $this->timeline = $timeline;
    }

    /*************/
    ////Getters///
    /*************/
    public function getId() {
        return $this->archiveId;
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
    public function downloadArchiveURL($videoId) {
        return API_Config::API_SERVER . '/archive/url/'.$this->archiveId.'/'.$videoId;
    }

    /*************/
    ////Parser/////
    /*************/
    public static function parseXML($manifest) {
        $archiveId = $manifest['archiveid'];
        $title = $manifest['title'];
        $resources = array();
        $timeline = array();

        foreach($manifest->resources as $videoResourceItem) {
            $resources[] = OpenTokArchiveVideoResource::parseXML($videoResourceItem); 
        }

        foreach($manifest->timeline as $timelineItem) {
            $timeline[] = OpenTokArchiveTimelineEvent::parseXML($timelineItem);
        }

        return new OpenTokArchive($archiveId, $title, $resources, $timeline);
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

    public static function parseXML($timelineItem) {
        return new OpenTokArchiveTimelineEvent($timelineItem['type'], $timelineItem['id'], $timelineItem['offset']);
    }
}

