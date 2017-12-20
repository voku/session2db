<?php

use voku\db\DB;
use voku\helper\Bootup;
use voku\helper\DbWrapper4Session;
use voku\helper\Session2DB;

/**
 * Class SimpleSessionLockViaMySqlTest
 */
class SimpleSessionLockViaMySqlTest extends \PHPUnit\Framework\TestCase
{

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
  public function __construct()
  {
    parent::__construct();

    $db = DB::getInstance('localhost', 'root', '', 'mysql_test');
    $this->db = new DbWrapper4Session($db);
  }

  public function testGetSettings()
  {
    $settings = $this->session2DB->get_settings();

    if (Bootup::is_php('7.2')) { // TODO?
      self::assertSame('1440 seconds (24 minutes)', $settings['session.gc_maxlifetime']);
    } else {
      self::assertSame('3600 seconds (60 minutes)', $settings['session.gc_maxlifetime']);
    }

    self::assertSame('1', $settings['session.gc_probability']);
    self::assertSame('1000', $settings['session.gc_divisor']);
    self::assertContains('0.1', $settings['probability']);
    self::assertContains('%', $settings['probability']);
  }

  /**
   * @depends testGetSettings
   */
  public function testBasic()
  {
    $_SESSION['test'] = 123;
    $this->session2DB->write($this->session_id, serialize($_SESSION));

    self::assertSame(123, $_SESSION['test']);

    // ---

    $_SESSION['null'] = null;
    $this->session2DB->write($this->session_id, serialize($_SESSION));

    self::assertNull($_SESSION['null']);
  }

  /**
   * @depends testBasic
   */
  public function testBasic2()
  {
    $data = $this->session2DB->read($this->session_id);
    $_SESSION = unserialize($data);

    self::assertSame(123, $_SESSION['test']);

    // ---

    $data = $this->session2DB->read($this->session_id);
    $_SESSION = unserialize($data);

    self::assertNull($_SESSION['null']);
  }

  /**
   * @depends testBasic2
   */
  public function testBasic3WithDbCheck()
  {
    $data = $this->session2DB->read($this->session_id);
    $_SESSION = unserialize($data);

    self::assertSame(123, $_SESSION['test']);

    $result = $this->db->getDb()->select('session_data', array('hash' => $this->session2DB->get_fingerprint()));
    $data = $result->fetchArray();
    $sessionDataFromDb = unserialize($data['session_data']);
    self::assertSame(123, $sessionDataFromDb['test']);
  }

  /**
   * @depends testBasic3WithDbCheck
   */
  public function testFlashdata()
  {
    $this->session2DB->set_flashdata('test2', 'lall');
    self::assertSame('lall', $_SESSION['test2']);

    $this->session2DB->_manage_flashdata(); // first call
    self::assertSame('lall', $_SESSION['test2']);

    $this->session2DB->_manage_flashdata(); // second call
    self::assertFalse(isset($_SESSION['test2']));
  }

  /**
   * @depends testFlashdata
   */
  public function testDestroy()
  {
    $sessionsCount1 = $this->session2DB->get_active_sessions();
    $this->session2DB->destroy($this->session_id);
    $sessionsCount2 = $this->session2DB->get_active_sessions();

    self::assertSame(1, $sessionsCount1);
    self::assertSame(0, $sessionsCount2);
    self::assertCount(0, $_SESSION);
  }

  /**
   * @depends testDestroy
   */
  public function testClose()
  {
    $this->session2DB->read($this->session_id); // needed to set the session-id

    $this->session2DB->use_lock_via_mysql(true);
    $result = $this->session2DB->close();
    self::assertTrue($result);

    $this->session2DB->use_lock_via_mysql(false);
    $result = $this->session2DB->close();
    self::assertTrue($result);
  }

  public function setUp()
  {
    $_SESSION = array();

    $this->session2DB = new Session2DB(
        'teste21321_!!',
        3600,
        true,
        false,
        1,
        1000,
        'session_data',
        60,
        $this->db,
        false
    );

    $this->session2DB->start();
  }

}
