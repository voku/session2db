<?php

declare(strict_types=1);

namespace voku\helper;

interface Db4Session
{
    /**
     * @return bool
     */
    public function close(): bool;

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
     * @return array|string
     *                      <p>empty string on error</p>
     */
    public function fetchColumn(string $sql, string $string);

    /**
     * @return bool
     */
    public function ping(): bool;

    /**
     * @param string $sql
     *
     * @return false|mixed
     *                     <p>false on error</p>
     */
    public function query(string $sql);

    /**
     * @param string $string
     *
     * @return string
     */
    public function quote_string(string $string): string;

    /**
     * @return bool
     */
    public function reconnect(): bool;
}
