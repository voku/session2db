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
