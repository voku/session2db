<?php

namespace voku\helper;

interface Db4Session
{

  /**
   * @return bool
   */
  public function close();

  /**
   * @param mixed $var
   *
   * @return mixed
   */
  public function escape($var);

  /**
   * @param string $sql
   * @param string $string
   *
   * @return array|string <p>empty string on error</p>
   */
  public function fetchColumn($sql, $string);

  /**
   * @return bool
   */
  public function ping();

  /**
   * @param string $sql
   *
   * @return mixed|false <p>false on error</p>
   */
  public function query($sql);

  /**
   * @param string $string
   *
   * @return string
   */
  public function quote_string($string);

  /**
   * @return bool
   */
  public function reconnect();
}
