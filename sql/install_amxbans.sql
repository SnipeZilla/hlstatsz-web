-- AMXBans tables for HLstatsZ
--
-- The structure of AMXBans 6 and its default rows, as its own installer creates them, for the AMXBans plugin of the
-- GoldSrc servers and the bans pages of HLstatsZ (its website can use them too). Admin > Bans > Bans Settings runs
-- this file when the database named by DB_AMXNAME in config.php has no AMXBans tables. No web admin is created:
-- HLstatsZ manages the in-game admins and the servers itself (Admin > Bans > AMXBans).
--
-- {prefix} is DB_AMXPREFIX: its tables are named {prefix}_bans, {prefix}_amxadmins... ('amx': amx_bans).
-- {charset} and {collate} are DB_CHARSET and DB_COLLATE, the ones of HLstatsZ's tables.
--
-- To create them by hand instead, replace the three placeholders and import it into the AMXBans database.

CREATE TABLE IF NOT EXISTS `{prefix}_admins_servers` (
  `admin_id` int(11) NOT NULL,
  `server_id` int(11) NULL,
  `custom_flags` varchar(32) NOT NULL,
  `use_static_bantime` enum('yes','no') NOT NULL DEFAULT 'yes'
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_amxadmins` (
  `id` int(12) NOT NULL auto_increment,
  `username` varchar(32) NULL,
  `password` varchar(32) NULL,
  `access` varchar(32) NULL,
  `flags` varchar(32) NULL,
  `steamid` varchar(32) NULL,
  `nickname` varchar(32) NULL,
  `ashow` int(11) NULL,
  `created` int(11) NULL,
  `expired` int(11) NULL,
  `days` int(11) NULL,
  PRIMARY KEY (`id`),
  KEY `steamid` (`steamid`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_bans` (
  `bid` int(11) NOT NULL auto_increment,
  `player_ip` varchar(32) NULL,
  `player_id` varchar(35) NULL,
  `player_nick` varchar(100) NULL DEFAULT 'Unknown',
  `admin_ip` varchar(32) NULL,
  `admin_id` varchar(35) NULL,
  `admin_nick` varchar(100) NULL DEFAULT 'Unknown',
  `ban_type` varchar(10) NULL DEFAULT 'S',
  `ban_reason` varchar(100) NULL,
  `ban_created` int(11) NULL,
  `ban_length` int(11) NULL,
  `server_ip` varchar(32) NULL,
  `server_name` varchar(100) NULL DEFAULT 'Unknown',
  `ban_kicks` int(11) NOT NULL DEFAULT '0',
  `expired` int(1) NOT NULL DEFAULT '0',
  `imported` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`bid`),
  KEY `player_id` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_bans_edit` (
  `id` int(11) NOT NULL auto_increment,
  `bid` int(11) NOT NULL,
  `edit_time` int(11) NOT NULL,
  `admin_nick` varchar(32) NOT NULL DEFAULT 'unknown',
  `edit_reason` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_bbcode` (
  `id` int(11) NOT NULL auto_increment,
  `open_tag` varchar(32) NULL,
  `close_tag` varchar(32) NULL,
  `url` varchar(32) NULL,
  `name` varchar(32) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_comments` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(35) NULL,
  `comment` text NULL,
  `email` varchar(100) NULL,
  `addr` varchar(32) NULL,
  `date` int(11) NULL,
  `bid` int(11) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_files` (
  `id` int(11) NOT NULL auto_increment,
  `upload_time` int(11) NULL,
  `down_count` int(11) NULL,
  `bid` int(11) NULL,
  `demo_file` varchar(100) NULL,
  `demo_real` varchar(100) NULL,
  `file_size` int(11) NULL,
  `comment` text NULL,
  `name` varchar(64) NULL,
  `email` varchar(64) NULL,
  `addr` varchar(32) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_levels` (
  `level` int(12) NOT NULL,
  `bans_add` enum('yes','no') NULL DEFAULT 'no',
  `bans_edit` enum('yes','no','own') NULL DEFAULT 'no',
  `bans_delete` enum('yes','no','own') NULL DEFAULT 'no',
  `bans_unban` enum('yes','no','own') NULL DEFAULT 'no',
  `bans_import` enum('yes','no') NULL DEFAULT 'no',
  `bans_export` enum('yes','no') NULL DEFAULT 'no',
  `amxadmins_view` enum('yes','no') NULL DEFAULT 'no',
  `amxadmins_edit` enum('yes','no') NULL DEFAULT 'no',
  `webadmins_view` enum('yes','no') NULL DEFAULT 'no',
  `webadmins_edit` enum('yes','no') NULL DEFAULT 'no',
  `websettings_view` enum('yes','no') NULL DEFAULT 'no',
  `websettings_edit` enum('yes','no') NULL DEFAULT 'no',
  `permissions_edit` enum('yes','no') NULL DEFAULT 'no',
  `prune_db` enum('yes','no') NULL DEFAULT 'no',
  `servers_edit` enum('yes','no') NULL DEFAULT 'no',
  `ip_view` enum('yes','no') NULL DEFAULT 'no',
  PRIMARY KEY (`level`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_logs` (
  `id` int(11) NOT NULL auto_increment,
  `timestamp` int(11) NULL,
  `ip` varchar(32) NULL,
  `username` varchar(32) NULL,
  `action` varchar(64) NULL,
  `remarks` varchar(256) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_modulconfig` (
  `id` int(11) NOT NULL auto_increment,
  `menuname` varchar(32) NULL,
  `name` varchar(32) NULL,
  `index` varchar(32) NULL,
  `activ` int(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_reasons` (
  `id` int(11) NOT NULL auto_increment,
  `reason` varchar(100) NULL,
  `static_bantime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_reasons_set` (
  `id` int(11) NOT NULL auto_increment,
  `setname` varchar(32) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_reasons_to_set` (
  `id` int(11) NOT NULL auto_increment,
  `setid` int(11) NOT NULL,
  `reasonid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_serverinfo` (
  `id` int(11) NOT NULL auto_increment,
  `timestamp` int(11) NULL,
  `hostname` varchar(100) NULL DEFAULT 'Unknown',
  `address` varchar(100) NULL,
  `gametype` varchar(32) NULL,
  `rcon` varchar(32) NULL,
  `amxban_version` varchar(32) NULL,
  `amxban_motd` varchar(250) NULL,
  `motd_delay` int(10) NULL DEFAULT '10',
  `amxban_menu` int(10) NOT NULL DEFAULT '1',
  `reasons` int(10) NULL,
  `timezone_fixx` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_smilies` (
  `id` int(5) NOT NULL auto_increment,
  `code` varchar(32) NULL,
  `url` varchar(32) NULL,
  `name` varchar(32) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_usermenu` (
  `id` int(11) NOT NULL auto_increment,
  `pos` int(11) NULL,
  `activ` tinyint(1) NOT NULL DEFAULT '1',
  `lang_key` varchar(64) NULL,
  `url` varchar(64) NULL,
  `lang_key2` varchar(64) NULL,
  `url2` varchar(64) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_webadmins` (
  `id` int(12) NOT NULL auto_increment,
  `username` varchar(32) NULL,
  `password` varchar(32) NULL,
  `level` int(11) NULL DEFAULT '99',
  `logcode` varchar(64) NULL,
  `email` varchar(64) NULL,
  `last_action` int(11) NULL,
  `try` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`,`email`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_webconfig` (
  `id` int(11) NOT NULL auto_increment,
  `cookie` varchar(32) NULL,
  `bans_per_page` int(11) NULL,
  `design` varchar(32) NULL,
  `banner` varchar(64) NULL,
  `banner_url` varchar(128) NOT NULL,
  `default_lang` varchar(32) NULL,
  `start_page` varchar(64) NULL,
  `show_comment_count` int(1) NULL DEFAULT '1',
  `show_demo_count` int(1) NULL DEFAULT '1',
  `show_kick_count` int(1) NULL DEFAULT '1',
  `demo_all` int(1) NOT NULL DEFAULT '0',
  `comment_all` int(1) NOT NULL DEFAULT '0',
  `use_capture` int(1) NULL DEFAULT '1',
  `max_file_size` int(11) NULL DEFAULT '2',
  `file_type` varchar(64) NULL DEFAULT 'dem,zip,rar,jpg,gif',
  `auto_prune` int(1) NOT NULL DEFAULT '0',
  `max_offences` smallint NOT NULL DEFAULT '10',
  `max_offences_reason` varchar(128) NOT NULL DEFAULT 'max offences reached',
  `use_demo` int(1) NULL DEFAULT '1',
  `use_comment` int(1) NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_flagged` (
  `fid` int(11) NOT NULL auto_increment,
  `player_ip` varchar(32) default NULL,
  `player_id` varchar(35) default NULL,
  `player_nick` varchar(100) default 'Unknown',
  `admin_ip` varchar(32) default NULL,
  `admin_id` varchar(35) default NULL,
  `admin_nick` varchar(100) default 'Unknown',
  `reason` varchar(100) default NULL,
  `created` int(11) default NULL,
  `length` int(11) default NULL,
  `server_ip` varchar(100) default NULL,
  PRIMARY KEY (`fid`),
  KEY `player_id` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

-- Default rows of AMXBans, for its website: settings, menu, the permissions of its first level, smilies and BBCode

INSERT INTO `{prefix}_webconfig` (`id`, `cookie`, `bans_per_page`, `design`, `banner`, `banner_url`, `default_lang`, `start_page`,
  `show_comment_count`, `show_demo_count`, `show_kick_count`, `demo_all`, `comment_all`, `use_capture`, `max_file_size`,
  `file_type`, `auto_prune`, `use_demo`, `use_comment`) VALUES
(1, 'amxbans', 50, 'default', 'amxbans.png', 'http://www.amxbans.net', 'english', 'view.php', 1, 1, 1, 0, 0, 1, 2, 'dem,zip,rar,jpg,gif,png', 0, 1, 1);

INSERT INTO `{prefix}_usermenu` (`id`, `pos`, `activ`, `lang_key`, `url`, `lang_key2`, `url2`) VALUES
(1, 0, 1, '_HOME', 'index.php', '_HOME', 'index.php'),
(2, 1, 1, '_BANLIST', 'ban_list.php', '_BANLIST', 'ban_list.php'),
(3, 2, 1, '_ADMLIST', 'admin_list.php', '_ADMLIST', 'admin_list.php'),
(4, 3, 1, '_SEARCH', 'search.php', '_SEARCH', 'search.php'),
(5, 4, 1, '_SERVER', 'view.php', '_SERVER', 'view.php'),
(6, 5, 1, '_LOGIN', 'login.php', '_LOGOUT', 'logout.php');

INSERT INTO `{prefix}_levels` (`level`, `bans_add`, `bans_edit`, `bans_delete`, `bans_unban`, `bans_import`, `bans_export`,
  `amxadmins_view`, `amxadmins_edit`, `webadmins_view`, `webadmins_edit`, `websettings_view`, `websettings_edit`, `permissions_edit`,
  `prune_db`, `servers_edit`, `ip_view`) VALUES
(1, 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes');

INSERT INTO `{prefix}_modulconfig` (`id`, `menuname`, `name`, `index`, `activ`) VALUES
(1, '_MENUIMPORTEXPORT', 'iexport', '', 1);

INSERT INTO `{prefix}_bbcode` (`id`, `open_tag`, `close_tag`, `url`, `name`) VALUES
(1, '[b]', '[/b]', 'bold.png', 'bold'),
(2, '[i]', '[/i]', 'italic.png', 'italic'),
(3, '[u]', '[/u]', 'underline.png', 'underline'),
(4, '[center]', '[/center]', 'center.png', 'center');

INSERT INTO `{prefix}_smilies` (`id`, `code`, `url`, `name`) VALUES
(1, ':D', 'big_smile.png', 'Big Grin'),
(2, '8)', 'cool.png', 'Cool'),
(3, ':S', 'hmm.png', 'Hmm'),
(4, 'lol', 'lol.png', 'lol'),
(5, ':(', 'mad.png', 'Mad'),
(6, ':|', 'neutral.png', 'Neutral'),
(7, ':roll:', 'roll.png', 'RollEyes'),
(8, ':*(', 'sad.png', 'Sad'),
(9, ':)', 'smile.png', 'Smilie'),
(10, ':P', 'tongue.png', 'Tongue'),
(11, ';)', 'wink.png', 'Wink'),
(12, ':O', 'yikes.png', 'Yikes');
