<?PHP
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

assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_WARNING, 0);
assert_options(ASSERT_QUIET_EVAL, 0);
$passed = true;

function my_assert_handler($file, $line, $code)
{
    echo "<hr>Assertion Failed:
        File '$file'<br />
        Line '$line'<br />
        Code '$code'<br /><hr />";
	global $passed;
	$passed = false;
}
function exception_handler($exception) {
	global $passed;
	$passed = false;
}

function validate_token($token) {
	if (empty($token) || !is_string($token)) {
		return false;
	}
	$url = API_Config::API_SERVER . "/token/validate";
	$ch = curl_init($url);

	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, Array('Content-type: application/x-www-form-urlencoded'));
	curl_setopt($ch, CURLOPT_HTTPHEADER, Array("X-TB-TOKEN-ATUH: $token"));

	$result = curl_exec($ch);
	echo $result;
	//echo curl_getinfo($ch, CURLINFO_HTTP_CODE); 
	curl_close($ch);
	
}
assert_options(ASSERT_CALLBACK, 'my_assert_handler');
set_exception_handler('exception_handler');

require_once '../OpenTokSDK.php';
$a = new OpenTokSDK(API_Config::API_KEY,API_Config::API_SECRET);

try{
$token = $a->generateToken();
assert('$token');

$token = $a->generateToken("mysession");
assert('$token');

$token = $a->generateToken("mysession", RoleConstants::SUBSCRIBER);
assert('$token');

$token = $a->generateToken("mysession", RoleConstants::PUBLISHER);
assert('$token');

$token = $a->generateToken("mysession", RoleConstants::MODERATOR);
assert('$token');
}
catch (Exception $e){
	assert('$e');
}

try{
	$token = $a->generateToken("");
	assert(false);
} catch(Exception $e){
	assert('$e');
}

try{
	$differentSessionId = "1_MX4yMDc1MjM4MX5-V2VkIEp1bCAxNyAxMDozMjoyMCBQRFQgMjAxM34wLjUyNTU2Mzd-";
	$token = $a->generateToken($differentSessionId);
	assert(false);
} catch(Exception $e){
	assert('$e');
}

try {
	$token = $a->generateToken("mysession", "randomString");
	assert(false);
} catch (Exception $e) {
	assert('$e');
}

try {
	$token = $a->generateToken("mysession", RoleConstants::MODERATOR, gmmktime() - 100000);
	assert(false);
} catch (Exception $e) {
	assert('$e');
}
try {
	$token = $a->generateToken("mysession", RoleConstants::MODERATOR, gmmktime() + 100000);
	assert('$token');
} catch (Exception $e){
	assert('$e');
}

try{
	$token = $a->generateToken("mysession", RoleConstants::MODERATOR, gmmktime());
	assert('$token');
} catch (Exception $e){
	assert('$e');
}


try {
	$token = $a->generateToken("mysession", RoleConstants::MODERATOR, gmmktime() + 1000000);
	assert('$token');
} catch (Exception $e) {
	assert('$e');
}

$sessionId = $a->createSession("127.0.0.1")->getSessionId();
assert('$sessionId');

$sessionId = $a->createSession("8.8.8.8")->getSessionId();
assert('$sessionId');

$sessionId = $a->createSession()->getSessionId();
assert('$sessionId');

$sessionId = $a->createSession('127.0.0.1', array("p2p.preference" => "enabled"))->getSessionId();
assert('$sessionId');

// try {
// 	
// 	assert(false);
// } catch(OpenTokException $e) {
// 	assert('$e');
// }

if ($passed) {
	echo '<h1>A OK!<h1/>';
	echo "<img src='http://i.imgur.com/36bNO.png' />";
} else {
	echo "<h1>Tests failed :(</h1>";
}
?>
