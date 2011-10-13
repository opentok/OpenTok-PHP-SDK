<?PHP
/*!
* OpenTok PHP Library v0.90.0
* http://www.tokbox.com/
*
* Copyright 2010, TokBox, Inc.
*
* Date: November 05 14:50:00 2010
*/

require_once 'OpenTokSDK.php';
$a = new OpenTokSDK(API_Config::API_KEY,API_Config::API_SECRET);
print $a->generate_token();
print "\n";
print $a->generate_token('mysession');
print "\n";
print $a->generate_token('mysession', RoleConstants::MODERATOR);
print "\n";
try {
	print $a->create_session('127.0.0.1')->getSessionId();
}catch(OpenTokException $e) {
	print $e->getMessage();
 }

print "\n";

