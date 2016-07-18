<?php

namespace Opentok;

use \Firebase\JWT\JWT;

class OpenTokTestCase extends \PHPUnit_Framework_TestCase {
  protected $API_KEY;
  protected $API_SECRET;

  public function validateJwt($token) {
    $this->assertNotEmpty($token);
    $decodedToken = JWT::decode($token, $this->API_SECRET, array('HS256'));
    $this->assertObjectHasAttribute('iss', $decodedToken);
    $this->assertEquals($this->API_KEY, $decodedToken->iss);
    $this->assertObjectHasAttribute('ist', $decodedToken);
    $this->assertEquals('project', $decodedToken->ist);
    $this->assertObjectHasAttribute('exp', $decodedToken);
    $this->assertGreaterThanOrEqual(time(), $decodedToken->exp);
    // todo: add test to check for anvil failure code if exp time is greater than anvil expects
    $this->assertObjectHasAttribute('jti', $decodedToken);
  }
}

?>
