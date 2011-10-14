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

// Create a handler function
function my_assert_handler($file, $line, $code)
{
    echo "<hr>Assertion Failed:
        File '$file'<br />
        Line '$line'<br />
        Code '$code'<br /><hr />";
}

// Set up the callback
assert_options(ASSERT_CALLBACK, 'my_assert_handler');

require_once 'OpenTokSDK.php';
$a = new OpenTokSDK(API_Config::API_KEY,API_Config::API_SECRET);
$token = $a->generate_token();
$token = $a->generate_token("mysession", RoleConstants::MODERATOR, 2);
assert('$token');
$a->validate_token($token);
//phpinfo();
//die();
$token = $a->generate_token("mysession");
$token = $a->generate_token("mysession", RoleConstants::SUBSCRIBER);
$token = $a->generate_token("mysession", RoleConstants::PUBLISHER);
$token = $a->generate_token("mysession", RoleConstants::MODERATOR);
$token = $a->generate_token("mysession", "randomString");
$token = $a->generate_token("mysession", RoleConstants::MODERATOR, gmmktime() - 100000);
$token = $a->generate_token("mysession", RoleConstants::MODERATOR, gmmktime() + 100000);
$token = $a->generate_token("mysession", RoleConstants::MODERATOR, gmmktime() + 1000000);

try {
	assert('$a->create_session("127.0.0.1")->getSessionId()');
}catch(OpenTokException $e) {
	print $e->getMessage();
	assert(false);
}


