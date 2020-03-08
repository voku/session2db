# Changelog 4.0.4 (2020-03-08)

* fix typo in the "security-key-fallback" code | thanks @svgaman
* fix typo in php settings
* fix reported problems from phpstan


# Changelog 4.0.3 (2019-10-18)

* use more secure session settings


# Changelog 4.0.2 (2018-12-29)

* update "Simple-MySQL"-dependency use v7 or v8
* use phpcs fixer for the code-style


# Changelog 4.0.1 (2018-04-29)

* fix "integrity constraint violation" 

  -> via "ON DUPLICATE KEY UPDATE" in the sql-query


# Chanelog 4.0.0 (2017-12-23)

* update "Portable UTF8" from v4 -> v5
  
  -> this is a breaking change without API-changes - but the requirement from 
     "Portable UTF8" has been changed (it no longer requires all polyfills from Symfony)


# Chanelog 3.3.0 (2017-12-20)

* use more php7 type-hints
* add "$start_session" to the "Session2DB"-constructor

-> If you want to modify the settings via setters before  starting the session, you can skip the session-start and do it manually via "Session2DB->start()"


# Changelog 3.2.1 (2017-12-14)

* use php7 type-hints


# Changelog 3.2.0 (2017-12-14)

* edit "Session2DB->use_lock_via_mysql(bool|null)"

   - true => use mysql GET_LOCK() / RELEASE_LOCK()
   - false => use php flock() + LOCK_EX
   - null => use mysql + extra lock-table


# Changelog 3.1.0 (2017-12-14)

* add "Session2DB->use_lock_via_mysql(bool)"
* use new version of "Simple MySQLi" (voku/simple-mysqli)


# Changelog 3.0.0 (2017-11-25)

* drop support for PHP < 7.0
* use "strict_types"


# Changelog 2.1.0 (2017-12-20)

* backport changes from the "master"-branch into "php_old"-branch


# Changelog 2.0.0 (2017-10-15)

* add a interface && a wrapper class for the database-connection
