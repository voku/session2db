[![Build Status](https://travis-ci.org/voku/session2db.svg?branch=master)](https://travis-ci.org/voku/session2db)
[![Coverage Status](https://coveralls.io/repos/voku/session2db/badge.svg)](https://coveralls.io/r/voku/session2db)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/voku/session2db/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/voku/session2db/?branch=master)
[![Codacy Badge](https://www.codacy.com/project/badge/836db772ff9443b18103d6a6c6ee35eb)](https://www.codacy.com/app/voku/session2db)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/32d82172-bf23-4b04-bef9-86c64d498763/mini.png)](https://insight.sensiolabs.com/projects/32d82172-bf23-4b04-bef9-86c64d498763)
[![Dependency Status](https://www.versioneye.com/php/voku:session2db/dev-master/badge.svg)](https://www.versioneye.com/php/voku:session2db/dev-master)
[![Total Downloads](https://poser.pugx.org/voku/session2db/downloads)](https://packagist.org/packages/voku/session2db)
[![License](https://poser.pugx.org/voku/session2db/license.svg)](https://packagist.org/packages/voku/session2db)

##Session2DB

####A drop-in replacement for PHP's default session handler which stores session data in a database, providing both better performance and better security and protection against session fixation and session hijacking.

Session2DB implements *session locking* - a way to ensure that data is correctly handled in a scenario with multiple concurrent AJAX requests.

It is also a solution for applications that are scaled across multiple web servers (using a load balancer or a round-robin DNS) and where the user's session data needs to be available. Storing sessions in a database makes them available to all of the servers!

The library supports "flashdata" - session variable which will only be available for the next server request, and which will be automatically deleted afterwards. Typically used for informational or status messages (for example: "data has been successfully updated").

Session2DB is was inspired by John Herren's code from the [Trick out your session handler](http://devzone.zend.com/413/trick-out-your-session-handler/) article and [Chris Shiflett](http://shiflett.org/articles/the-truth-about-sessions)'s articles about PHP sessions.

The code is heavily commented and generates no warnings/errors/notices when PHP's error reporting level is set to E_ALL.

##Features

- acts as a wrapper for PHP’s default session handling functions, but instead of storing session data in flat files it stores them in a database, providing better security and better performance

- it is a drop-in and seamingless replacement for PHP’s default session handler: PHP sessions will be used in the same way as prior to using the library; you don’t need to change any existing code!

- implements *row locks*, ensuring that data is correctly handled in scenarios with multiple concurrent AJAX requests

- because session data is stored in a database, the library represents a solution for applications that are scaled across multiple web servers (using a load balancer or a round-robin DNS)

- has comprehensive documentation

- the code is heavily commented and generates no warnings/errors/notices when PHP’s error reporting level is set to E_ALL

## Requirements

PHP 5+ with the **mysqli extension** activated, MySQL 4.1.22+

## How to install

```shell
composer require voku/session2db
```

## How to use

Download the latest version, unpack it, and put it in a place accessible to your scripts. After unpacking, you will notice a directory called *install* containing a file named *session_data.sql*. This file contains the SQL code that will create a table that is used by the class to store session data. Import or execute the SQL code using your preferred MySQL manager (like phpMyAdmin or the fantastic Adminer) into a database of your choice.

*Note that this class assumes that there is an active connection to a MySQL database and it does not attempt to create one! If you really need the class to make a database connection, put the code in the "open" method of the class.*

```php
<?php

    // include autoloader
    require_once 'vendor/autoload.php';

    $session = new voku/helper/Session2DB();

    // from now on, use sessions as you would normally
    // this is why it is called a "drop-in replacement" :)
    $_SESSION['foo'] = 'bar';

    // data is in the database!

?>
```
