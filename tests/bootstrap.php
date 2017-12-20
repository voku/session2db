<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

# running from the cli doesn't set $_SESSION
if (!isset($_SESSION)) {
  $_SESSION = [];
}
