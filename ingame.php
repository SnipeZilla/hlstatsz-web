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
foreach ($_SERVER as $key => $entry) {
	if ($key !== "HTTP_COOKIE") {
		$search_pattern  = array("/<script>/", "/<\/script>/", "/[^A-Za-z0-9.\-\/=:;_?#&~]/");
		$replace_pattern = array("", "", "");
		$entry = preg_replace($search_pattern, $replace_pattern, $entry);
  
		if ($key == "PHP_SELF") {
			if ((strrchr($entry, "/") !== "/hlstats.php") &&
				(strrchr($entry, "/") !== "/ingame.php") &&
				(strrchr($entry, "/") !== "/show_graph.php") &&
				(strrchr($entry, "/") !== "/sig.php") &&
				(strrchr($entry, "/") !== "/sig2.php") &&
				(strrchr($entry, "/") !== "/index.php") &&
				(strrchr($entry, "/") !== "/status.php") &&
				(strrchr($entry, "/") !== "/top10.php") &&
				(strrchr($entry, "/") !== "/config.php") &&
				(strrchr($entry, "/") !== "/") &&
				($entry !== "")) {
				header("Location: http://".$_SERVER['HTTP_HOST']."/hlstats.php");    
				exit;
			}    
		}
		$_SERVER[$key] = $entry;
	}
}

// Several Stuff end
@header("Content-Type: text/html; charset=utf-8");

////
//// Initialisation
////

define('IN_HLSTATS', true);
define('PAGE', 'INGAME');

///
/// Classes
///

// Load required files
require("config.php");
require(INCLUDE_PATH . "/class_db.php");
require(INCLUDE_PATH . "/class_table.php");
require(INCLUDE_PATH . "/functions.php");

if (defined('DEBUG') && DEBUG === true) {
    ini_set('log_errors', 'On');
    error_reporting(-1);
    ini_set('error_log', '_error.txt');
} else {
    error_reporting(0);
}

$db_classname = "DB_" . DB_TYPE;
if ( class_exists($db_classname) )
{
	$db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
}
else
{
	error('Database class does not exist.  Please check your config.php file for DB_TYPE');
}

$g_options = getOptions();

if (!isset($g_options['scripturl']))
	$g_options['scripturl'] = str_replace('\\','/',$_SERVER['PHP_SELF']);


////
//// Main
////

$game = valid_request(isset($_GET['game']) ? $_GET['game'] : '', false);
if ($game && (empty($_SESSION['game']) || $_SESSION['game'] !== $game)) {
	$realgame = null;
	$realname = null;
} else {
	$realgame = $_SESSION['realgame'] ?? null;
	$realname = $_SESSION['realname'] ?? null;
}

if ( !$game ) {
	$game = isset($_SESSION['game'])?$_SESSION['game']:'';
} else {
	$_SESSION['game'] = $game;
}

if ((!$realgame || !$realname) && $game)
{
	list($realgame, $realname) = getRealGame($game);
	$_SESSION['realgame'] = $realgame;
	$_SESSION['realname'] = $realname;
}

$mode = isset($_GET["mode"]) ? $_GET["mode"] : "";

$valid_modes = array(
    "pro",
    "motd",
    "status",
    "load",
    "help",
    "players",
    "clans",
    "statsme",
    "kills",
    "targets",
    "accuracy",
    "actions", 
    "weapons",
    "maps",
    "servers",
    "bans",
    "claninfo",
    "weaponinfo",
    "mapinfo",
    "actioninfo",
    "steamprofile"
);

if (!in_array($mode, $valid_modes))
{
	$mode = "status";
}

include (PAGE_PATH . '/ingame/header.php');

if ( file_exists(PAGE_PATH . "/ingame/$mode.php") )
	@include(PAGE_PATH . "/ingame/$mode.php");
else
	error('Unable to find ' . PAGE_PATH . "/ingame/$mode.php");

include (PAGE_PATH . '/ingame/footer.php');

?>

