CREATE TABLE lock_data (
  lock_hash varchar(128) NOT NULL,
  lock_time INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY lock_hash (`lock_hash`),
  KEY lock_time (`lock_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
