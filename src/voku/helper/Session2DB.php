<?php

declare(strict_types=1);

namespace voku\helper;

/**
 * A PHP library acting as a drop-in replacement for PHP's default session handler, but instead of storing session
 * data in flat files it stores them in a database, providing both better performance and better security and
 * protection against session fixation and session hijacking.
 *
 * Session (Zebra_Session) implements <i>session locking</i>. Session locking is a way to ensure that data is
 * correctly handled in a scenario with multiple concurrent AJAX requests. Read more about it in this excellent
 * article by Andy Bakun called {@link
 * http://thwartedefforts.org/2006/11/11/race-conditions-with-ajax-and-php-sessions/ Race Conditions with Ajax and PHP
 * Sessions}.
 *
 * This library is also a solution for applications that are scaled across multiple web servers (using a
 * load balancer or a round-robin DNS) and where the user's session data needs to be available. Storing sessions in a
 * database makes them available to all of the servers!
 *
 * Session (Zebra_Session ) supports "flashdata" - session variable which will only be available for the next server
 * request, and which will be automatically deleted afterwards. Typically used for informational or status messages
 * (for example: "data has been successfully updated").
 *
 * This is a fork of "Zebra_Session " and that was inspired by John Herren's code from
 * the {@link http://devzone.zend.com/413/trick-out-your-session-handler/ Trick out your session handler}
 * article and {@link http://shiflett.org/articles/the-truth-about-sessions Chris Shiflett}'s articles about PHP
 * sessions.
 *
 * Visit {@link http://stefangabos.ro/php-libraries/zebra-session/} for more information.
 *
 * @author  Stefan Gabos <contact@stefangabos.ro>
 * @author  Lars Moelleken <lars@moelleken.org>
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
 * @package voku\helper
 */
class Session2DB implements \SessionHandlerInterface
{
  /**
   * the name for the session variable that will be created upon script execution
   * and destroyed when instantiating this library, and which will hold information
   * about flashdata session variables
   *
   * @var string
   */
  const flashDataVarName = '_menadwork_session_flashdata_ec3asbuiad';

  /**
   * @var Db4Session
   */
  private $db;

  /**
   * @var array
   */
  private $flashdata = [];

  /*
   * @var int
   */
  private $session_lifetime;

  /**
   * @var string
   */
  private $lock_file_tmp;

  /**
   * @var bool|null
   */
  private $lock_via_mysql = true;

  /**
   * @var int
   */
  private $lock_timeout;

  /**
   * @var bool
   */
  private $lock_to_ip;

  /**
   * @var bool
   */
  private $lock_to_user_agent;

  /**
   * @var string
   */
  private $table_name = 'session_data';

  /**
   * @var string
   */
  private $table_name_lock = 'lock_data';

  /**
   * @var string
   */
  private $security_code;

  /**
   * @var string
   */
  private $_fingerprint;

  /**
   * @var string
   */
  private $_session_id;

  /**
   * Constructor of class. Initializes the class and automatically calls
   * {@link http://php.net/manual/en/function.session-start.php start_session()}.
   *
   * <code>
   * // first, connect to a database containing the sessions table
   *
   * // include the class (use the composer-"autoloader")
   * require 'vendor/autoload.php';
   *
   * // start the session
   * $session = new Session2DB();
   * </code>
   *
   * By default, the cookie used by PHP to propagate session data across multiple pages ('PHPSESSID') uses the
   * current top-level domain and subdomain in the cookie declaration.
   *
   * Example: www.domain.com
   *
   * This means that the session data is not available to other subdomains. Therefore, a session started on
   * www.domain.com will not be available on blog.domain.com. The solution is to change the domain PHP uses when it
   * sets the 'PHPSESSID' cookie by calling the line below *before* instantiating the Session library.
   *
   * <code>
   * // takes the domain and removes the subdomain
   * // blog.domain.com becoming .domain.com
   * ini_set(
   *   'session.cookie_domain',
   *    substr($_SERVER['SERVER_NAME'], strpos($_SERVER['SERVER_NAME'], '.'))
   * );
   * </code>
   *
   * From now on whenever PHP sets the 'PHPSESSID' cookie, the cookie will be available to all subdomains!
   *
   * @param string          $security_code      [Optional] The value of this argument is appended to the string created
   *                                            by
   *                                            concatenating the user's User Agent (browser) string (or an empty
   *                                            string
   *                                            if "lock_to_user_agent" is FALSE) and to the user's IP address (or an
   *                                            empty string if "lock_to_ip" is FALSE), before creating an SHA1 hash
   *                                            out
   *                                            of it and storing it in the database.
   *
   *                                          On each call this value will be generated again and compared to the
   *                                          value stored in the database ensuring that the session is correctly
   *                                          linked
   *                                          with the user who initiated the session thus preventing session
   *                                          hijacking.
   *
   *                                          <samp>To prevent session hijacking, make sure you choose a string around
   *                                          12 characters long containing upper- and lowercase letters, as well as
   *                                          digits. To simplify the process, use {@link
   *                                          https://www.random.org/passwords/?num=1&len=12&format=html&rnd=new this}
   *                                          link to generate such a random string.</samp>
   *
   * @param  int            $session_lifetime   [Optional] The number of seconds after which a session will be
   *                                            considered as <i>expired</i>.
   *
   *                                          Expired sessions are cleaned up from the database whenever the <i>garbage
   *                                          collection routine</i> is run. The probability of the <i>garbage
   *                                          collection routine</i> to be executed is given by the values of
   *                                          <i>$gc_probability</i> and <i>$gc_divisor</i>. See below.
   *
   *                                          Default is the value of <i>session.gc_maxlifetime</i> as set in in
   *                                          php.ini. Read more at {@link
   *                                          http://www.php.net/manual/en/session.configuration.php}
   *
   *                                          To clear any confusions that may arise: in reality,
   *                                          <i>session.gc_maxlifetime</i> does not represent a session's lifetime but
   *                                          the number of seconds after which a session is seen as <i>garbage</i> and
   *                                          is deleted by the <i>garbage collection routine</i>. The PHP setting that
   *                                          sets a session's lifetime is
   *                                          <i>session.cookie_lifetime</i> and is usually set to "0" - indicating
   *                                          that
   *                                          a session is active until the browser/browser tab is closed. When this
   *                                          class is used, a session is active until the browser/browser tab is
   *                                          closed and/or a session has been inactive for more than the number of
   *                                          seconds specified by <i>session.gc_maxlifetime</i>.
   *
   *                                          To see the actual value of <i>session.gc_maxlifetime</i> for your
   *                                          environment, use the {@link get_settings()} method.
   *
   *                                          Pass an empty string to keep default value.
   *
   * @param boolean         $lock_to_user_agent [Optional] Whether to restrict the session to the same User Agent (or
   *                                            browser) as when the session was first opened.
   *
   *                                          <i>The user agent check only adds minor security, since an attacker that
   *                                          hijacks the session cookie will most likely have the same user agent.</i>
   *
   *                                          In certain scenarios involving Internet Explorer, the browser will
   *                                          randomly change the user agent string from one page to the next by
   *                                          automatically switching into compatibility mode. So, on the first load
   *                                          you would have something like:
   *
   *                                          <code>Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0;
   *                                          etc...</code>
   *
   *                                          and reloading the page you would have
   *
   *                                          <code> Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0;
   *                                          etc...</code>
   *
   *                                          So, if the situation asks for this, change this value to FALSE.
   *
   *                                          Default is FALSE.
   *
   * @param boolean         $lock_to_ip         [Optional]    Whether to restrict the session to the same IP as when
   *                                            the
   *                                            session was first opened.
   *
   *                                          Use this with caution as many users have dynamic IP addresses which may
   *                                          change over time, or may come through proxies.
   *
   *                                          This is mostly useful if your know that all your users come from static
   *                                          IPs.
   *
   *                                          Default is FALSE.
   *
   * @param int             $gc_probability     [Optional]    Used in conjunction with <i>$gc_divisor</i>. It defines
   *                                            the probability that the <i>garbage collection routine</i> is started.
   *
   *                                          The probability is expressed by the formula:
   *
   *                                          <code>
   *                                          $probability = $gc_probability / $gc_divisor;
   *                                          </code>
   *
   *                                          So, if <i>$gc_probability</i> is 1 and <i>$gc_divisor</i> is 100, it
   *                                          means
   *                                          that there is a 1% chance the the <i>garbage collection routine</i> will
   *                                          be called on each request.
   *
   *                                          Default is the value of <i>session.gc_probability</i> as set in php.ini.
   *                                          Read more at {@link
   *                                          http://www.php.net/manual/en/session.configuration.php}
   *
   *                                          To see the actual value of <i>session.gc_probability</i> for your
   *                                          environment, and the computed <i>probability</i>, use the
   *                                          {@link get_settings()} method.
   *
   *                                          Pass an empty string to keep default value.
   *
   * @param int             $gc_divisor         [Optional]        Used in conjunction with <i>$gc_probability</i>. It
   *                                            defines the probability that the <i>garbage collection routine</i> is
   *                                            started.
   *
   *                                          The probability is expressed by the formula:
   *
   *                                          <code>
   *                                          $probability = $gc_probability / $gc_divisor;
   *                                          </code>
   *
   *                                          So, if <i>$gc_probability</i> is 1 and <i>$gc_divisor</i> is 100, it
   *                                          means
   *                                          that there is a 1% chance the the <i>garbage collection routine</i> will
   *                                          be called on each request.
   *
   *                                          Default is the value of <i>session.gc_divisor</i> as set in php.ini.
   *                                          Read more at {@link
   *                                          http://www.php.net/manual/en/session.configuration.php}
   *
   *                                          To see the actual value of <i>session.gc_divisor</i> for your
   *                                          environment, and the computed <i>probability</i>, use the
   *                                          {@link get_settings()} method.
   *
   *                                          Pass an empty string to keep default value.
   *
   * @param string          $table_name         [Optional]     Name of the DB table used by the class.
   *
   *                                          Default is <i>session_data</i>.
   *
   * @param int             $lock_timeout       [Optional]      The maximum amount of time (in seconds) for which a
   *                                            lock on the session data can be kept.
   *
   *                                          <i>This must be lower than the maximum execution time of the script!</i>
   *
   *                                          Session locking is a way to ensure that data is correctly handled in a
   *                                          scenario with multiple concurrent AJAX requests.
   *
   *                                          Read more about it at
   *                                          {@link
   *                                          http://thwartedefforts.org/2006/11/11/race-conditions-with-ajax-and-php-sessions/}
   *
   *                                          Default is <i>60</i>
   *
   * @param Db4Session|null $db                 [Optional] A database instance from voku\db\DB ("voku/simple-mysqli")
   *
   * @param bool            $start_session      [Optional] If you want to modify the settings via setters before
   *                                            starting the session, you can skip the session-start and do it manually
   *                                            via "Session2DB->start()".
   */
  public function __construct(string $security_code = '', int $session_lifetime = 3600, bool $lock_to_user_agent = false, bool $lock_to_ip = false, int $gc_probability = 1, int $gc_divisor = 1000, string $table_name = '', int $lock_timeout = 60, Db4Session $db = null, bool $start_session = true)
  {
    if (null !== $db) {
      $this->db = $db;
    } else {
      $this->db = DbWrapper4Session::getInstance();
    }

    // If no DB connections could be found and
    // we could not connect to the DB, then
    // trigger a fatal error message and stop execution.
    if (
        !$this->db->ping()
        &&
        !$this->db->reconnect()
    ) {
      \trigger_error('Session: No DB-Connection!', E_USER_ERROR);
    }

    $this->set_ini_settings($session_lifetime, $gc_probability, $gc_divisor);

    // we'll use this later on in order to try to prevent HTTP_USER_AGENT spoofing
    $this->set_security_code($security_code);

    // some other defaults
    $this->set_lock_to_user_agent($lock_to_user_agent);
    $this->set_lock_to_ip($lock_to_ip);
    $this->set_lock_file_tmp(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'session2db.lock.');

    // the table to be used by the class
    $this->set_table_name($table_name);

    // the maximum amount of time (in seconds) for which a process can lock the session
    $this->set_lock_timeout($lock_timeout);

    // initialize session2db
    if ($start_session === true) {
      $this->start();
    }
  }

  /**
   * @param string $session_id
   *
   * @return bool
   */
  private function _get_lock(string $session_id): bool
  {
    // skip if we don't use the lock
    if (!$this->lock_timeout) {
      return true;
    }

    // get the lock name, associated with the current session
    $look_name = $this->_lock_name($session_id);

    // try to obtain a lock with the given name and timeout

    $time = \time();
    $lock_time = (string)($time + $this->lock_timeout);
    $time = (string)$time;
    $old_lock_timeout = null;

    if ($this->lock_via_mysql === true) {

      $result_lock = $this->_get_lock_mysql_native($look_name);

    } elseif ($this->lock_via_mysql === null) {

      list($old_lock_timeout, $result_lock) = $this->_get_lock_mysql_fake($look_name, $lock_time);

    } else {

      list($old_lock_timeout, $result_lock) = $this->_get_lock_php_native($look_name, $lock_time);

    }

    if ($old_lock_timeout) {
      return ($old_lock_timeout >= $time);
    }

    // if there was an error, then stop the execution
    if (!$result_lock) {
      return false;
    }

    return true;
  }

  /**
   * @param string $look_name
   * @param string $lock_time
   *
   * @return array
   */
  private function _get_lock_mysql_fake(string $look_name, string $lock_time): array
  {
    // init
    $result_lock = false;

    $query_lock = '
    SELECT * FROM ' . $this->table_name_lock . " 
      WHERE lock_hash = '" . $this->db->escape($look_name) . "'
      LIMIT 1
    ";
    $db_result = $this->db->query($query_lock);
    $old_lock_timeout = $db_result->fetchColumn('lock_time');

    if (!$old_lock_timeout) {
      $query_lock = '
      INSERT INTO ' . $this->table_name_lock . " 
        SET 
          lock_hash = '" . $this->db->escape($look_name) . "',
          lock_time = '" . $this->db->escape($lock_time) . "'
      ";
      if ($this->db->query($query_lock) !== false) {
        $result_lock = true;
      }
    }

    return [$old_lock_timeout, $result_lock];
  }

  /**
   * @param string $look_name
   *
   * @return bool
   */
  private function _get_lock_mysql_native($look_name): bool
  {
    $query_lock = "SELECT GET_LOCK('" . $this->db->escape($look_name) . "', " . $this->db->escape($this->lock_timeout) . ') as result';
    $db_result = $this->db->query($query_lock);
    $result_lock = (bool)$db_result->fetchColumn('result');

    return $result_lock;
  }

  /**
   * @param string $look_name
   * @param string $lock_time
   *
   * @return array
   */
  private function _get_lock_php_native(string $look_name, string $lock_time): array
  {
    // init
    $result_lock = false;
    $lock_file = $this->lock_file_tmp . $look_name;

    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    $fp = @fopen($lock_file, 'rb');
    $old_lock_timeout = '';
    if ($fp && flock($fp, LOCK_SH | LOCK_NB)) {
      while (!feof($fp)) {
        $line = fgets($fp);
        $old_lock_timeout .= $line;
      }
      flock($fp, LOCK_UN);
    }
    if ($fp) {
      fclose($fp);
    }

    if (!$old_lock_timeout) {
      /** @noinspection PhpUsageOfSilenceOperatorInspection */
      $fp = @fopen($lock_file, 'ab');
      if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
        ftruncate($fp, 0);
        $result_lock = fwrite($fp, $lock_time);
        fflush($fp);
        flock($fp, LOCK_UN);
      }
      fclose($fp);
    }

    return [$old_lock_timeout, $result_lock];
  }

  /**
   * @param string $session_id
   *
   * @return string
   */
  private function _lock_name(string $session_id): string
  {
    // MySQL >=5.7.5 | the new GET_LOCK implementation has a limit on the identifier name
    // -> https://bugs.mysql.com/bug.php?id=80721
    return 'session_' . \sha1($session_id);
  }

  /**
   * Manages flashdata behind the scenes.
   *
   * @access private
   */
  public function _manage_flashdata()
  {
    // if there is flashdata to be handled
    if (!empty($this->flashdata)) {

      // iterate through all the entries
      foreach ($this->flashdata as $variable => $counter) {

        // increment counter representing server requests
        $this->flashdata[$variable]++;

        // if we're past the first server request
        if ($this->flashdata[$variable] > 1) {

          // unset the session variable & stop tracking
          unset($_SESSION[$variable], $this->flashdata[$variable]);
        }
      }

      // if there is any flashdata left to be handled
      // ... then store data in a temporary session variable
      if (!empty($this->flashdata)) {
        $_SESSION[self::flashDataVarName] = \serialize($this->flashdata);
      }
    }
  }

  /**
   * @param string $session_id
   *
   * @return bool
   */
  private function _release_lock(string $session_id): bool
  {
    // skip if we don't use the lock
    if (!$this->lock_timeout) {
      return true;
    }

    // get the lock name, associated with the current session
    $look_name = $this->_lock_name($session_id);

    // release the lock associated with the current session

    if ($this->lock_via_mysql === true) {

      $result_unlock = $this->_release_lock_sql_native($look_name);

    } elseif ($this->lock_via_mysql === null) {

      $result_unlock = $this->_release_lock_sql_fake($look_name);

    } else {

      $result_unlock = $this->_release_lock_php_native($look_name);

    }

    // if there was an error, then stop the execution
    if (!$result_unlock) {
      return false;
    }

    return true;
  }

  /**
   * @param string $look_name
   *
   * @return bool
   */
  private function _release_lock_php_native(string $look_name): bool
  {
    $lock_file = $this->lock_file_tmp . $look_name;
    if (\file_exists($lock_file) === true) {
      /** @noinspection PhpUsageOfSilenceOperatorInspection */
      $result_unlock = @unlink($lock_file);
    } else {
      $result_unlock = true;
    }

    return $result_unlock;
  }

  /**
   * @param string $look_name
   *
   * @return bool
   */
  private function _release_lock_sql_fake(string $look_name): bool
  {
    $query = 'DELETE FROM ' . $this->table_name_lock . "
        WHERE lock_hash = '" . $this->db->escape($look_name) . "'
      ";
    $result_unlock = (bool)$this->db->query($query);

    return $result_unlock;
  }

  /**
   * @param string $look_name
   *
   * @return bool
   */
  private function _release_lock_sql_native(string $look_name): bool
  {
    $query = "SELECT RELEASE_LOCK('" . $this->db->escape($look_name) . "') as result";
    $db_result = $this->db->query($query);
    $result_unlock = (bool)$db_result->fetchColumn('result');

    return $result_unlock;
  }

  /**
   * Custom close() function.
   *
   * @return bool
   */
  public function close(): bool
  {
    // 1. write all data into the db
    \session_register_shutdown();

    // 2. release the lock, if there is a lock
    if ($this->_session_id) {
      $this->_release_lock($this->_session_id);
    }

    // 3. close the db-connection
    $this->db->close();

    return true;
  }

  /**
   * Custom destroy() function.
   *
   * @param int $session_id
   *
   * @return bool
   */
  public function destroy($session_id): bool
  {
    // deletes the current locks from the database
    if ($this->lock_via_mysql === null) {
      $queryLock = 'DELETE FROM ' . $this->table_name_lock . "
        WHERE lock_time < '" . $this->db->escape(\time()) . "'
      ";
      $this->db->query($queryLock);
    }

    // deletes the current session id from the database

    $query = 'DELETE FROM ' . $this->table_name . "
      WHERE session_id = '" . $this->db->escape($session_id) . "'
    ";
    $result = $this->db->query($query);

    return ($result > 0);
  }

  /**
   * Custom gc() function (garbage collector).
   *
   * @param int $maxlifetime <p>INFO: must be set for the interface.</P>
   *
   * @return int
   */
  public function gc($maxlifetime): int
  {
    // deletes expired locks from database
    if ($this->lock_via_mysql === null) {
      $queryLock = 'DELETE FROM ' . $this->table_name_lock . "
        WHERE lock_time < '" . $this->db->escape(\time()) . "'
      ";
      $this->db->query($queryLock);
    }

    // deletes expired sessions from database

    $query = 'DELETE FROM ' . $this->table_name . "
      WHERE session_expire < '" . $this->db->escape(\time()) . "'
    ";

    return (int)$this->db->query($query);
  }

  /**
   * Custom open() function.
   *
   * @param string $save_path
   * @param string $session_name
   *
   * @return bool
   */
  public function open($save_path, $session_name): bool
  {
    // session_regenerate_id() --->
    //
    // PHP5: call -> "destroy"
    //
    // PHP7: call -> "destroy", "read", "close", "open", "read"
    //
    // WARNING: PHP >= 7.0 will reuse $this session-handler-object, so we need to reconnect to the database
    //
    if (!$this->db->ping()) {
      $this->db->reconnect();
    }

    return $this->db->ping();
  }

  /**
   * Custom read() function.
   *
   * @param $session_id
   *
   * @return string
   */
  public function read($session_id): string
  {
    // Needed by write() to detect session_regenerate_id() calls
    $this->_session_id = $session_id;

    // try to obtain a lock with the given name and timeout
    $locked = $this->_get_lock($session_id);

    // if there was an error, then stop the execution
    if ($locked === false) {
      \trigger_error('Session: Could not obtain session lock!', E_USER_ERROR);
    }

    $hash = $this->get_fingerprint();

    $query = 'SELECT
        session_data
      FROM
        ' . $this->table_name . "
      WHERE session_id = '" . $this->db->escape($session_id) . "'
      AND hash = '" . $this->db->escape($hash) . "'
      AND session_expire > '" . $this->db->escape(\time()) . "'
      LIMIT 1
    ";

    $data = $this->db->fetchColumn($query, 'session_data');

    // if anything was found
    if ($data) {
      // don't bother with the unserialization - PHP handles this automatically
      return $data;
    }

    // on error return an empty string - this HAS to be an empty string
    return '';
  }

  /**
   * Custom write() function.
   *
   * @param string $session_id
   * @param string $session_data
   *
   * @return bool|string
   */
  public function write($session_id, $session_data)
  {
    // check if the "$session_id" was regenerated
    if (
        $this->_session_id
        &&
        $session_id !== $this->_session_id
    ) {
      if (
          $this->_release_lock($this->_session_id) === false
          ||
          $this->_get_lock($session_id) === false
      ) {
        return false;
      }

      $this->_session_id = $session_id;
    }

    $hash = $this->get_fingerprint();
    $expire_time = \time() + (int)$this->session_lifetime;

    $query_select = 'SELECT session_id FROM ' . $this->table_name . "
      WHERE session_id = '" . $this->db->escape($session_id) . "'
      LIMIT 0, 1
    ";
    $result_select = $this->db->query($query_select);

    if ($result_select->num_rows) {

      $query = 'UPDATE ' . $this->table_name . "
        SET 
          hash = '" . $this->db->escape($hash) . "',
          session_data = '" . $this->db->escape($session_data) . "',
          session_expire = '" . $this->db->escape($expire_time) . "'
        WHERE session_id = '" . $this->db->escape($session_id) . "';
      ";

    } else {

      $query = 'INSERT INTO
        ' . $this->table_name . "
        (
          session_id,
          hash,
          session_data,
          session_expire
        )
        VALUES
        (
          '" . $this->db->escape($session_id) . "',
          '" . $this->db->escape($hash) . "',
          '" . $this->db->escape($session_data) . "',
          '" . $this->db->escape($expire_time) . "'
        )
        ON DUPLICATE KEY UPDATE
          session_data = '" . $this->db->escape($session_data) . "',
          session_expire = '" . $this->db->escape($expire_time) . "'
      ";

    }

    // insert OR update session's data
    $result = $this->db->query($query);

    return ($result !== false);
  }

  private function generate_fingerprint()
  {
    //  reads session data associated with a session id, but only if
    //  -   the session ID exists;
    //  -   the session has not expired;
    //  -   if lock_to_user_agent is TRUE and the HTTP_USER_AGENT is the same as the one who had previously been associated with this particular session;
    //  -   if lock_to_ip is TRUE and the host is the same as the one who had previously been associated with this particular session;
    $hash = '';

    // if we need to identify sessions by also checking the user agent
    if ($this->lock_to_user_agent && isset($_SERVER['HTTP_USER_AGENT'])) {
      $hash .= $_SERVER['HTTP_USER_AGENT'];
    }

    // if we need to identify sessions by also checking the host
    if ($this->lock_to_ip && isset($_SERVER['REMOTE_ADDR'])) {
      $hash .= $_SERVER['REMOTE_ADDR'];
    }

    // append this to the end
    $hash .= $this->security_code;

    // save the fingerprint-hash into the current object
    $this->_fingerprint = \sha1($hash);
  }

  /**
   * Get the number of active sessions - sessions that have not expired.
   *
   * <i>The returned value does not represent the exact number of active users as some sessions may be unused
   * although they haven't expired.</i>
   *
   * <code>
   * // first, connect to a database containing the sessions table
   *
   * //  include the class (use the composer-"autoloader")
   * require 'vendor/autoload.php';
   *
   * //  start the session
   * $session = new Session2DB();
   *
   * //  get the (approximate) number of active sessions
   * $active_sessions = $session->get_active_sessions();
   * </code>
   *
   * @return int <p>Returns the number of active (not expired) sessions.</p>
   */
  public function get_active_sessions(): int
  {
    // call the garbage collector
    $this->gc($this->session_lifetime);

    $query = 'SELECT COUNT(session_id) AS count
      FROM ' . $this->table_name . '
    ';

    // counts the rows from the database
    return (int)$this->db->fetchColumn($query, 'count');
  }

  /**
   * @return string
   */
  public function get_fingerprint(): string
  {
    return $this->_fingerprint;
  }

  /**
   * Queries the system for the values of <i>session.gc_maxlifetime</i>, <i>session.gc_probability</i> and
   * <i>session.gc_divisor</i> and returns them as an associative array.
   *
   * To view the result in a human-readable format use:
   * <code>
   * //  include the class (use the composer-"autoloader")
   * require 'vendor/autoload.php';
   *
   * //  start the session
   * $session = new Session2DB();
   *
   * //  get default settings
   * print_r('<pre>');
   * print_r($session->get_settings());
   *
   * //  would output something similar to (depending on your actual settings)
   * //  array
   * //  (
   * //    [session.gc_maxlifetime] => 1440 seconds (24 minutes)
   * //    [session.gc_probability] => 1
   * //    [session.gc_divisor] => 1000
   * //    [probability] => 0.1%
   * //  )
   * </code>
   *
   * @return string[] <p>
   *                  Returns the values of <i>session.gc_maxlifetime</i>, <i>session.gc_probability</i> and
   *                  <i>session.gc_divisor</i> as an associative array.
   *                  </p>
   */
  public function get_settings(): array
  {
    // get the settings
    $gc_maxlifetime = \ini_get('session.gc_maxlifetime');
    $gc_probability = \ini_get('session.gc_probability');
    $gc_divisor = \ini_get('session.gc_divisor');

    // return them as an array
    return [
        'session.gc_maxlifetime' => $gc_maxlifetime . ' seconds (' . \round($gc_maxlifetime / 60) . ' minutes)',
        'session.gc_probability' => $gc_probability,
        'session.gc_divisor'     => $gc_divisor,
        'probability'            => $gc_probability / $gc_divisor * 100 . '%',
    ];
  }

  /**
   * Regenerates the session id.
   *
   * <b>Call this method whenever you do a privilege change in order to prevent session hijacking!</b>
   *
   * <code>
   * // first, connect to a database containing the sessions table
   *
   * // include the class (use the composer-"autoloader")
   * require 'vendor/autoload.php';
   *
   * // start the session
   * $session = new Session2DB();
   *
   * // regenerate the session's ID
   * $session->regenerate_id();
   * </code>
   */
  public function regenerate_id()
  {
    // regenerates the id (create a new session with a new id and containing the data from the old session)
    // also, delete the old session
    \session_regenerate_id(true);
  }

  /**
   * @return bool
   */
  private function register_session_handler(): bool
  {
    // WARNING: PHP 7.2 throws a warning for "session"-ini, so we catch it here ...
    if (
        PHP_SAPI !== 'cli'
        &&
        \headers_sent() === true
    ) {
      \trigger_error('Cannot change save handler when headers already sent', E_USER_WARNING);
    }

    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    return @\session_set_save_handler($this, true);
  }

  /**
   * Sets a "flashdata" session variable which will only be available for the next server request, and which will be
   * automatically deleted afterwards.
   *
   * Typically used for informational or status messages (for example: "data has been successfully updated").
   *
   * <code>
   * // first, connect to a database containing the sessions table
   *
   * //  include the class (use the composer-"autoloader")
   * require 'vendor/autoload.php';
   *
   * //  start the session
   * $session = new Session2DB();
   *
   * // set "myvar" which will only be available
   * // for the next server request and will be
   * // automatically deleted afterwards
   * $session->set_flashdata('myvar', 'myval');
   * </code>
   *
   * Flashdata session variables can be retrieved as any other session variable:
   *
   * <code>
   * if (isset($_SESSION['myvar'])) {
   *   // do something here but remember that the
   *   // flashdata session variable is available
   *   // for a single server request after it has
   *   // been set!
   * }
   * </code>
   *
   * @param string $name  <p>The name of the session variable.</p>
   * @param mixed  $value <p>The value of the session variable.</p>
   *
   * @return $this
   */
  public function set_flashdata(string $name, $value): self
  {
    // set session variable
    $_SESSION[$name] = $value;

    // initialize the counter for this flashdata
    $this->flashdata[$name] = 0;

    return $this;
  }

  /**
   * @param int $session_lifetime
   * @param int $gc_probability
   * @param int $gc_divisor
   *
   * @return $this
   */
  public function set_ini_settings(int $session_lifetime, int $gc_probability, int $gc_divisor): self
  {
    // WARNING: PHP 7.2 throws a warning for "session"-ini, so we catch it here ...
    if (
        PHP_SAPI !== 'cli'
        &&
        \headers_sent() === true
    ) {
      \trigger_error('You cannot change the session module\'s ini settings at this time', E_USER_WARNING);
    }

    // Prevent session-fixation
    // See: http://en.wikipedia.org/wiki/Session_fixation
    //
    // Tell the browser not to expose the cookie to client side scripting,
    // this makes it harder for an attacker to hijack the session ID.
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    @\ini_set('session.cookie_httponly', '1');

    // Make sure that PHP only uses cookies for sessions and disallow session ID passing as a GET parameter,
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    @\ini_set('session.session.use_only_cookies', '1');

    // PHP 7.1 Incompatible Changes
    // -> http://php.net/manual/en/migration71.incompatible.php
    if (Bootup::is_php('7.1') === false) {
      // Use the SHA-1 hashing algorithm
      /** @noinspection PhpUsageOfSilenceOperatorInspection */
      @ini_set('session.hash_function', '1');

      // Increase character-range of the session ID to help prevent brute-force attacks.
      //
      // INFO: The possible values are '4' (0-9, a-f), '5' (0-9, a-v), and '6' (0-9, a-z, A-Z, "-", ",").
      /** @noinspection PhpUsageOfSilenceOperatorInspection */
      @ini_set('session.hash_bits_per_character', '6');
    }

    // make sure session cookies never expire so that session lifetime
    // will depend only on the value of $session_lifetime
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    @\ini_set('session.cookie_lifetime', '0');

    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    @\ini_set('session.gc_maxlifetime', (string)$session_lifetime);
    $this->session_lifetime = \ini_get('session.gc_maxlifetime');

    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    @\ini_set('session.gc_probability', (string)$gc_probability);

    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    @\ini_set('session.gc_divisor', (string)$gc_divisor);

    return $this;
  }

  /**
   * @param string $lock_file_tmp
   *
   * @return $this
   */
  public function set_lock_file_tmp(string $lock_file_tmp): self
  {
    if ($lock_file_tmp) {
      $this->lock_file_tmp = $lock_file_tmp;
    }

    return $this;
  }

  /**
   * @param int $lock_timeout
   *
   * @return $this
   */
  public function set_lock_timeout(int $lock_timeout): self
  {
    $this->lock_timeout = $lock_timeout;

    return $this;
  }

  /**
   * @param bool $lock_to_ip
   *
   * @return $this
   */
  public function set_lock_to_ip(bool $lock_to_ip): self
  {
    $this->lock_to_ip = $lock_to_ip;

    $this->generate_fingerprint();

    return $this;
  }

  /**
   * @param bool $lock_to_user_agent
   *
   * @return $this
   */
  public function set_lock_to_user_agent(bool $lock_to_user_agent): self
  {
    $this->lock_to_user_agent = $lock_to_user_agent;

    $this->generate_fingerprint();

    return $this;
  }

  /**
   * @param string $security_code
   *
   * @return $this
   */
  public function set_security_code(string $security_code): self
  {
    // fallback for the security-code
    if (!$security_code || $security_code = '###set_the_security_key###') {
      $security_code = 'sEcUrmenadwork_))';
    }

    $this->security_code = $security_code;

    $this->generate_fingerprint();

    return $this;
  }

  /**
   * @param string $table_name
   *
   * @return $this
   */
  public function set_table_name(string $table_name): self
  {
    if ($table_name) {
      $this->table_name = $this->db->quote_string($table_name);
    }

    return $this;
  }

  /**
   * @param string $table_name_lock
   *
   * @return $this
   */
  public function set_table_name_lock(string $table_name_lock): self
  {
    if ($table_name_lock) {
      $this->table_name_lock = $this->db->quote_string($table_name_lock);
    }

    return $this;
  }

  /**
   * @return bool
   */
  public function start(): bool
  {
    // register the new session-handler
    $result = $this->register_session_handler();
    if ($result === false) {
      return false;
    }

    // start the session
    if (PHP_SAPI === 'cli') {
      $_SESSION = [];
      $result = true;
    } else {
      $result = \session_start();
    }
    if ($result === false) {
      return false;
    }

    // if there are any flashdata variables that need to be handled
    if (isset($_SESSION[self::flashDataVarName])) {

      // store them
      $this->flashdata = \unserialize($_SESSION[self::flashDataVarName], []);

      // and destroy the temporary session variable
      unset($_SESSION[self::flashDataVarName]);
    }

    // handle flashdata after script execution
    \register_shutdown_function(
        [
            $this,
            '_manage_flashdata',
        ]
    );

    return $result;
  }

  /**
   * Deletes all data related to the session
   *
   * <code>
   * // first, connect to a database containing the sessions table
   *
   * // include the class (use the composer-"autoloader")
   * require 'vendor/autoload.php';
   *
   * // start the session
   * $session = new Session2DB();
   *
   * // end current session
   * $session->stop();
   * </code>
   */
  public function stop()
  {
    if (PHP_SAPI === 'cli') {
      return;
    }

    // if a cookie is used to pass the session id
    if (\ini_get('session.use_cookies')) {
      // get session cookie's properties
      $params = \session_get_cookie_params();

      // unset the cookie
      \setcookie(\session_name(), '', \time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    \session_unset();
    \session_destroy();
  }

  /**
   * @param bool|null $boolOrNull <p>
   *                              <strong>true</strong> => use mysql GET_LOCK() / RELEASE_LOCK()<br>
   *                              <strong>false</strong> => use php flock() + LOCK_EX<br>
   *                              <strong>null</strong> => use mysql + extra lock-table<br>
   *                              </p>
   *
   * @return $this
   */
  public function use_lock_via_mysql($boolOrNull): self
  {
    $this->lock_via_mysql = $boolOrNull;

    return $this;
  }

}
