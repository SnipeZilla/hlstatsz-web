<?php
/*
HLstatsZ - Real-time player and clan rankings and statistics
Originally HLstatsX Community Edition by Nicholas Hastings (2008–20XX)
Based on ELstatsNEO by Malte Bayer, HLstatsX by Tobias Oetzel, and HLstats by Simon Garner

HLstats > HLstatsX > HLstatsX:CE > HLStatsZ
HLstatsZ continues a long lineage of open-source server stats tools for Half-Life and Source games.
This version is released under the GNU General Public License v2 or later.

For current support and updates:
   https://snipezilla.com
   https://github.com/SnipeZilla
   https://forums.alliedmods.net/forumdisplay.php?f=156
*/

if ( !defined('IN_HLSTATS') ) { die('Do not access this file directly'); }


// Warning and error output to _error.txt
define("DEBUG", false);

// DB_ADDR - The address of the database server, in host:port format.
//           (You might also try setting this to e.g. ":/tmp/mysql.sock" to
//           use a Unix domain socket, if your mysqld is on the same box as
//           your web server.)
define("DB_ADDR", "localhost");

// DB_USER - The username to connect to the database as
define("DB_USER", "root");

// DB_PASS - The password for DB_USER
define("DB_PASS", "");

// DB_NAME - The name of the database
define("DB_NAME", "");

// DB_TYPE - The database server type. Only "mysql" is supported currently
define("DB_TYPE", "mysql");

// default 'utf8mb4'
define("DB_CHARSET", 'utf8mb4');

// default 'utf8mb4_general_ci'
define("DB_COLLATE", 'utf8mb4_general_ci');
// DB_PCONNECT - Set to 1 to use persistent database connections. Persistent
//               connections can give better performance, but may overload
//               the database server. Set to 0 to use non-persistent
//               connections.
define("DB_PCONNECT", 0);

// INCLUDE_PATH - Filesystem path to the includes directory, relative to hlstats.php. This must be specified
// as a relative path.
//
// Under Windows, make sure you use forward slash (/) instead
// of back slash (\) and use absolute paths if you are having any issue.
define("INCLUDE_PATH", "./includes");

// PAGE_PATH - Filesystem path to the pages directory, relative to hlstats.php. This must be specified
// as a relative path.
//
// Under Windows, make sure you use forward slash (/) instead
// of back slash (\) and use absolute paths if you are having any issue.
define("PAGE_PATH", "./pages");

// PAGE_PATH - Filesystem path to the hlstatsimg directory, relative to hlstats.php. This must be specified
//		as a relative path.
//
//                Under Windows, make sure you use forward slash (/) instead
//                of back slash (\) and use absolute paths if you are having any issue.
//
// 		Note: the progress directory under hlstatsimg must be writable!!
define("IMAGE_PATH", "./hlstatsimg");

// How often dynamicly generated images are updated (in seconds)
define("IMAGE_UPDATE_INTERVAL", 300);

// Google Analytics
define("GOOGLE_ANALYTICS_ID", "");

// Steam Web API Key for secured sign in
define("STEAM_API", '');

// Steam Admin - Admin link will be removed
// Steam ID 64 : 76561100000000000
// this is more secured with sign handled by Steam 
define("STEAM_ADMIN", '');

// Secret key for secure cookie signing with Steam (required)
// https://passwords-generator.org/
// Password Length: 64
// Lowercase Characters: ✅ 
// Uppercase Characters: ✅ 
// Numbers:              ✅ 
define("SECRET_KEY", 'kB1XWI0GPgW7UA94JAY2QPxPbMk4DLQU7Ekw8UfLsQPlhxrcR3GjRbaYdFZp6xiF');

?>