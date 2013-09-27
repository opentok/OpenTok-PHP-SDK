<?php

/**
* OpenTok PHP Library
* http://www.tokbox.com/
*
* Copyright (c) 2011, TokBox, Inc.
* Permission is hereby granted, free of charge, to any person obtaining
* a copy of this software and associated documentation files (the "Software"), 
* to deal in the Software without restriction, including without limitation 
* the rights to use, copy, modify, merge, publish, distribute, sublicense, 
* and/or sell copies of the Software, and to permit persons to whom the
* Software is furnished to do so, subject to the following conditions:
* 
* The above copyright notice and this permission notice shall be included
* in all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
* OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL 
* THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN 
* THE SOFTWARE.
*/

require_once '../API_Config.php';
require_once '../OpenTokSDK.php';

// Creating an OpenTok Object
$apiObj = new OpenTokSDK( API_Config::API_KEY, API_Config::API_SECRET );

// Creating Simple Session object, passing IP address to determine closest production server
// Passing IP address to determine closest production server
//$session = $apiObj->createSession( $_SERVER["REMOTE_ADDR"] );
$session = $apiObj->createSession();
$sessionId = $session->getSessionId();
echo $sessionId;
echo "\n";
// Creating Simple Session object 
// Enable p2p connections
$session = $apiObj->createSession(null, array(SessionPropertyConstants::P2P_PREFERENCE=> "enabled") );

// Getting sessionId from Sessions
// Option 1: Call getSessionId()
$sessionId = $session->getSessionId();
echo $sessionId;
// Option 2: Return the object itself
echo $session;
echo "\n";

?>
