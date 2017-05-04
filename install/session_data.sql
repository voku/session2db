CREATE TABLE `session_data` (
  `session_id` varchar(128) NOT NULL default '',
  `hash` varchar(128) NOT NULL default '',
  `session_data` blob NOT NULL,
  `session_expire` int(11) NOT NULL default 0,
  PRIMARY KEY session_id (`session_id`),
  KEY hash (`hash`),
  KEY session_expire (`session_expire`),
  KEY select_helper_index (`session_id`, `hash`, `session_expire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
