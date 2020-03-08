<?php

use voku\db\DB;
use voku\helper\DbWrapper4Session;
use voku\helper\Session2DB;

/**
 * Class SimpleSessionLockViaPhpTest
 *
 * @internal
 */
final class SimpleSessionLockViaPhpTest extends \PHPUnit\Framework\TestCase
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

    public function testBasic()
    {
        $_SESSION['test'] = 1234;
        $this->session2DB->write($this->session_id, \serialize($_SESSION));

        static::assertSame(1234, $_SESSION['test']);

        // ---

        $_SESSION['null'] = null;
        $this->session2DB->write($this->session_id, \serialize($_SESSION));

        static::assertNull($_SESSION['null']);
    }

    /**
     * @depends testBasic
     */
    public function testBasic2()
    {
        $data = $this->session2DB->read($this->session_id);
        $_SESSION = \unserialize($data, []);

        static::assertSame(1234, $_SESSION['test']);

        // ---

        $data = $this->session2DB->read($this->session_id);
        $_SESSION = \unserialize($data, []);

        static::assertNull($_SESSION['null']);
    }

    /**
     * @depends testBasic2
     */
    public function testBasic3WithDbCheck()
    {
        $data = $this->session2DB->read($this->session_id);
        $_SESSION = \unserialize($data, []);

        static::assertSame(1234, $_SESSION['test']);

        $result = $this->db->getDb()->select('session_data', ['hash' => $this->session2DB->get_fingerprint()]);
        $data = $result->fetchArray();
        $sessionDataFromDb = \unserialize($data['session_data'], []);
        static::assertSame(1234, $sessionDataFromDb['test']);
    }

    /**
     * @depends testBasic3WithDbCheck
     */
    public function testFlashdata()
    {
        $this->session2DB->set_flashdata('test2', 'lall');
        static::assertSame('lall', $_SESSION['test2']);

        $this->session2DB->_manage_flashdata(); // first call
        static::assertSame('lall', $_SESSION['test2']);

        $this->session2DB->_manage_flashdata(); // second call
        static::assertFalse(isset($_SESSION['test2']));
    }

    /**
     * @depends testFlashdata
     */
    public function testDestroy()
    {
        $sessionsCount1 = $this->session2DB->get_active_sessions();
        $this->session2DB->destroy($this->session_id);
        $sessionsCount2 = $this->session2DB->get_active_sessions();

        static::assertSame(1, $sessionsCount1);
        static::assertSame(0, $sessionsCount2);
        static::assertCount(0, $_SESSION);
    }

    /**
     * @depends testDestroy
     */
    public function testClose()
    {
        $this->session2DB->read($this->session_id); // needed to set the session-id

        $result = $this->session2DB->close();
        static::assertTrue($result);
    }

    protected function setUp()
    {
        $_SESSION = [];

        $this->session2DB = new Session2DB(
            'teste21321_!!',
            3600,
            true,
            false,
            1,
            1000,
            'session_data',
            60,
            $this->db
        );
        $this->session2DB->use_lock_via_mysql(false);
    }
}
