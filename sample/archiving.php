<?php

/**
* OpenTok PHP Library
* http://www.tokbox.com/
*
* Copyright (c) 2014, TokBox, Inc.
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

require_once '../OpenTokSDK.php';

$apiObj = new OpenTokSDK();

// The following method starts recording an archive of an OpenTok 2.0 session
// and returns an Archive object (on success). Note that you can only start an archive
// on a session that has clients connected.

$session_id = ""; // Replace this with an OpenTok session ID.
$name = "my first archive";

function startArchive($session_id, $name) {
  global $apiObj;
  try {
    $apiObj->startArchive($session_id);
  } catch (Exception $error) {
    echo $error->getMessage();
  }
}


// The following method stops the recording of an archive, returning
// true on success, and false on failure.

$archive_id = ""; // Replace with a valid archive ID.

function stopArchive($archive_id) {
  global $apiObj;
  try {
    $apiObj->stopArchive($archive_id);
  } catch (Exception $error) {
    echo $error->getMessage();
  }
}

// The following method deletes an archive.

$archive_id = ""; // Replace with a valid archive ID.

function deleteArchive($archive_id) {
  global $apiObj;
  try {
    $archive = $apiObj->deleteArchive($archive_id);
    echo "Deleted archive: ", $archive_id, "\n";
  } catch (Exception $error) {
    echo $error->getMessage();
  }
}

// The following method logs information on a given archive.

$archive_id = ""; // Replace with a valid archive ID.

function getArchive($archive_id) {
  global $apiObj;
  try {
    $archive = $apiObj->getArchive($archive_id);
    echo "createdAt: ", $archive->createdAt(), "<br>\n";
    echo "duration: ", $archive->duration(), "<br>\n";
    echo "id: ", $archive->id(), "<br>\n";
    echo "name: ", $archive->name(), "<br>\n";
    echo "reason: ", $archive->reason(), "<br>\n";
    echo "sessionId: ", $archive->sessionId(), "<br>\n";
    echo "size: ", $archive->size(), "<br>\n";
  } catch (Exception $error) {
    echo $error->getMessage();
  }
}

// The following method logs information on all archives (up to 1000)
// for your API key.

function listArchives() {
  global $apiObj;
  try {
    $archive_list = $apiObj->listArchives();
    $count = $archive_list->totalCount();
    echo("Number of archives: {$count}<br>\n");
    foreach ($archive_list->items() as $archive) {
        echo $archive->id(), "<br>\n";
    } 
  } catch (Exception $error) {
    echo $error->getMessage();
  }
}

?>
