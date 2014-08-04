<?php

use voku\db\DB;

error_reporting(E_ERROR);
ini_set('display_errors', FALSE);

# running from the cli doesn't set $_SESSION
if (!isset($_SESSION)) {
  $_SESSION = array();
}

class SimpleSessionTest extends PHPUnit_Framework_TestCase {

  /**
   * @var DB
   */
  public $db;

  public $tableName = 'test_page';

  public function __construct() {
    $this->db = DB::getInstance('localhost', 'root', '', 'mysql_test');
  }

  function test_basic() {
    new voku\helper\Session2DB('teste21321_!!', 3600);

    $_SESSION['test'] = 123;

    $this->assertEquals('123', $_SESSION['test']);
  }

}
