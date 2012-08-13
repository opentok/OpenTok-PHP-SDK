<?php
 
/*HEADER INFORMATION*/
function parse_results($res){
     /*Variables*/
     $lines = explode("\n", $res);
     /*Print All*/
     echo '<pre>'.print_r($lines, TRUE). '</pre>';
     /*Location*/
     $url = $lines[8];
}

/*Variables*/
$API_KEY = '';
$API_SECRET = '';
$archiveId = '';
$stitchUrl = "https://api.opentok.com/hl/archive/$archiveId/stitch";

/*CURL*/
/*Start CURL*/
$ch = curl_init();

/*CURL Variables*/
curl_setopt($ch, CURLOPT_URL, $stitchUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded', "X-TB-PARTNER-AUTH: $API_KEY:$API_SECRET", 'Content-length:0'));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

/*CURL Result*/
$res = curl_exec($ch);

/*Process Response*/
parse_results($res);

/*Close CURL*/
curl_close($ch);
?>
