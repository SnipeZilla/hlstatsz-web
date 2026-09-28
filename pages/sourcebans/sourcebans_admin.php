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

/*
 * SourceBans administration. Admins sign in with Steam only; permissions, flags and the rows written
 * follow SourceBans++ so the SourceMod plugins and the original web panel keep working on the same data.
 */

require_once INCLUDE_PATH . '/class_rcon.php';

// Web permissions as grouped in the editor (bit values come from web.json)
const SB_WEB_SECTIONS = array(
	'Admins'                 => array('ADMIN_LIST_ADMINS', 'ADMIN_ADD_ADMINS', 'ADMIN_EDIT_ADMINS', 'ADMIN_DELETE_ADMINS'),
	'Bans'                   => array('ADMIN_ADD_BAN', 'ADMIN_EDIT_OWN_BANS', 'ADMIN_EDIT_GROUP_BANS', 'ADMIN_EDIT_ALL_BANS', 'ADMIN_UNBAN_OWN_BANS', 'ADMIN_UNBAN_GROUP_BANS', 'ADMIN_UNBAN', 'ADMIN_DELETE_BAN', 'ADMIN_BAN_IMPORT'),
	'Servers'                => array('ADMIN_LIST_SERVERS', 'ADMIN_ADD_SERVER', 'ADMIN_EDIT_SERVERS', 'ADMIN_DELETE_SERVERS'),
	'Groups'                 => array('ADMIN_LIST_GROUPS', 'ADMIN_ADD_GROUP', 'ADMIN_EDIT_GROUPS', 'ADMIN_DELETE_GROUPS'),
	'Games (original panel)' => array('ADMIN_LIST_MODS', 'ADMIN_ADD_MODS', 'ADMIN_EDIT_MODS', 'ADMIN_DELETE_MODS'),
	'Appeals & reports'      => array('ADMIN_BAN_PROTESTS', 'ADMIN_BAN_SUBMISSIONS', 'ADMIN_NOTIFY_PROTEST', 'ADMIN_NOTIFY_SUB'),
	'Settings'               => array('ADMIN_WEB_SETTINGS'),
);

// Permissions needed to open each admin page (any of them; Owner opens everything)
const SB_ADMIN_PAGES = array(
	'admin_settings'  => array('Admins', array('ADMIN_LIST_ADMINS', 'ADMIN_ADD_ADMINS', 'ADMIN_EDIT_ADMINS', 'ADMIN_DELETE_ADMINS')),
	'amx_admins'      => array('AMXBans admins', array('ADMIN_LIST_ADMINS', 'ADMIN_ADD_ADMINS', 'ADMIN_EDIT_ADMINS', 'ADMIN_DELETE_ADMINS')),
	'group_settings'  => array('Groups', array('ADMIN_LIST_GROUPS', 'ADMIN_ADD_GROUP', 'ADMIN_EDIT_GROUPS', 'ADMIN_DELETE_GROUPS')),
	'server_settings' => array('Servers', array('ADMIN_LIST_SERVERS', 'ADMIN_ADD_SERVER', 'ADMIN_EDIT_SERVERS', 'ADMIN_DELETE_SERVERS')),
	'manage_bans'     => array('Add ban', array('ADMIN_ADD_BAN')),
	'manage_comms'    => array('Add comm block', array('ADMIN_ADD_BAN')),
	'web_settings'    => array('Settings', array('ADMIN_WEB_SETTINGS')),
);

// Base games (HLstatsZ realgame) running on GoldSrc: their servers use AMXBans, not SourceBans
const SB_GOLDSRC_GAMES = array('cstrike', 'tfc', 'dod', 'ns', 'valve');

// Ban lengths offered as presets, in minutes (0 = permanent)
const SB_LENGTHS = array(0 => 'Permanent', 30 => '30 min', 60 => '1 hour', 1440 => '1 day', 10080 => '1 week', 20160 => '2 weeks', 43200 => '1 month', 129600 => '3 months');

// SourceBans++ default ban reasons
const SB_REASONS = array(
	'Hacking'   => array('Aimbot', 'Anti Recoil', 'Wallhack', 'Spinhack', 'Speedhack', 'Multi-Hack', 'No Smoke', 'No Flash'),
	'Behaviour' => array('Team Killing', 'Team Flashing', 'Spamming Mic/Chat', 'Inappropriate Spray', 'Inappropriate Language', 'Inappropriate Name', 'Ignoring Admins', 'Team Stacking'),
);

// Settings of the web panel edited here; SourceBans++ keeps them in sb_settings
const SB_SETTINGS = array(
	'banlist.hideadminname'       => array('type' => 'bool', 'group' => 'Public pages', 'label' => 'Hide admin names', 'help' => 'Visitors do not see which admin issued a ban or a comm block; signed-in admins still do.'),
	'banlist.hideplayerips'       => array('type' => 'bool', 'group' => 'Public pages', 'label' => 'Hide player IP addresses', 'help' => 'HLstatsZ never shows them; this also applies to the original SourceBans panel.'),
	'config.enablecomms'          => array('type' => 'bool', 'group' => 'Public pages', 'label' => 'Show comm blocks', 'help' => 'Mutes and gags on the SourceBans pages.'),
	'config.enablekickit'         => array('type' => 'bool', 'group' => 'Game servers', 'label' => 'Kick banned players', 'help' => 'Right after a ban here, look for the player on the servers and kick them (RCON).'),
	'config.enableadminrehashing' => array('type' => 'bool', 'group' => 'Game servers', 'label' => 'Reload admins after changes', 'help' => 'Run sm_rehash on the servers concerned after an admin or group change (RCON).'),
	'bans.customreasons'          => array('type' => 'lines', 'group' => 'Bans', 'label' => 'Custom ban reasons', 'help' => 'One per line, offered next to the standard reasons.'),
);

/**
 * Web permission flags from web.json: name => array('value', 'display'). ALL_WEB is a shortcut, not a flag.
 */
function sbWebFlags()
{
	static $flags = null;

	if ($flags === null) {
		$flags = json_decode(file_get_contents(PAGE_PATH . '/sourcebans/web.json'), true) ?: array();
		unset($flags['ALL_WEB']);
	}
	return $flags;
}

/**
 * SourceMod admin flags from sourcemod.json: letter => display, in alphabetical order with root last.
 */
function sbServerFlags()
{
	static $flags = null;

	if ($flags === null) {
		$flags = array();
		foreach (json_decode(file_get_contents(PAGE_PATH . '/sourcebans/sourcemod.json'), true) ?: array() as $flag) {
			$flags[$flag['value']] = $flag['display'];
		}
		ksort($flags);
		$root = $flags['z'] ?? null;
		unset($flags['z']);
		if ($root !== null) {
			$flags['z'] = $root;
		}
	}
	return $flags;
}

/**
 * Bit mask of web permission names.
 */
function sbWebMask(array $names)
{
	$flags = sbWebFlags();
	$mask  = 0;
	foreach ($names as $name) {
		$mask |= (int) ($flags[$name]['value'] ?? 0);
	}
	return $mask;
}

/**
 * Web permission names set in a mask.
 */
function sbWebNames($mask)
{
	$names = array();
	foreach (sbWebFlags() as $name => $flag) {
		if ($mask & (int) $flag['value']) {
			$names[] = $name;
		}
	}
	return $names;
}

/**
 * SourceBans stores web masks in a signed 32-bit column: bit 31 makes the value negative.
 */
function sbSigned32($mask)
{
	$mask &= 0xFFFFFFFF;
	return $mask > 0x7FFFFFFF ? $mask - 0x100000000 : $mask;
}

/**
 * Formats of a Steam account: 'steam2' (STEAM_0:Y:Z), 'steam3' ([U:1:N]) and 'id64'. Accepts any of them,
 * STEAM_1 or a steamcommunity.com/profiles link; null otherwise (see sbResolveSteam() for vanity URLs).
 */
function sbSteamIds($input)
{
	$input = trim((string) $input);
	if (preg_match('#steamcommunity\.com/profiles/(\d{17})#i', $input, $m)) {
		$input = $m[1];
	}
	if (preg_match('/^STEAM_[0-5]:([01]):(\d{1,10})$/i', $input, $m)) {
		$account = $m[2] * 2 + $m[1];
	} elseif (preg_match('/^\[?U:1:(\d{1,10})\]?$/i', $input, $m)) {
		$account = (int) $m[1];
	} elseif (preg_match('/^7656119\d{10}$/', $input)) {
		$account = (int) $input - 76561197960265728;
	} else {
		return null;
	}
	if ($account <= 0 || $account > 0xFFFFFFFF) {
		return null;
	}
	return array(
		'steam2' => 'STEAM_0:' . ($account % 2) . ':' . intdiv($account, 2),
		'steam3' => '[U:1:' . $account . ']',
		'id64'   => (string) (76561197960265728 + $account),
	);
}

/**
 * Like sbSteamIds(), also resolving steamcommunity.com/id/<name> links and bare custom URL names.
 */
function sbResolveSteam($input)
{
	$ids = sbSteamIds($input);
	if ($ids || !preg_match('#^(?:https?://)?(?:www\.)?(?:steamcommunity\.com/id/)?([A-Za-z0-9_-]{2,32})/?$#i', trim((string) $input), $m)) {
		return $ids;
	}
	$reply = sbSteamApi('ISteamUser/ResolveVanityURL/v0001', array('vanityurl' => $m[1]));
	return isset($reply['response']['steamid']) ? sbSteamIds($reply['response']['steamid']) : null;
}

/**
 * Steam Web API call with the site's key; null when there is no key or no answer.
 */
function sbSteamApi($method, array $params)
{
	if (!defined('STEAM_API') || !preg_match('/^[A-F0-9]{32}$/i', (string) STEAM_API)) {
		return null;
	}
	$context = stream_context_create(array('http' => array('timeout' => 5, 'ignore_errors' => true)));
	$body    = @file_get_contents('https://api.steampowered.com/' . $method . '/?' . http_build_query(array('key' => STEAM_API) + $params), false, $context);
	return $body ? json_decode($body, true) : null;
}

/**
 * Steam names and avatars by SteamID64 (cached for the session): id64 => array('name', 'avatar').
 */
function sbSteamProfiles(array $ids64)
{
	$cache  = $_SESSION['sb_profiles'] ?? array();
	$wanted = array_values(array_diff(array_unique($ids64), array_keys($cache)));

	foreach (array_chunk($wanted, 100) as $chunk) {
		$reply = sbSteamApi('ISteamUser/GetPlayerSummaries/v0002', array('steamids' => implode(',', $chunk)));
		if ($reply === null) {
			break;
		}
		foreach ($chunk as $id64) {
			$cache[$id64] = null;
		}
		foreach ($reply['response']['players'] ?? array() as $player) {
			$cache[$player['steamid']] = array('name' => $player['personaname'], 'avatar' => $player['avatarmedium'] ?? $player['avatar']);
		}
	}
	if (session_status() === PHP_SESSION_ACTIVE) {
		$_SESSION['sb_profiles'] = array_slice($cache, -500, null, true);
	}
	return array_intersect_key($cache, array_flip($ids64));
}

/**
 * The SourceBans admin signed in with Steam, or null (always without SourceBans). Web permissions combine the
 * admin's own flags with the web group's and server flags the admin's with the server group's, as in SourceBans++.
 */
function sbAdmin()
{
	global $db;
	static $admin = false;

	if ($admin !== false) {
		return $admin;
	}
	$admin = null;
	$ids   = sbSteamIds($_SESSION['ID64'] ?? '');
	if (!$ids || !sbOn()) {
		return null;
	}
	$steam1 = str_replace('STEAM_0:', 'STEAM_1:', $ids['steam2']);
	$db->query("
		SELECT
			a.aid,
			a.user,
			a.authid,
			a.gid,
			a.extraflags,
			a.immunity,
			a.srv_group,
			a.srv_flags,
			wg.flags AS wgflags,
			sg.flags AS sgflags,
			sg.immunity AS sgimmunity
		FROM
			" . DB_SBPREFIX . "_admins AS a
			LEFT JOIN " . DB_SBPREFIX . "_groups AS wg ON wg.gid = a.gid
			LEFT JOIN " . DB_SBPREFIX . "_srvgroups AS sg ON sg.name = a.srv_group
		WHERE
			a.aid > 0
			AND a.authid IN ('{$ids['steam2']}', '$steam1', '{$ids['steam3']}', '{$ids['id64']}')
		ORDER BY
			a.aid
		LIMIT 1
	");
	$row = $db->fetch_array();
	if (!$row) {
		return null;
	}

	$row['aid']      = (int) $row['aid'];
	$row['gid']      = (int) $row['gid'];
	$row['web']      = ((int) $row['extraflags'] | (int) $row['wgflags']) & 0xFFFFFFFF;
	$row['srv']      = (string) $row['srv_flags'] . (string) $row['sgflags'];
	$row['immunity'] = max((int) $row['immunity'], (int) $row['sgimmunity']);

	// SourceBans++ records panel visits; its plugins can require a recent one
	if (empty($_SESSION['sb_visit']) || $_SESSION['sb_visit'] < time() - 3600) {
		$db->query("UPDATE " . DB_SBPREFIX . "_admins SET lastvisit = " . time() . " WHERE aid = " . $row['aid']);
		$_SESSION['sb_visit'] = time();
	}
	return $admin = $row;
}

/**
 * Whether the signed-in admin holds any of these web permissions (Owner holds them all).
 */
function sbCan(...$names)
{
	$admin = sbAdmin();
	return $admin !== null && ($admin['web'] & sbWebMask(array_merge(array('ADMIN_OWNER'), $names))) !== 0;
}

/**
 * Whether the signed-in admin is an owner.
 */
function sbIsOwner()
{
	return sbCan();
}

/**
 * Sends a JSON answer and stops.
 */
function sbReply(array $data)
{
	// Drop the page output buffered so far
	while (ob_get_level() > 0) {
		if (!@ob_end_clean()) {
			break;
		}
	}
	// Opened without AJAX, the page header has already gone out
	if (!headers_sent()) {
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
	}
	echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
}

/**
 * JSON failure: a message and, for forms, one error per field name.
 */
function sbFail($message, array $errors = array())
{
	sbReply(array('ok' => false, 'message' => $message, 'errors' => (object) $errors));
}

/**
 * Admin pages the signed-in admin may open: task => title. The AMXBans one needs AMXBans set up.
 */
function sbAdminPages()
{
	$pages = array();
	foreach (SB_ADMIN_PAGES as $task => $page) {
		if (sbCan(...$page[1]) && ($task !== 'amx_admins' || sbAmx())) {
			$pages[$task] = $page[0];
		}
	}
	return $pages;
}

/**
 * Anti-forgery token of the admin session.
 */
function sbToken()
{
	if (empty($_SESSION['sb_token'])) {
		$_SESSION['sb_token'] = bin2hex(random_bytes(16));
	}
	return $_SESSION['sb_token'];
}

/**
 * Entry in the SourceBans log ('m' message, 'w' warning, 'e' error), shown by both panels.
 */
function sbLog($type, $title, $message)
{
	global $db;

	$admin = sbAdmin();
	$db->query("
		INSERT INTO " . DB_SBPREFIX . "_log (type, title, message, function, query, aid, host, created)
		VALUES ('" . $db->escape($type) . "', '" . $db->escape($title) . "', '" . $db->escape($message) . "', 'HLstatsZ', '', "
		. (int) ($admin['aid'] ?? 0) . ", '" . $db->escape($_SERVER['REMOTE_ADDR'] ?? '') . "', " . time() . ")
	");
}

/**
 * Setting of the SourceBans web panel, as a string ('' when missing).
 */
function sbSetting($name)
{
	return (string) (sbSettings()[$name] ?? '');
}

/**
 * Whether a web permission mask includes Owner (masks can be negative, see sbSigned32()).
 */
function sbIsOwnerMask($mask)
{
	return ((int) $mask & sbWebMask(array('ADMIN_OWNER'))) !== 0;
}

/**
 * SQL list of the ways an account's Steam ID can be stored, for "authid IN (...)".
 */
function sbSteamMatch(array $ids)
{
	return "'{$ids['steam2']}', '" . str_replace('STEAM_0:', 'STEAM_1:', $ids['steam2']) . "', '{$ids['steam3']}', '{$ids['id64']}'";
}

/**
 * Text typed in the search box of an admin list (?q=).
 */
function sbSearch()
{
	$text = $_GET['q'] ?? '';
	return is_string($text) ? trim(mb_substr($text, 0, 100)) : '';
}

/**
 * Quoted LIKE pattern finding $text anywhere, its % and _ taken literally.
 */
function sbLike($text)
{
	global $db;

	return "'%" . $db->escape(addcslashes($text, '%_\\')) . "%'";
}

/**
 * An admin with the web permissions of their group merged in, or null for the console accounts (aid 0, STEAM_ID_SERVER).
 */
function sbAdminRow($aid)
{
	global $db;

	$db->query("
		SELECT
			a.aid, a.user, a.authid, a.gid, a.extraflags, a.immunity, a.srv_group, a.srv_flags, a.lastvisit,
			wg.flags AS wgflags
		FROM
			" . DB_SBPREFIX . "_admins AS a
			LEFT JOIN " . DB_SBPREFIX . "_groups AS wg ON wg.gid = a.gid
		WHERE
			a.aid = " . (int) $aid . " AND a.aid > 0 AND a.authid <> 'STEAM_ID_SERVER'
	");
	$row = $db->fetch_array();
	if (!$row) {
		return null;
	}
	$row['aid'] = (int) $row['aid'];
	$row['web'] = ((int) $row['extraflags'] | (int) $row['wgflags']) & 0xFFFFFFFF;
	return $row;
}

/**
 * Admin name made unique for the admins table (SourceBans++ also refuses apostrophes); '' if unusable.
 */
function sbUniqueAdminName($name, $excludeAid = 0)
{
	global $db;

	$base = mb_substr(trim(str_replace("'", '', (string) $name)), 0, 58);
	for ($i = 1; $base !== '' && $i < 50; $i++) {
		$candidate = $i === 1 ? $base : "$base $i";
		$db->query("SELECT 1 FROM " . DB_SBPREFIX . "_admins WHERE user = '" . $db->escape($candidate) . "' AND aid <> " . (int) $excludeAid);
		if ($db->num_rows() === 0) {
			return $candidate;
		}
	}
	return '';
}

/**
 * Server access values ("g<gid>" server group, "s<sid>" server) that exist: array(group ids, server ids).
 */
function sbParseAccess(array $values)
{
	global $db;

	$wanted = array('g' => array(), 's' => array());
	foreach ($values as $value) {
		if (preg_match('/^([gs])(\d+)$/', (string) $value, $m)) {
			$wanted[$m[1]][] = (int) $m[2];
		}
	}
	$found = array('g' => array(), 's' => array());
	if ($wanted['g']) {
		$db->query("SELECT gid FROM " . DB_SBPREFIX . "_groups WHERE type = 3 AND gid IN (" . implode(',', array_unique($wanted['g'])) . ")");
		$found['g'] = array_map('intval', array_column($db->fetch_row_set() ?: array(), 'gid'));
	}
	if ($wanted['s']) {
		$db->query("SELECT sid FROM " . DB_SBPREFIX . "_servers WHERE sid IN (" . implode(',', array_unique($wanted['s'])) . ")");
		$found['s'] = array_map('intval', array_column($db->fetch_row_set() ?: array(), 'sid'));
	}
	return array($found['g'], $found['s']);
}

/**
 * Follow-up tasks reloading the admins of these servers, when the setting asks for it.
 */
function sbRehashTasks(array $sids)
{
	if (sbSetting('config.enableadminrehashing') !== '1') {
		return array();
	}
	$tasks = array();
	foreach (array_values(array_intersect(sbRconServerIds(), array_map('intval', $sids))) as $sid) {
		$tasks[] = array('action' => 'rehash', 'sid' => $sid, 'label' => sbServerName($sid) ?: "Server #$sid");
	}
	return $tasks;
}

/**
 * Whether the signed-in admin may edit, unban or delete a ban or comm block ($row needs aid and admin_gid):
 * all of them, their own, or those of admins in their web group, as SourceBans++ decides.
 */
function sbCanOnBan(array $row, $verb)
{
	$admin = sbAdmin();
	if ($admin === null) {
		return false;
	}
	if ($verb === 'delete') {
		return sbCan('ADMIN_DELETE_BAN');
	}
	$perm = $verb === 'edit'
		? array('ADMIN_EDIT_ALL_BANS', 'ADMIN_EDIT_OWN_BANS', 'ADMIN_EDIT_GROUP_BANS')
		: array('ADMIN_UNBAN', 'ADMIN_UNBAN_OWN_BANS', 'ADMIN_UNBAN_GROUP_BANS');
	return sbCan($perm[0])
		|| (sbCan($perm[1]) && (int) $row['aid'] === $admin['aid'])
		|| (sbCan($perm[2]) && $admin['gid'] > 0 && (int) ($row['admin_gid'] ?? 0) === $admin['gid']);
}

/**
 * Custom ban reasons of the settings (stored serialized by SourceBans++).
 */
function sbCustomReasons()
{
	$reasons = @unserialize(sbSetting('bans.customreasons'), array('allowed_classes' => false));
	return is_array($reasons) ? array_values(array_filter($reasons, 'is_string')) : array();
}

/**
 * Enabled servers where an admin is an admin in game: listed directly or through a server group.
 */
function sbAdminServerIds($aid)
{
	global $db;

	$db->query("
		SELECT DISTINCT
			s.sid
		FROM
			" . DB_SBPREFIX . "_servers AS s
			JOIN " . DB_SBPREFIX . "_admins_servers_groups AS asg ON asg.admin_id = " . (int) $aid . "
			LEFT JOIN " . DB_SBPREFIX . "_servers_groups AS sg ON sg.group_id = asg.srv_group_id
		WHERE
			s.enabled = 1
			AND (s.sid = asg.server_id OR s.sid = sg.server_id)
	");
	return array_map('intval', array_column($db->fetch_row_set() ?: array(), 'sid'));
}

/**
 * HLstatsZ games by code, visible ones first: name, hidden, realgame and goldsrc (GoldSrc games are left to AMXBans).
 */
function sbHlzGames()
{
	global $db;
	static $games = null;

	if ($games === null) {
		$games  = array();
		$result = $db->query("
			SELECT
				g.code,
				g.name,
				g.hidden,
				COALESCE(NULLIF(g.realgame, ''), g.code) AS realgame,
				COALESCE(gd.value, '') AS engine
			FROM
				`" . DB_NAME . "`.hlstats_Games AS g
				LEFT JOIN `" . DB_NAME . "`.hlstats_Games_Defaults AS gd ON gd.code = COALESCE(NULLIF(g.realgame, ''), g.code) AND gd.parameter = 'GameEngine'
			ORDER BY
				g.hidden,
				g.name
		");
		while ($row = $db->fetch_array($result)) {
			$row['goldsrc'] = $row['engine'] === '1' || in_array($row['realgame'], SB_GOLDSRC_GAMES, true);
			$games[$row['code']] = $row;
		}
	}
	return $games;
}

/**
 * HLstatsZ servers by serverId: name, address, port, publicaddress, game, gamename and goldsrc.
 * GoldSrc as the RCON console tells it: the server's GameEngine setting, else its game's.
 */
function sbHlzServers()
{
	global $db;

	$result = $db->query("
		SELECT
			s.serverId,
			s.name,
			s.address,
			s.port,
			s.publicaddress,
			s.game,
			sc.value AS engine
		FROM
			`" . DB_NAME . "`.hlstats_Servers AS s
			LEFT JOIN `" . DB_NAME . "`.hlstats_Servers_Config AS sc ON sc.serverId = s.serverId AND sc.parameter = 'GameEngine'
		ORDER BY
			s.name,
			s.serverId
	");
	$games   = sbHlzGames();
	$servers = array();
	while ($row = $db->fetch_array($result)) {
		$game = $games[$row['game']] ?? null;
		$row['name']     = html_entity_decode($row['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$row['gamename'] = $game ? $game['name'] : $row['game'];
		$row['goldsrc']  = ($row['engine'] ?? $game['engine'] ?? '') === '1' || in_array($game['realgame'] ?? $row['game'], SB_GOLDSRC_GAMES, true);
		$servers[(int) $row['serverId']] = $row;
	}
	return $servers;
}

/**
 * SourceBans mod folders an HLstatsZ game can match: its own code first, then its base game.
 */
function sbGameFolders($code)
{
	$game    = sbHlzGames()[$code] ?? null;
	$folders = array_flip(SB_MOD_GAMES);   // HLstatsZ code => SourceBans folder
	return array_values(array_unique(array($folders[$code] ?? $code, $folders[$game['realgame'] ?? $code] ?? ($game['realgame'] ?? $code))));
}

/**
 * SourceBans mod (mid) of an HLstatsZ game, added when SourceBans has none yet; 0 for unknown and GoldSrc games.
 * The plugins do not use mods; the web panels take the icon and the Steam ID format from them.
 */
function sbModForGame($code)
{
	global $db;

	$game = sbHlzGames()[$code] ?? null;
	if (!$game || $game['goldsrc']) {
		return 0;
	}
	$folders = sbGameFolders($code);
	foreach ($folders as $folder) {
		$db->query("SELECT mid FROM " . DB_SBPREFIX . "_mods WHERE mid > 0 AND modfolder = '" . $db->escape($folder) . "' LIMIT 1");
		if ($row = $db->fetch_row()) {
			return (int) $row[0];
		}
	}

	// A game SourceBans knows under its own folder keeps it, others go under their base game; names are unique too
	$own    = isset(array_flip(SB_MOD_GAMES)[$code]);
	$folder = $own ? $folders[0] : end($folders);
	$name   = mb_substr(($own ? $game : (sbHlzGames()[$game['realgame']] ?? $game))['name'], 0, 110);
	$db->query("SELECT 1 FROM " . DB_SBPREFIX . "_mods WHERE name = '" . $db->escape($name) . "'");
	if ($db->num_rows() > 0) {
		$name .= " ($folder)";
	}
	$db->query("INSERT INTO " . DB_SBPREFIX . "_mods (name, icon, modfolder, steam_universe, enabled) VALUES ('" . $db->escape($name) . "', '', '"
		. $db->escape($folder) . "', " . (in_array($folder, array('csgo', 'left4dead', 'left4dead2'), true) ? 1 : 0) . ', 1)');
	$mid = (int) $db->insert_id();   // before the log line, which has its own
	sbLog('m', 'Mod Added', "Mod ($name) has been added for the HLstatsZ game $code.");
	return $mid;
}

/**
 * HLstatsZ game of a SourceBans mod folder: a game whose own code matches first, then one whose base game does.
 */
function sbGameForFolder($folder)
{
	foreach (array(0, 1) as $level) {
		foreach (sbHlzGames() as $code => $game) {
			$folders = sbGameFolders($code);
			if (!$game['goldsrc'] && isset($folders[$level]) && strcasecmp($folders[$level], (string) $folder) === 0) {
				return $code;
			}
		}
	}
	return '';
}

/**
 * Enabled servers that have an RCON password.
 */
function sbRconServerIds()
{
	global $db;

	$db->query("SELECT sid FROM " . DB_SBPREFIX . "_servers WHERE enabled = 1 AND rcon <> '' ORDER BY sid");
	return array_map('intval', array_column($db->fetch_row_set() ?: array(), 'sid'));
}

/**
 * Sends an RCON command to a SourceBans server: array(ok, output or error). Reads such as "status" skip the log.
 */
function sbRcon($sid, $command, $log = true)
{
	global $db;

	$db->query("SELECT ip, port, rcon FROM " . DB_SBPREFIX . "_servers WHERE sid = " . (int) $sid);
	$server = $db->fetch_array();
	if (!$server) {
		return array(false, 'Unknown server.');
	}
	if ($server['rcon'] === '') {
		return array(false, 'No RCON password is set for this server.');
	}

	$rcon   = new Rcon($server['ip'], $server['port'], $server['rcon']);
	$output = $rcon->execute($command);
	if ($output === false) {
		sbLog('e', 'RCON Error', sprintf('RCON command (%s) to %s:%d failed: %s', $command, $server['ip'], $server['port'], $rcon->error));
		return array(false, $rcon->error);
	}
	if ($log) {
		sbLog('m', 'RCON Sent', sprintf('RCON command (%s) was sent to server (%s:%d)', $command, $server['ip'], $server['port']));
	}
	return array(true, $output);
}

/**
 * Players in an RCON "status" reply: array of array('id', 'name', 'steam2', 'ip', 'time', 'ping'), bots left out.
 * Source games list "# userid "name" uniqueid connected ping loss state adr"; CS2 lists
 * "id time ping loss state rate adr 'name'" without Steam IDs, so steam2 stays '' there.
 */
function sbStatusPlayers($status)
{
	$players = array();
	foreach (preg_split('/\R/', (string) $status) as $line) {
		if (preg_match('/^#\s*(\d+)(?>\s|\d)*"(.*)"\s*(STEAM_[0-5]:[01]:\d+|\[U:1:\d+\])\s+(?:(\d+(?::\d+){1,2})\s+(\d+)\s+)?(?>\s|:|\d)*[a-zA-Z]*\s*\d*\s([0-9.]+)/', $line, $m)) {
			$ids = sbSteamIds($m[3]);
			$players[] = array('id' => (int) $m[1], 'name' => $m[2], 'steam2' => $ids ? $ids['steam2'] : '', 'ip' => $m[6], 'time' => $m[4], 'ping' => $m[5]);
		} elseif (preg_match("/^\s*(\d+)\s+(\d+(?::\d+){1,2})\s+(\d+)\s+\d+\s+[a-z]+\s+\d+\s*([0-9.]+):\d+\s+'(.*)'\s*$/i", $line, $m)) {
			$players[] = array('id' => (int) $m[1], 'name' => $m[5], 'steam2' => '', 'ip' => $m[4], 'time' => $m[2], 'ping' => $m[3]);
		}
	}
	return $players;
}

/**
 * Players on a SourceBans server, from RCON "status": array(true, players, info) or array(false, error).
 * CS2 does not list Steam IDs; when HLstatsZ tracks the server they come from its live players, by address.
 * info is sbStatusInfo() of the reply: host name, map, players and max players.
 */
function sbServerPlayers($sid)
{
	global $db;

	list($ok, $status) = sbRcon($sid, 'status', false);
	if (!$ok) {
		return array(false, $status);
	}
	$players = sbStatusPlayers($status);

	$hlz = sbServers()[(int) $sid]['hlz'] ?? null;
	if ($hlz && in_array('', array_column($players, 'steam2'), true)) {
		$db->query("SELECT steam_id, name, cli_address FROM `" . DB_NAME . "`.hlstats_Livestats WHERE server_id = " . (int) $hlz['serverId']);
		$live = $db->fetch_row_set() ?: array();
		foreach ($players as &$player) {
			if ($player['steam2'] !== '') {
				continue;
			}
			$same = array_values(array_filter($live, function ($row) use ($player) {
				return preg_replace('/:\d+$/', '', (string) $row['cli_address']) === $player['ip'];
			}));
			if (count($same) > 1) {   // players behind one address: tell them apart by name
				$same = array_values(array_filter($same, function ($row) use ($player) {
					return $row['name'] === $player['name'];
				}));
			}
			if (count($same) === 1 && ($ids = sbSteamIds($same[0]['steam_id']))) {
				$player['steam2'] = $ids['steam2'];
			}
		}
		unset($player);
	}

	return array(true, $players, sbStatusInfo($status));
}

/**
 * Sorted list of the SourceMod flags in a string, without duplicates.
 */
function sbCleanFlags($flags)
{
	$valid = array_keys(sbServerFlags());
	$flags = array_unique(array_intersect(str_split(strtolower((string) $flags)), $valid));
	sort($flags);
	return implode('', $flags);
}

/**
 * Round placeholder with the first letter of the name, for admins without a Steam avatar.
 */
function sbAvatarInitial($name, $class = '')
{
	return '<span class="sba-avatar' . $class . '" aria-hidden="true">' . htmlspecialchars(mb_substr(trim((string) $name), 0, 1)) . '</span>';
}

/*
 * Form pieces shared by the admin pages
 */

/**
 * Web permission editor: checkboxes named web[] grouped by section; Owner is reserved to owners.
 */
function sbWebPermsField(array $checked, $name = 'web[]')
{
	$flags = sbWebFlags();
	$owner = sbIsOwner();
	$html  = '<div class="sba-perms">';
	$html .= '<label class="sba-check sba-owner' . ($owner ? '' : ' is-locked') . '"><input type="checkbox" name="' . $name . '" value="ADMIN_OWNER"'
		. (in_array('ADMIN_OWNER', $checked) ? ' checked' : '') . ($owner ? '' : ' disabled') . '>'
		. '<span><b>Owner</b> &middot; every permission, including managing other owners</span></label>';
	$html .= '<div class="sba-perm-grid">';
	foreach (SB_WEB_SECTIONS as $section => $names) {
		$html .= '<fieldset class="sba-perm-group"><legend>' . $section . ' <button type="button" class="sba-link" data-sba-all>All</button></legend>';
		foreach ($names as $flag) {
			if (!isset($flags[$flag])) {
				continue;
			}
			$html .= '<label class="sba-check"><input type="checkbox" name="' . $name . '" value="' . $flag . '"' . (in_array($flag, $checked) ? ' checked' : '') . '>'
				. '<span>' . htmlspecialchars($flags[$flag]['display']) . '</span></label>';
		}
		$html .= '</fieldset>';
	}
	return $html . '</div></div>';
}

/**
 * SourceMod flag picker: one chip per flag letter, named srv[].
 */
function sbServerFlagsField($checked, $name = 'srv[]')
{
	$html = '<div class="sba-flags">';
	foreach (sbServerFlags() as $letter => $display) {
		$html .= '<label class="sba-flag' . ($letter === 'z' ? ' is-root' : '') . '"><input type="checkbox" name="' . $name . '" value="' . $letter . '"'
			. (strpos((string) $checked, $letter) !== false ? ' checked' : '') . '>'
			. '<span class="sba-flag-letter">' . $letter . '</span><span>' . htmlspecialchars($display) . '</span></label>';
	}
	return $html . '</div>';
}

/**
 * Checkbox chips for server access: server groups (value "g<gid>") and single servers (value "s<sid>").
 */
function sbServerAccessField(array $checked)
{
	global $db;

	$html = '<div class="sba-chips" data-sba-chips>';
	$db->query("SELECT gid, name FROM " . DB_SBPREFIX . "_groups WHERE type = 3 ORDER BY name");
	foreach ($db->fetch_row_set() ?: array() as $group) {
		$value = 'g' . (int) $group['gid'];
		$html .= '<label class="sba-chip is-group"><input type="checkbox" name="access[]" value="' . $value . '"' . (in_array($value, $checked) ? ' checked' : '') . '>'
			. '<span>' . htmlspecialchars($group['name']) . ' <small>group</small></span></label>';
	}
	// Disabled servers too, or saving would drop the access to them
	$db->query("SELECT sid, ip, port, enabled FROM " . DB_SBPREFIX . "_servers ORDER BY sid");
	foreach ($db->fetch_row_set() ?: array() as $server) {
		$value = 's' . (int) $server['sid'];
		$label = sbServerName($server['sid']) ?: $server['ip'] . ':' . $server['port'];
		$html .= '<label class="sba-chip' . ($server['enabled'] ? '' : ' is-off') . '"><input type="checkbox" name="access[]" value="' . $value . '"' . (in_array($value, $checked) ? ' checked' : '') . '>'
			. '<span>' . htmlspecialchars($label) . ($server['enabled'] ? '' : ' <small>disabled</small>') . '</span></label>';
	}
	return $html . '</div>';
}

/**
 * <option> list of web groups (type 1), with "none".
 */
function sbWebGroupOptions($selected)
{
	global $db;

	$html = '<option value="-1">No web group</option>';
	$db->query("SELECT gid, name FROM " . DB_SBPREFIX . "_groups WHERE type = 1 ORDER BY name");
	foreach ($db->fetch_row_set() ?: array() as $group) {
		$html .= '<option value="' . (int) $group['gid'] . '"' . ((int) $group['gid'] === (int) $selected ? ' selected' : '') . '>' . htmlspecialchars($group['name']) . '</option>';
	}
	return $html;
}

/**
 * <option> list of server admin groups, by name as SourceBans stores them, with "none".
 */
function sbServerGroupOptions($selected)
{
	global $db;

	$html = '<option value="">No server group</option>';
	$db->query("SELECT name, flags, immunity FROM " . DB_SBPREFIX . "_srvgroups ORDER BY name");
	foreach ($db->fetch_row_set() ?: array() as $group) {
		$html .= '<option value="' . htmlspecialchars($group['name']) . '"' . ($group['name'] === (string) $selected ? ' selected' : '') . '>'
			. htmlspecialchars($group['name']) . ' (' . htmlspecialchars($group['flags']) . ($group['immunity'] ? ', immunity ' . (int) $group['immunity'] : '') . ')</option>';
	}
	return $html;
}

/**
 * Steam ID input with the live profile preview.
 */
function sbSteamField($value = '', $autofill = 'name')
{
	return '<label class="sba-field"><span class="sba-label">Steam ID</span>'
		. '<input type="text" name="steam" value="' . htmlspecialchars($value) . '" required autocomplete="off" spellcheck="false" data-sba-steam="' . $autofill . '"'
		. ' placeholder="STEAM_0:1:123, [U:1:246], 7656… or a profile link">'
		. '<span class="sba-error" data-error-for="steam"></span></label>'
		. '<div class="sba-steam" hidden></div>';
}

/**
 * Length picker: preset chips plus an "other" amount and unit, stored in minutes in the hidden "length".
 */
function sbLengthField()
{
	$html = '<fieldset class="sba-field"><legend class="sba-label">Length</legend><div class="sba-presets" data-sba-presets="length">';
	foreach (SB_LENGTHS as $minutes => $label) {
		$html .= '<button type="button" class="sba-preset" data-value="' . $minutes . '">' . $label . '</button>';
	}
	return $html . '<button type="button" class="sba-preset" data-value="other">Other&hellip;</button></div>'
		. '<div class="sba-custom" hidden><input type="number" min="1" value="1" aria-label="Length" data-sba-amount>'
		. '<select aria-label="Unit" data-sba-unit><option value="1">minutes</option><option value="60">hours</option><option value="1440" selected>days</option>'
		. '<option value="10080">weeks</option><option value="43200">months</option></select></div>'
		. '<input type="hidden" name="length" value=""><span class="sba-error" data-error-for="length"></span></fieldset>';
}

/**
 * Reason input with the standard and custom reasons as chips ($max characters: AMXBans keeps 100).
 */
function sbReasonField(array $groups, $max = 500)
{
	$html = '<fieldset class="sba-field"><legend class="sba-label">Reason</legend><div class="sba-presets is-reasons" data-sba-presets="reason">';
	foreach ($groups as $group => $reasons) {
		if ($reasons) {
			$html .= '<span class="sba-preset-group">' . htmlspecialchars($group) . '</span>';
			foreach ($reasons as $reason) {
				$html .= '<button type="button" class="sba-preset" data-value="' . htmlspecialchars($reason) . '">' . htmlspecialchars($reason) . '</button>';
			}
		}
	}
	return $html . '</div><input type="text" name="reason" maxlength="' . (int) $max . '" required placeholder="Pick a reason above or type your own">'
		. '<span class="sba-error" data-error-for="reason"></span></fieldset>';
}

/**
 * Fields of a new ban: Steam ID or IP address, name, length and reason (Add ban page, players of a server).
 */
function sbBanFields()
{
?>
	<fieldset class="sba-field">
		<legend class="sba-label">Ban by</legend>
		<div class="sba-segment">
			<label><input type="radio" name="target" value="steam" checked><span>Steam ID</span></label>
			<label><input type="radio" name="target" value="ip"><span>IP address</span></label>
		</div>
	</fieldset>
	<div data-sba-when="target=steam"><?= sbSteamField() ?></div>
	<div data-sba-when="target=ip" hidden>
		<label class="sba-field"><span class="sba-label">IP address</span><input type="text" name="ip" spellcheck="false" placeholder="203.0.113.7"><span class="sba-error" data-error-for="ip"></span></label>
	</div>
	<label class="sba-field"><span class="sba-label">Player name</span><input type="text" name="name" maxlength="128" placeholder="Taken from the Steam profile"></label>
	<?= sbLengthField() ?>
	<?= sbReasonField(array('Hacking' => SB_REASONS['Hacking'], 'Behaviour' => SB_REASONS['Behaviour'], 'Custom' => sbCustomReasons())) ?>
<?php
}

/**
 * Fields of a new comm block: Steam ID, name, mute and/or gag, length and reason.
 */
function sbCommFields()
{
?>
	<?= sbSteamField() ?>
	<label class="sba-field"><span class="sba-label">Player name</span><input type="text" name="name" maxlength="128" placeholder="Taken from the Steam profile"></label>
	<fieldset class="sba-field">
		<legend class="sba-label">Block</legend>
		<div class="sba-segment">
			<label><input type="radio" name="type" value="mute" checked><span><?= SB_ICON_MUTE ?> Voice (mute)</span></label>
			<label><input type="radio" name="type" value="gag"><span><?= SB_ICON_GAG ?> Chat (gag)</span></label>
			<label><input type="radio" name="type" value="both"><span>Both</span></label>
		</div>
		<span class="sba-error" data-error-for="type"></span>
	</fieldset>
	<?= sbLengthField() ?>
	<?= sbReasonField(array('Common' => array('Spamming Mic/Chat', 'Inappropriate Language', 'Admin disrespect', 'Harassment', 'Advertising', 'Music on the mic'), 'Custom' => sbCustomReasons())) ?>
<?php
}

/**
 * Ban and block dialogs of the players of a server (Servers list), for admins who can ban.
 */
function sbPlayerDialogs()
{
	if (!sbCan('ADMIN_ADD_BAN')) {
		return;
	}
?>
<dialog id="sba-player-ban" class="sba-dialog">
	<form data-sba-action="ban.add" novalidate>
		<div class="sba-dialog-head"><h2 class="sba-dialog-title">Ban player</h2><button type="button" class="sba-x" data-sba-close aria-label="Close">&times;</button></div>
		<div class="sba-dialog-body hlstats-scrollbar"><?php sbBanFields(); ?></div>
		<div class="sba-form-foot"><span class="sba-form-error" role="alert"></span><button type="button" class="sba-btn" data-sba-close>Cancel</button><button type="submit" class="sba-btn is-danger is-solid">Ban player</button></div>
	</form>
</dialog>
<dialog id="sba-player-comm" class="sba-dialog">
	<form data-sba-action="comm.add" novalidate>
		<div class="sba-dialog-head"><h2 class="sba-dialog-title">Block player</h2><button type="button" class="sba-x" data-sba-close aria-label="Close">&times;</button></div>
		<div class="sba-dialog-body hlstats-scrollbar"><?php sbCommFields(); ?></div>
		<div class="sba-form-foot"><span class="sba-form-error" role="alert"></span><button type="button" class="sba-btn" data-sba-close>Cancel</button><button type="submit" class="sba-btn is-primary">Block player</button></div>
	</form>
</dialog>
<?php
}

/**
 * Players of a server under its row in the Servers list: who is on, their Steam ID, and the ban and block buttons.
 */
function sbPlayersPanel($sid, array $players, array $info)
{
	global $db, $g_options;

	$now    = time();
	$steam  = array_values(array_unique(array_filter(array_column($players, 'steam2'))));
	$admins = $blocks = $profiles = array();
	if ($steam) {
		$match = implode(', ', array_map(function ($steam2) { return sbSteamMatch(sbSteamIds($steam2)); }, $steam));
		$db->query("SELECT authid, user FROM " . DB_SBPREFIX . "_admins WHERE aid > 0 AND authid IN ($match)");
		foreach ($db->fetch_row_set() ?: array() as $row) {
			$admins[sbSteamIds($row['authid'])['steam2'] ?? ''] = $row['user'];
		}
		$db->query("SELECT authid, type FROM " . DB_SBPREFIX . "_comms WHERE authid IN ($match) AND RemoveType IS NULL AND (length = 0 OR ends > $now)");
		foreach ($db->fetch_row_set() ?: array() as $row) {
			$blocks[sbSteamIds($row['authid'])['steam2'] ?? ''][(int) $row['type']] = true;
		}
		// HLstatsZ profiles in the server's game (HLstatsZ keeps STEAM_X:Y:Z as "Y:Z")
		if ($hlz = sbServers()[(int) $sid]['hlz'] ?? null) {
			$unique = implode(', ', array_map(function ($steam2) { return "'" . substr($steam2, 8) . "'"; }, $steam));
			$db->query("SELECT uniqueId, playerId FROM `" . DB_NAME . "`.hlstats_PlayerUniqueIds WHERE game = '" . $db->escape($hlz['game']) . "' AND uniqueId IN ($unique)");
			foreach ($db->fetch_row_set() ?: array() as $row) {
				$profiles['STEAM_0:' . $row['uniqueId']] = (int) $row['playerId'];
			}
		}
	}

	$head = array_filter(array($info['host'] !== '' ? '<b>' . htmlspecialchars($info['host']) . '</b>' : '', $info['map'] !== '' ? htmlspecialchars($info['map']) : '', count($players) . (count($players) === 1 ? ' player' : ' players')));
	$html = '<div class="sba-players-head"><div>' . implode(' &middot; ', $head) . '</div>'
		. '<button type="button" class="sba-btn is-small" data-sba-players-refresh>Refresh</button></div>';
	if (!$players) {
		return $html . '<p class="sba-muted sba-players-none">Nobody is playing on this server right now.</p>';
	}

	$html .= '<div class="responsive-table"><table class="sba-table sba-players-table"><thead><tr>'
		. '<th class="left">Player</th><th class="left hide-1">Steam ID</th><th class="hide-2">Time</th><th class="hide-2">Ping</th><th class="left hide">IP address</th><th></th>'
		. '</tr></thead><tbody>';
	foreach ($players as $player) {
		$steam2 = $player['steam2'];
		$name   = htmlspecialchars($player['name']);
		$badges = (isset($admins[$steam2]) ? ' <span class="sba-badge is-warn" data-tooltip="' . htmlspecialchars('SourceBans admin ' . $admins[$steam2]) . '">Admin</span>' : '')
			. (isset($blocks[$steam2][1]) ? ' <span class="sba-badge is-danger">Muted</span>' : '')
			. (isset($blocks[$steam2][2]) ? ' <span class="sba-badge is-danger">Gagged</span>' : '');
		$ban  = array('target' => $steam2 !== '' ? 'steam' : 'ip', 'steam' => $steam2, 'ip' => $player['ip'], 'name' => $player['name']);
		$comm = array('steam' => $steam2, 'name' => $player['name'], 'type' => 'mute');

		$html .= '<tr><td class="left">'
			. (isset($profiles[$steam2]) ? '<a class="hlstats-name" href="' . $g_options['scripturl'] . '?mode=playerinfo&amp;player=' . $profiles[$steam2] . '">' . $name . '</a>' : '<span class="hlstats-name">' . $name . '</span>')
			. $badges . '</td>'
			. '<td class="left hide-1">' . ($steam2 !== '' ? '<code class="sba-code">' . $steam2 . '</code>' : '<span class="sba-muted" data-tooltip="CS2 does not list Steam IDs, and HLstatsZ has no live data for this player">unknown</span>') . '</td>'
			. '<td class="hide-2 nowrap">' . htmlspecialchars((string) $player['time']) . '</td>'
			. '<td class="hide-2">' . htmlspecialchars((string) $player['ping']) . '</td>'
			. '<td class="left hide">' . htmlspecialchars($player['ip']) . '</td>'
			. '<td class="right nowrap">'
			. '<button type="button" class="sba-btn is-small is-danger" data-sba-edit="sba-player-ban" data-sba-title="' . htmlspecialchars('Ban ' . $player['name']) . '"'
			. " data-sba-values='" . htmlspecialchars(json_encode($ban), ENT_QUOTES) . "'>Ban</button> "
			. '<button type="button" class="sba-btn is-small" data-sba-edit="sba-player-comm" data-sba-title="' . htmlspecialchars('Mute or gag ' . $player['name']) . '"'
			. " data-sba-values='" . htmlspecialchars(json_encode($comm), ENT_QUOTES) . "'"
			. ($steam2 === '' ? ' disabled data-tooltip="Needs the Steam ID"' : '') . '>Mute/Gag</button>'
			. '</td></tr>';
	}
	return $html . '</tbody></table></div>';
}

/**
 * Dialogs behind the admin buttons of the ban and comm block details (edit, unban or lift).
 */
function sbBanDialogs()
{
?>
<dialog id="sba-ban-edit" class="sba-dialog">
	<form data-sba-action="ban.edit" novalidate>
		<input type="hidden" name="id" value="0">
		<div class="sba-dialog-head"><h2 class="sba-dialog-title">Edit</h2><button type="button" class="sba-x" data-sba-close aria-label="Close">&times;</button></div>
		<div class="sba-dialog-body hlstats-scrollbar">
			<label class="sba-field"><span class="sba-label">Player name</span><input type="text" name="name" maxlength="128" required><span class="sba-error" data-error-for="name"></span></label>
			<?= sbLengthField() ?>
			<p class="sba-muted sba-tight">The length counts from the original date.</p>
			<?= sbReasonField(array('Hacking' => SB_REASONS['Hacking'], 'Behaviour' => SB_REASONS['Behaviour'], 'Custom' => sbCustomReasons())) ?>
		</div>
		<div class="sba-form-foot"><span class="sba-form-error" role="alert"></span><button type="button" class="sba-btn" data-sba-close>Cancel</button><button type="submit" class="sba-btn is-primary">Save</button></div>
	</form>
</dialog>
<dialog id="sba-ban-lift" class="sba-dialog">
	<form data-sba-action="ban.unban" novalidate>
		<input type="hidden" name="id" value="0">
		<div class="sba-dialog-head"><h2 class="sba-dialog-title">Unban</h2><button type="button" class="sba-x" data-sba-close aria-label="Close">&times;</button></div>
		<div class="sba-dialog-body hlstats-scrollbar">
			<p class="sba-tight" data-sba-text="summary"></p>
			<label class="sba-field"><span class="sba-label">Why?</span><input type="text" name="reason" maxlength="500" required placeholder="Shown with the ban"><span class="sba-error" data-error-for="reason"></span></label>
		</div>
		<div class="sba-form-foot"><span class="sba-form-error" role="alert"></span><button type="button" class="sba-btn" data-sba-close>Cancel</button><button type="submit" class="sba-btn is-primary">Confirm</button></div>
	</form>
</dialog>
<?php
}

/**
 * Admin buttons under the details of a ban or comm block, as the signed-in admin's rights allow.
 * $row needs bid, name, aid, admin_gid, length, ends, RemoveType and reason.
 */
function sbBanActions($type, array $row)
{
	$now    = time();
	$length = (int) $row['length'];
	$active = $row['RemoveType'] === null && ($length === 0 || (int) $row['ends'] > $now);
	$prefix = $type === 'comm' ? 'comm' : 'ban';
	$noun   = $type === 'comm' ? 'comm block' : 'ban';
	$html   = '';

	if (sbCanOnBan($row, 'edit')) {
		$values = array('_action' => "$prefix.edit", 'id' => (int) $row['bid'], 'name' => $row['name'], 'reason' => $row['reason'], 'length' => $length >= 0 ? (string) intdiv($length, 60) : '');
		$html  .= '<button type="button" class="sba-btn is-small" data-sba-edit="sba-ban-edit" data-sba-title="Edit ' . $noun . '"'
			. " data-sba-values='" . htmlspecialchars(json_encode($values), ENT_QUOTES) . "'>Edit</button> ";
	}
	if ($active && sbCanOnBan($row, 'unban')) {
		$label  = $type === 'comm' ? 'Lift block' : 'Unban';
		$values = array('_action' => "$prefix.unban", 'id' => (int) $row['bid'], '@summary' => ($type === 'comm' ? 'Lift the block of ' : 'Unban ') . $row['name'] . '?');
		$html  .= '<button type="button" class="sba-btn is-small" data-sba-edit="sba-ban-lift" data-sba-title="' . $label . '"'
			. " data-sba-values='" . htmlspecialchars(json_encode($values), ENT_QUOTES) . "'>$label</button> ";
	}
	if (sbCanOnBan($row, 'delete')) {
		$html .= '<button type="button" class="sba-btn is-small is-danger" data-sba-do="' . $prefix . '.delete" data-sba-id="' . (int) $row['bid'] . '"'
			. ' data-sba-confirm="' . htmlspecialchars('Delete this ' . $noun . ' of ' . $row['name'] . ' for good? Unbanning keeps a record; deleting does not.') . '">Delete</button>';
	}
	return $html !== '' ? '<div class="sba-actions">' . $html . '</div>' : '';
}

/**
 * Boot data of the admin script: API address and anti-forgery token.
 */
function sbAdminScript()
{
	global $g_options;
?>
<link rel="stylesheet" href="styles/sourcebans-admin.css?<?= filemtime('styles/sourcebans-admin.css') ?>">
<script>window.SBA = <?= json_encode(array('api' => $g_options['scripturl'] . '?mode=sourcebans&task=admin_api', 'token' => sbToken()), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= INCLUDE_PATH ?>/js/sourcebans-admin.js?<?= filemtime(INCLUDE_PATH . '/js/sourcebans-admin.js') ?>" defer></script>
<?php
}
