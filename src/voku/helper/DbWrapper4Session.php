<?php

declare(strict_types=1);

namespace voku\helper;

use voku\db\DB;

class DbWrapper4Session implements Db4Session
{

  /**
   * @var DB
   */
  private $db;

  /**
   * SimpleMySQLiWrapper constructor.
   *
   * @param null|DB $db
   */
  public function __construct($db = null)
  {
    if (null !== $db) {
      $this->db = $db;
    } else {
      $this->db = DB::getInstance();
    }

    $this->db->setConfigExtra(array('session_to_db' => true));
  }

  /**
   * @inheritdoc
   */
  public function close(): bool
  {
    return $this->db->close();
  }

  /**
   * @inheritdoc
   */
  public function escape($var)
  {
    return $this->db->escape($var);
  }

  /**
   * @inheritdoc
   */
  public function fetchColumn(string $sql, string $string)
  {
    $result = $this->db->query($sql);

    return $result->fetchColumn($string);
  }

  /**
   * @inheritdoc
   */
  public function ping(): bool
  {
    return $this->db->ping();
  }

  /**
   * @inheritdoc
   */
  public function query(string $sql)
  {
    return $this->db->query($sql);
  }

  /**
   * @inheritdoc
   */
  public function quote_string(string $string): string
  {
    return $this->db->quote_string($string);
  }

  /**
   * @inheritdoc
   */
  public function reconnect(): bool
  {
    return $this->db->reconnect();
  }

  /**
   * @return DB
   */
  public function getDb()
  {
    return $this->db;
  }

  /**
   * @return DbWrapper4Session
   */
  public static function getInstance()
  {
    return new self();
  }
}
