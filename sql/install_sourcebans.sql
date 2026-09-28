-- SourceBans++ tables for HLstatsZ
--
-- The structure of SourceBans++ 1.8 (database version 705) and its default rows, as its own installer creates them,
-- for the SourceMod plugins and the bans pages of HLstatsZ. Admin > Bans > Bans Settings runs this file when the
-- database named by DB_SBNAME in config.php has no SourceBans tables, and adds the first admin (an owner).
--
-- {prefix} is DB_SBPREFIX: its tables are named {prefix}_bans, {prefix}_admins... ('sb': sb_bans).
-- {charset} and {collate} are DB_CHARSET and DB_COLLATE, the ones of HLstatsZ's tables.
--
-- To create them by hand instead, replace the three placeholders and import it into the SourceBans database.

-- The Web game and the CONSOLE admin are rows 0
SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'NO_AUTO_VALUE_ON_ZERO');

CREATE TABLE IF NOT EXISTS `{prefix}_admins` (
  `aid` int(6) NOT NULL auto_increment,
  `user` varchar(64) NOT NULL,
  `authid` varchar(64) NOT NULL default '',
  `password` varchar(128) NOT NULL,
  `gid` int(6) NOT NULL,
  `email` varchar(128) NOT NULL,
  `validate` varchar(128) NULL default NULL,
  `extraflags` int(10) NOT NULL,
  `immunity` int(10) NOT NULL default '0',
  `srv_group` varchar(128) default NULL,
  `srv_flags` varchar(64) default NULL,
  `srv_password` varchar(128) default NULL,
  `lastvisit` int(11) NULL,
  PRIMARY KEY  (`aid`),
  UNIQUE KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_admins_servers_groups` (
  `admin_id` int(10) NOT NULL,
  `group_id` int(10) NOT NULL,
  `srv_group_id` int(10) NOT NULL,
  `server_id` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_banlog` (
  `sid` int(6) NOT NULL,
  `time` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `bid` int(6) NOT NULL,
  PRIMARY KEY  (`sid`,`time`,`bid`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_bans` (
  `bid` int(6) NOT NULL auto_increment,
  `ip` varchar(32) default NULL,
  `authid` varchar(64) NOT NULL default '',
  `name` varchar(128) NOT NULL default 'unnamed',
  `created` int(11) NOT NULL default '0',
  `ends` int(11) NOT NULL default '0',
  `length` int(10) NOT NULL default '0',
  `reason` text NOT NULL,
  `aid` int(6) NOT NULL default '0',
  `adminIp` varchar(128) NOT NULL default '',
  `sid` int(6) NOT NULL default '0',
  `country` varchar(4) default NULL,
  `RemovedBy` int(8) NULL,
  `RemoveType` VARCHAR(3) NULL,
  `RemovedOn` int(10) NULL,
  `type` TINYINT NOT NULL DEFAULT '0',
  `ureason` text,
  PRIMARY KEY  (`bid`),
  KEY `sid` (`sid`),
  FULLTEXT KEY `reason` (`reason`),
  FULLTEXT KEY `authid_2` (`authid`),
  KEY `type_authid` (`type`,`authid`),
  KEY `type_ip` (`type`,`ip`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_comments` (
  `cid` int(6) NOT NULL auto_increment,
  `bid` int(6) NOT NULL,
  `type` varchar(1) NOT NULL,
  `aid` int(6) NOT NULL,
  `commenttxt` longtext NOT NULL,
  `added` int(11) NOT NULL,
  `editaid` int(6) default NULL,
  `edittime` int(11) default NULL,
  FULLTEXT `commenttxt` (`commenttxt`),
  KEY `cid` (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_demos` (
  `demid` int(6) NOT NULL,
  `demtype` varchar(1) NOT NULL,
  `filename` varchar(128) NOT NULL,
  `origname` varchar(128) NOT NULL,
  PRIMARY KEY  (`demid`,`demtype`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_groups` (
  `gid` int(6) NOT NULL auto_increment,
  `type` smallint(6) NOT NULL default '0',
  `name` varchar(128) NOT NULL default 'unnamed',
  `flags` int(10) NOT NULL,
  PRIMARY KEY  (`gid`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_log` (
  `lid` int(11) NOT NULL auto_increment,
  `type` enum('m','w','e') NOT NULL,
  `title` varchar(512) NOT NULL,
  `message` text NOT NULL,
  `function` text NOT NULL,
  `query` text NOT NULL,
  `aid` int(11) NOT NULL,
  `host` text NOT NULL,
  `created` int(11) NOT NULL,
  PRIMARY KEY  (`lid`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_mods` (
  `mid` int(11) NOT NULL auto_increment,
  `name` varchar(128) NOT NULL,
  `icon` varchar(128) NOT NULL,
  `modfolder` varchar(64) NOT NULL,
  `steam_universe` TINYINT NOT NULL DEFAULT '0',
  `enabled` TINYINT NOT NULL DEFAULT '1',
  PRIMARY KEY  (`mid`),
  UNIQUE (`modfolder`),
  UNIQUE (`name`),
  INDEX (`steam_universe`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_overrides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('command','group') NOT NULL,
  `name` varchar(32) NOT NULL,
  `flags` varchar(30) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`type`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_protests` (
  `pid` int(6) NOT NULL auto_increment,
  `bid` int(6) NOT NULL,
  `datesubmitted` int(11) NOT NULL,
  `reason` text NOT NULL,
  `email` varchar(128) NOT NULL,
  `archiv` tinyint(1) default '0',
  `archivedby` INT(11) NULL,
  `pip` varchar(64) NOT NULL,
  PRIMARY KEY  (`pid`),
  KEY `bid` (`bid`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_servers` (
  `sid` int(6) NOT NULL auto_increment,
  `ip` varchar(64) NOT NULL,
  `port` int(5) NOT NULL,
  `rcon` varchar(64) NOT NULL,
  `modid` int(10) NOT NULL,
  `enabled` TINYINT NOT NULL DEFAULT '1',
  PRIMARY KEY  (`sid`),
  UNIQUE KEY `ip` (`ip`,`port`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_servers_groups` (
  `server_id` int(10) NOT NULL,
  `group_id` int(10) NOT NULL,
  PRIMARY KEY  (`server_id`,`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting` varchar(128) NOT NULL,
  `value` text NOT NULL,
  UNIQUE KEY `setting` (`setting`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_srvgroups` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `flags` varchar(30) NOT NULL,
  `immunity` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `groups_immune` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_srvgroups_overrides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` smallint(5) unsigned NOT NULL,
  `type` enum('command','group') NOT NULL,
  `name` varchar(32) NOT NULL,
  `access` enum('allow','deny') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_id` (`group_id`,`type`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_submissions` (
  `subid` int(6) NOT NULL auto_increment,
  `submitted` int(11) NOT NULL,
  `ModID` int(6) NOT NULL,
  `SteamId` varchar(64) NOT NULL default 'unnamed',
  `name` varchar(128) NOT NULL,
  `email` varchar(128) NOT NULL,
  `reason` text NOT NULL,
  `ip` varchar(64) NOT NULL,
  `subname` varchar(128) default NULL,
  `sip` varchar(64) default NULL,
  `archiv` tinyint(1) default '0',
  `archivedby` INT(11) NULL,
  `server` tinyint(3) default NULL,
  PRIMARY KEY  (`subid`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_comms` (
  `bid` int(6) NOT NULL AUTO_INCREMENT,
  `authid` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL DEFAULT 'unnamed',
  `created` int(11) NOT NULL DEFAULT '0',
  `ends` int(11) NOT NULL DEFAULT '0',
  `length` int(10) NOT NULL DEFAULT '0',
  `reason` text NOT NULL,
  `aid` int(6) NOT NULL DEFAULT '0',
  `adminIp` varchar(128) NOT NULL DEFAULT '',
  `sid` int(6) NOT NULL DEFAULT '0',
  `RemovedBy` int(8) DEFAULT NULL,
  `RemoveType` varchar(3) DEFAULT NULL,
  `RemovedOn` int(11) DEFAULT NULL,
  `type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '1 - Mute, 2 - Gag',
  `ureason` text,
  PRIMARY KEY (`bid`),
  KEY `sid` (`sid`),
  KEY `type` (`type`),
  KEY `RemoveType` (`RemoveType`),
  KEY `authid` (`authid`),
  KEY `created` (`created`),
  KEY `aid` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_login_tokens` (
  `jti` varchar(16) NOT NULL,
  `secret` varchar(64) NOT NULL,
  `lastAccessed` int(11) NOT NULL,
  PRIMARY KEY (`jti`),
  UNIQUE KEY `secret` (`secret`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collate};

-- Default rows of SourceBans++

INSERT INTO `{prefix}_mods` (`mid`, `name`, `icon`, `modfolder`, `steam_universe`) VALUES
(0, 'Web', 'web.png', 'NULL', '0'),
(1, 'Half-Life 2 Deathmatch', 'hl2dm.png', 'hl2mp', '0'),
(2, 'Counter-Strike: Source', 'csource.png', 'cstrike', '0'),
(3, 'Day of Defeat: Source', 'dods.png', 'dod', '0'),
(4, 'Insurgency: Source', 'ins.png', 'insurgency', '0'),
(5, 'Dystopia', 'dys.png', 'dystopia_v1', '0'),
(6, 'Hidden: Source', 'hidden.png', 'hidden', '0'),
(7, 'Half-Life 2 Capture the Flag', 'hl2ctf.png', 'hl2ctf', '0'),
(8, 'Pirates Vikings and Knights II', 'pvkii.png', 'pvkii', '0'),
(9, 'Perfect Dark: Source', 'pdark.png', 'pdark', '0'),
(10, 'The Ship', 'ship.png', 'ship', '0'),
(11, 'Fortress Forever', 'hl2-fortressforever.png', 'FortressForever', '0'),
(12, 'Team Fortress 2', 'tf2.png', 'tf', '0'),
(13, 'Zombie Panic', 'zps.png', 'zps', '0'),
(14, 'Garry''s Mod', 'gmod.png', 'garrysmod', '0'),
(15, 'Left 4 Dead', 'l4d.png', 'left4dead', '1'),
(16, 'Left 4 Dead 2', 'l4d2.png', 'left4dead2', '1'),
(17, 'CSPromod', 'cspromod.png', 'cspromod', '0'),
(18, 'Alien Swarm', 'alienswarm.png', 'alienswarm', '0'),
(19, 'E.Y.E: Divine Cybermancy', 'eye.png', 'eye', '0'),
(20, 'Nuclear Dawn', 'nucleardawn.png', 'nucleardawn', '0'),
(21, 'Counter-Strike: Global Offensive', 'csgo.png', 'csgo', '1'),
(22, 'Synergy', 'synergy.png', 'synergy', '0');

INSERT INTO `{prefix}_settings` (`setting`, `value`) VALUES
('dash.intro.text', '<center><p>Your new SourceBans install</p><p>SourceBans++ successfully installed!</center>'),
('dash.intro.title', 'Your SourceBans++ install'),
('dash.lognopopup', '0'),
('banlist.bansperpage', '30'),
('banlist.hideadminname', '1'),
('banlist.nocountryfetch', '1'),
('banlist.hideplayerips', '1'),
('bans.customreasons', ''),
('config.password.minlength', '7'),
('config.debug', '0'),
('template.logo', 'logos/sb-large.png'),
('template.title', 'SourceBans++'),
('config.enableprotest', '1'),
('config.enablecomms', '1'),
('config.enablesubmit', '1'),
('config.exportpublic', '0'),
('config.enablepubliccomments', '0'),
('config.enablekickit', '1'),
('config.dateformat', 'Y-m-d H:i:s'),
('config.theme', 'default'),
('config.defaultpage', '0'),
('config.enablegroupbanning', '0'),
('config.enablefriendsbanning', '0'),
('config.enableadminrehashing', '1'),
('protest.emailonlyinvolved', '0'),
('config.version', '705'),
('config.enablesteamlogin', '1'),
('auth.maxlife', '1440'),
('auth.maxlife.remember', '10080'),
('auth.maxlife.steam', '10080'),
('smtp.host', ''),
('smtp.pass', ''),
('smtp.port', ''),
('smtp.user', ''),
('smtp.verify_peer', '');

INSERT INTO `{prefix}_admins` (`aid`, `user`, `authid`, `password`, `gid`, `email`, `validate`, `extraflags`, `immunity`) VALUES
(0, 'CONSOLE', 'STEAM_ID_SERVER', '', 0, '', NULL, 0, 0);
