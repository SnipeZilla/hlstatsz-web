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
if (!defined('IN_HLSTATS')) { die('Do not access this file directly'); }

// The settings of the HLstatsZ website; the rest is set in the admin panel.
// This file holds passwords and keys: keep it private.


// ── Database ─────────────────────────────────────────────────────────────────────────────────────────

// The MySQL or MariaDB database that the daemon fills (DBHost, DBName... in its hlstats.conf).
// DB_ADDR is 'localhost', a host name or an IP address, with ':port' after it when the port is not 3306.
define('DB_ADDR', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', '');

// The character set and collation of HLstatsZ's tables: leave them as they are.
// Tables from an older HLstats or HLstatsX are converted by Admin > Tools > Reset DB Collations.
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_general_ci');


// ── Bans (optional) ──────────────────────────────────────────────────────────────────────────────────

// The bans pages read SourceBans (Source and CS2 servers), AMXBans (GoldSrc servers) or both. Their databases
// must be on the same server as HLstatsZ's, and DB_USER must be allowed to read and change them.
// An empty name turns one off.

// SourceBans or SourceBans++: its database, and its table prefix, DB_PREFIX in its config.php ('sb': sb_bans...).
define('DB_SBNAME', '');
define('DB_SBPREFIX', 'sb');

// AMXBans: its database, and its table prefix, db_prefix in its include/db.config.inc.php ('amx': amx_bans...).
define('DB_AMXNAME', '');
define('DB_AMXPREFIX', 'amx');


// ── Steam sign-in ────────────────────────────────────────────────────────────────────────────────────

// Steam Web API key, 32 characters (https://steamcommunity.com/dev/apikey). It turns on "Sign in with Steam"
// and the Steam admins below; empty, there is no sign-in.
define('STEAM_API', '');

// The admins who open the admin panel by signing in with Steam: one SteamID64, '76561197960287930',
// or a list of them, ['76561197960287930', '76561197960287931'].
// With these and a Steam API key, the username/password login (Admin Users) is off; '' brings it back.
define('STEAM_ADMIN', '');

// Signs the Steam sign-in cookie and the map download links. Keep it secret: whoever knows it can sign in as
// any Steam account, the admins included. 64 random letters and digits, from a password generator or from
// php -r "echo bin2hex(random_bytes(32));". A new key signs everyone out.
// Secret key for secure cookie signing (required)
// https://passwords-generator.org/
// Password Length: 64
// Lowercase Characters: ✅ 
// Uppercase Characters: ✅ 
// Numbers:              ✅ 
define('SECRET_KEY', 'GdXtjLW6A8eIgoeYMbnRFhf366e111SAQMomkeYqDVGMOwjxY4WOd4y7t5er7F2b');


// ── Other ────────────────────────────────────────────────────────────────────────────────────────────

// How long forum signatures, graphs and charts are kept before they are drawn again, in seconds.
define('IMAGE_UPDATE_INTERVAL', 300);

// Google Analytics measurement ID ('G-XXXXXXXXXX'); empty for none.
define('GOOGLE_ANALYTICS_ID', '');

// true: PHP warnings and errors are written to _error.txt next to this file (web.config and .htaccess keep it
// private), never shown on the pages. false: php.ini decides.
define('DEBUG', false);

// Folders, relative to hlstats.php: leave them as they are. The web server must be able to write to
// hlstatsimg/progress (signatures, graphs) and to cache/.
define('INCLUDE_PATH', './includes');
define('PAGE_PATH', './pages');
define('IMAGE_PATH', './hlstatsimg');
