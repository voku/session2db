<?php

use voku\db\DB;
use voku\helper\Session2DB;

# running from the cli doesn't set $_SESSION
if (!isset($_SESSION)) {
  $_SESSION = array();
}

/**
 * Class SimpleSessionTest
 */
class SimpleSessionTest extends PHPUnit_Framework_TestCase {

  /**
   * @var DB
   */
  public $db;

  /**
   * @var Session2DB
   */
  public $session2DB;

  /**
   * @var string
   */
  public $session_id = 'test';

  /**
   * __construct
   */
  public function __construct() {
    $this->db = DB::getInstance('localhost', 'root', '', 'mysql_test');
  }

  public function testGetSettings()
  {
    $settings = $this->session2DB->get_settings();

    self::assertEquals('3600 seconds (60 minutes)', $settings['session.gc_maxlifetime']);
    self::assertEquals('1', $settings['session.gc_probability']);
    self::assertEquals('1000', $settings['session.gc_divisor']);
    self::assertEquals('0.10000000000000001%', $settings['probability']);
  }

  public function testBasic() {
    $_SESSION['test'] = 123;
    $this->session2DB->write($this->session_id, serialize($_SESSION));

    self::assertEquals('123', $_SESSION['test']);
  }

  public function testBasic2() {
    $data = $this->session2DB->read($this->session_id);
    $_SESSION = unserialize($data);

    self::assertEquals('123', $_SESSION['test']);
  }

  public function testDestroy()
  {
    $this->session2DB->destroy($this->session_id);

    self::assertEquals('0', count($_SESSION));
  }

  public function setUp()
  {
    $this->session2DB = new Session2DB('teste21321_!!', 3600, true, false, 1, 1000, 'session_data', 60);
  }

}
