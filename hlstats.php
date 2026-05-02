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
define('IN_HLSTATS', true);
require('config.php');

if (defined('DEBUG') && DEBUG === true) {
    ini_set('log_errors', 'On');
    error_reporting(-1);
    ini_set('error_log', '_error.txt');
} else {
    error_reporting(0);
}

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

global $script_path;
$scripttime = microtime(true);
$script_path = (isset($_SERVER['SSL']) || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")) ? 'https://' : 'http://';
$script_path .= $_SERVER['HTTP_HOST'];
$script_path .= str_replace('\\','/',dirname($_SERVER['PHP_SELF']));
$script_path = preg_replace('/\/$/','',$script_path);

foreach ($_SERVER as $key => $entry) {
	if ($key !== 'HTTP_COOKIE') {
		$search_pattern  = array('/<script>/', '/<\/script>/', '/[^A-Za-z0-9.\-\/=:;_?#&~]/');
		$replace_pattern = array('', '', '');
		$entry = preg_replace($search_pattern, $replace_pattern, $entry);
  
		if ($key == "PHP_SELF") {
			if ((strrchr($entry, '/') !== '/hlstats.php') &&
				(strrchr($entry, '/') !== '/ingame.php') &&
				(strrchr($entry, '/') !== '/show_graph.php') &&
				(strrchr($entry, '/') !== '/sig.php') &&
				(strrchr($entry, '/') !== '/sig2.php') &&
				(strrchr($entry, '/') !== '/index.php') &&
				(strrchr($entry, '/') !== '/status.php') &&
				(strrchr($entry, '/') !== '/top10.php') &&
				(strrchr($entry, '/') !== '/config.php') &&
				(strrchr($entry, '/') !== '/') &&
				($entry !== '')) {
				header("Location: $script_path/hlstats.php");
				exit;
			}    
		}
		$_SERVER[$key] = $entry;
	}
}

header('Content-Type: text/html; charset=utf-8');

////
//// Initialisation
////

define('PAGE', 'HLSTATS');

///
/// Classes
///

// Load required files
require(INCLUDE_PATH . '/class_db.php');
require(INCLUDE_PATH . '/class_table.php');
require(INCLUDE_PATH . '/functions.php');
require(PAGE_PATH . '/search-class.php');

if (!empty($_GET['logout']) && $_GET['logout'] == '1') {
	clearAuthSession();
	myCookie('steam', '', time() - 3600);
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
	header("Location: " . $scheme . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME']);
	die;
}

$db_classname = 'DB_' . DB_TYPE;
if ( class_exists($db_classname) )
{
	$db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
}
else
{
	error('Database class does not exist.  Please check your config.php file for DB_TYPE');
}

$g_options = getOptions();

if (!isset($g_options['scripturl'])) {
	$g_options['scripturl'] = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : getenv('PHP_SELF');
}


////
//// Main
////

$signin = include './signin.php';

$mode = isset($_GET['mode']) ? $_GET['mode'] : '';

$valid_modes = array(
	'players',
	'clans',
	'weapons',
	'roles',
	'rolesinfo',
	'maps',
	'actions',
	'claninfo',
	'playerinfo',
	'weaponinfo',
	'mapinfo',
	'actioninfo',
	'playerhistory',
	'playersessions',
	'playerawards',
	'search',
	'admin',
	'help',
	'bans',
	'servers',
	'chathistory',
	'ranks',
	'rankinfo',
	'ribbons',
	'ribboninfo',
	'chat',
	'globalawards',
	'awards',
	'dailyawardinfo',
	'countryclans',
	'countryclansinfo',
	'teamspeak',
	'ventrilo',
	'discord'
);

if ( !in_array($mode, $valid_modes) ) {
	$mode = 'contents';
}

$game = valid_request(isset($_GET['game']) ? $_GET['game'] : '', false);

if ($mode !== 'contents' && !$game) {

	$player = valid_request(isset($_GET['player']) ? $_GET['player'] : '', false);
	if ($player) {
		$db->query("SELECT game
					FROM hlstats_Players
					WHERE playerId = '$player'
					LIMIT 1
		");

		list($game) = $db->fetch_row();

	} else {

		$clan = valid_request(isset($_GET['clan']) ? $_GET['clan'] : '', false);
		if ($clan) {
			$db->query("SELECT game
						FROM hlstats_Clans
						WHERE clanId = '$clan'
						LIMIT 1
			");

			list($game) = $db->fetch_row();

		}
	}

}


if ($game && (empty($_SESSION['game']) || $_SESSION['game'] !== $game)) {
	$realgame = null;
	$realname = null;
} else {
	$realgame = $_SESSION['realgame'] ?? null;
	$realname = $_SESSION['realname'] ?? null;
}

if ( $game ) {
	$_SESSION['game'] = $game;
}

if ((!$realgame || !$realname) && $game)
{
	list($realgame, $realname) = getRealGame($game);
	$_SESSION['realgame'] = $realgame;
	$_SESSION['realname'] = $realname;
}



if (!is_ajax() && $mode !== 'admin')
	include (PAGE_PATH . '/header.php');

if ( file_exists(PAGE_PATH . "/$mode.php") ) {
	include(PAGE_PATH . "/$mode.php");
	if (!is_ajax())
		include (PAGE_PATH . '/footer.php');
} else {
	error('Unable to find ' . PAGE_PATH . "/$mode.php");
}

?>
