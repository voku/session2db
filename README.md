[![Build Status](https://travis-ci.org/voku/session2db.svg?branch=master)](https://travis-ci.org/voku/session2db)
[![Coverage Status](https://coveralls.io/repos/github/voku/session2db/badge.svg?branch=master)](https://coveralls.io/github/voku/session2db?branch=master)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/836db772ff9443b18103d6a6c6ee35eb)](https://www.codacy.com/app/voku/session2db)
[![Latest Stable Version](https://poser.pugx.org/voku/session2db/v/stable)](https://packagist.org/packages/voku/session2db) 
[![Total Downloads](https://poser.pugx.org/voku/session2db/downloads)](https://packagist.org/packages/voku/session2db) 
[![License](https://poser.pugx.org/voku/session2db/license)](https://packagist.org/packages/voku/session2db)
[![Donate to this project using Paypal](https://img.shields.io/badge/paypal-donate-yellow.svg)](https://www.paypal.me/moelleken)
[![Donate to this project using Patreon](https://img.shields.io/badge/patreon-donate-yellow.svg)](https://www.patreon.com/voku)

# :crown: Session2DB

#### A drop-in replacement for PHP's default session handler which stores session data in a database, providing both better performance and better security and protection against session fixation and session hijacking.

Session2DB implements *session locking* - a way to ensure that data is correctly handled in a scenario with multiple concurrent AJAX requests.

It is also a solution for applications that are scaled across multiple web servers (using a load balancer or a round-robin DNS) and where the user's session data needs to be available. Storing sessions in a database makes them available to all of the servers!

The library supports "flashdata" - session variable which will only be available for the next server request, and which will be automatically deleted afterwards. Typically used for informational or status messages (for example: "data has been successfully updated").

Session2DB is was inspired by John Herren's code from the [Trick out your session handler](http://devzone.zend.com/413/trick-out-your-session-handler/) article and [Chris Shiflett](http://shiflett.org/articles/the-truth-about-sessions)'s articles about PHP sessions and based on [Zebra_Session](https://github.com/stefangabos/Zebra_Session). 

The code is heavily commented and generates no warnings/errors/notices when PHP's error reporting level is set to E_ALL.

### Requirements

PHP 7.x with the **mysqli extension** activated, MySQL 5.x+ (recommanded: **mysqlnd extension**)

### How to install

```shell
composer require voku/session2db
```

### How to use

After installing, you will need to initialise the database table from the *install* directory from this repo, it will containing a file named *session_data.sql*. This file contains the SQL code that will create a table that is used by the class to store session data. Import or execute the SQL code using your preferred MySQL manager (like phpMyAdmin or the fantastic Adminer) into a database of your choice.

*Note that this class assumes that there is an active connection to a MySQL database and it does not attempt to create one!

```php
//
// simple (dirty) example
//

<?php
    use voku\db\DB;
    use voku\helper\Session2DB;
    
    DB::getInstance('hostname', 'username', 'password', 'database');
    new Session2DB();
    
    // from now on, use sessions as you would normally
    // this is why it is called a "drop-in replacement" :)
    $_SESSION['foo'] = 'bar';
```

```php
//
// extended example
//

<?php
    use voku\db\DB;
    use voku\helper\DbWrapper4Session;
    use voku\helper\Session2DB;

    // include autoloader
    require_once 'composer/autoload.php';

    // initialize the database connection e.g. via "voku\db\DB"-class
    $db = DB::getInstance(
        'hostname', // e.g. localhost
        'username', // e.g. user_1
        'password', // e.g. ******
        'database', // e.g. db_1
        'port',     // e.g. 3306
        'charset',  // e.g. utf8mb4
        true,       // e.g. true|false (exit_on_error)
        true,       // e.g. true|false (echo_on_error)
        '',         // e.g. 'framework\Logger' (logger_class_name)
        ''          // e.g. 'DEBUG' (logger_level)
    );
    
    // you can also use you own database implementation via the "Db4Session"-interface,
    // take a look at the "DbWrapper4Session"-class for a example
    $db_wrapper = new DbWrapper4Session($db);
    
    // initialize "Session to DB"
    new Session2DB(
      'add_your_own_security_code_here', // security_code
      0,                                 // session_lifetime
      false,                             // lock_to_user_agent 
      false,                             // lock_to_ip
      1,                                 // gc_probability 
      1000,                              // gc_divisor 
      'session_data',                    // table_name
      60,                                // lock_timeout 
      $db_wrapper,                       // db (must implement the "Db4Session"-interface)
      true                               // start_session (start the session-handling automatically, otherwise you need to use session2db->start() afterwards)
    );

    // from now on, use sessions as you would normally
    // this is why it is called a "drop-in replacement" :)
    $_SESSION['foo'] = 'bar';

    // data is in the database!
```

### Support

For support and donations please visit [Github](https://github.com/voku/session2db/) | [Issues](https://github.com/voku/session2db/issues) | [PayPal](https://paypal.me/moelleken) | [Patreon](https://www.patreon.com/voku).

For status updates and release announcements please visit [Releases](https://github.com/voku/session2db/releases) | [Twitter](https://twitter.com/suckup_de) | [Patreon](https://www.patreon.com/voku/posts).

For professional support please contact [me](https://about.me/voku).

### Thanks

- Thanks to [GitHub](https://github.com) (Microsoft) for hosting the code and a good infrastructure including Issues-Managment, etc.
- Thanks to [IntelliJ](https://www.jetbrains.com) as they make the best IDEs for PHP and they gave me an open source license for PhpStorm!
- Thanks to [Travis CI](https://travis-ci.com/) for being the most awesome, easiest continous integration tool out there!
- Thanks to [StyleCI](https://styleci.io/) for the simple but powerfull code style check.
- Thanks to [PHPStan](https://github.com/phpstan/phpstan) && [Psalm](https://github.com/vimeo/psalm) for relly great Static analysis tools and for discover bugs in the code!
